<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\ToyyibPayService;
use App\Support\MailSettings;
use App\Support\PaymentVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * REQ-005 — Planning §11.A.
 *
 * The return URL and the callback are UNTRUSTED notifications. Neither the
 * browser's status_id nor the callback's status/amount is written to the order.
 * Both handlers take the bill code and nothing else, then re-query the gateway
 * server-side (§11.A.5).
 */
class PaymentController extends Controller
{
    public function __construct(private readonly ToyyibPayService $toyyibpay) {}

    /** Creates the bill and sends the customer to the gateway. */
    public function pay(string $orderNo): RedirectResponse
    {
        $order = Order::query()->where('order_no', $orderNo)->firstOrFail();

        if ($order->payment_status !== PaymentStatus::Pending) {
            return redirect()->route('checkout.confirmation', $order->order_no);
        }

        if (! $this->toyyibpay->isConfigured()) {
            Log::warning('Payment attempted while ToyyibPay is unconfigured', ['order_no' => $order->order_no]);

            return redirect()
                ->route('checkout.confirmation', $order->order_no)
                ->with('error', 'Online payment is not available yet. Your order has been saved.');
        }

        try {
            $billCode = $this->toyyibpay->createBill(
                $order,
                route('payment.return'),
                route('payment.callback'),
            );
        } catch (Throwable $e) {
            Log::error('Failed to create ToyyibPay bill', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('checkout.confirmation', $order->order_no)
                ->with('error', 'We could not start the payment. Your order has been saved — please try again.');
        }

        // Recorded before the redirect so the bill code is never orphaned.
        Payment::updateOrCreate(
            ['order_id' => $order->id, 'provider' => 'toyyibpay'],
            ['bill_code' => $billCode, 'amount_minor' => $order->grand_total_minor, 'status' => PaymentStatus::Pending],
        );

        return redirect()->away($this->toyyibpay->paymentUrl($billCode));
    }

    /** Browser redirect back from the gateway. Untrusted. */
    public function handleReturn(Request $request): View|RedirectResponse
    {
        $billCode = (string) $request->query('billcode', '');
        $order = $this->orderForBillCode($billCode);

        if ($order === null) {
            return redirect()->route('home')->with('error', 'We could not match that payment to an order.');
        }

        $this->settle($order, $billCode, 'return');
        $order->load('items');

        return view('storefront.payment_result', ['order' => $order->fresh()]);
    }

    /**
     * Server-to-server callback. Untrusted, CSRF-exempt (Planning §11.A.4).
     * Always answers 200 so the gateway does not retry a message we handled.
     */
    public function handleCallback(Request $request): Response
    {
        $billCode = (string) $request->input('billcode', '');
        $order = $this->orderForBillCode($billCode);

        if ($order === null) {
            Log::warning('Callback for an unknown bill code', ['bill_code' => $billCode]);

            return response('OK', 200);
        }

        // The official reference states the MD5 hash MUST be validated before
        // processing. It is a cheap first gate: a forged callback is rejected
        // here, before any outbound request. The server-side re-query in
        // settle() remains the actual proof of payment — this narrows the
        // attack surface, it does not replace it.
        $callback = $request->all();

        if ($this->toyyibpay->callbackIsSigned($callback)
            && ! $this->toyyibpay->callbackHashIsValid($callback)) {
            Log::error('Callback hash mismatch — ignoring', [
                'order_no' => $order->order_no,
                'bill_code' => $billCode,
            ]);

            // Still 200: a retry would deliver the same bad hash.
            return response('OK', 200);
        }

        $this->settle($order, $billCode, 'callback');

        return response('OK', 200);
    }

    /**
     * The single settlement path, shared by both notifications.
     *
     * Re-queries, checks amount AND reference, then transitions the order and
     * decrements stock inside one transaction. Idempotent: only the first
     * caller wins the guarded update, so a duplicate callback is a no-op.
     */
    private function settle(Order $order, string $billCode, string $source): void
    {
        $result = $this->toyyibpay->verifyPayment($billCode);

        Log::info('Payment verification', [
            'order_no' => $order->order_no,
            'source' => $source,
            'status' => $result->status,
            'reason' => $result->reason,
        ]);

        if ($result->isUnverified()) {
            // Deliberate: the order stays pending rather than being settled on
            // a response we cannot read (Planning §11.A.6, OQ-11).
            Log::warning('Payment left UNVERIFIED — order stays pending', [
                'order_no' => $order->order_no,
                'reason' => $result->reason,
            ]);

            return;
        }

        if ($result->status === PaymentVerification::FAILED) {
            $this->recordPayment($order, $billCode, $result, PaymentStatus::Failed);
            $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();

            return;
        }

        if (! $result->isPaid()) {
            return;
        }

        // The two checks that make the gateway's word insufficient on its own.
        if ($result->amountMinor !== $order->grand_total_minor) {
            Log::error('Payment amount mismatch — refusing to settle', [
                'order_no' => $order->order_no,
                'expected_minor' => $order->grand_total_minor,
                'reported_minor' => $result->amountMinor,
                'interpretations' => $this->toyyibpay->describeAmountInterpretations(
                    (string) ($result->raw['billpaymentAmount'] ?? $result->raw['amount'] ?? '')
                ),
            ]);

            return;
        }

        if ($result->externalReference !== null && $result->externalReference !== $order->order_no) {
            Log::error('Payment reference mismatch — refusing to settle', [
                'order_no' => $order->order_no,
                'reported' => $result->externalReference,
            ]);

            return;
        }

        $this->markPaid($order, $billCode, $result);
    }

    private function markPaid(Order $order, string $billCode, PaymentVerification $result): void
    {
        $settled = DB::transaction(function () use ($order, $billCode, $result): bool {
            // Guarded transition. Only the first caller proceeds; a duplicate
            // callback gets false and must not move stock.
            if (! Order::markPaidAtomically($order->id)) {
                Log::info('Duplicate settlement ignored', ['order_no' => $order->order_no]);

                return false;
            }

            $this->recordPayment($order, $billCode, $result, PaymentStatus::Paid);

            $shortfall = false;

            foreach ($order->items()->get() as $item) {
                if (! ProductVariant::decrementStockAtomically($item->product_variant_id, $item->qty)) {
                    $shortfall = true;

                    Log::error('Oversell on settlement — insufficient stock', [
                        'order_no' => $order->order_no,
                        'sku' => $item->sku,
                        'qty' => $item->qty,
                    ]);
                }
            }

            if ($shortfall) {
                // Money was taken and cannot be fulfilled from stock. The order
                // is flagged rather than silently accepted (Planning §7.5).
                $order->forceFill(['order_status' => OrderStatus::NeedsReview])->save();
            }

            return true;
        });

        Log::info('Order settled', ['order_no' => $order->order_no]);

        // AFTER the transaction, and only for the caller that actually settled
        // it. Inside, a later rollback would leave a confirmation sent for an
        // order that is not paid; and ToyyibPay sends both a return and a
        // callback, so anything not gated on markPaidAtomically() would email
        // the customer twice.
        if ($settled) {
            $this->confirmByEmail($order);
        }
    }

    /**
     * Tell the buyer their order is confirmed.
     *
     * Silent when no mail transport has been set up — an unconfigured shop
     * simply does not send, rather than erroring on every payment. And never
     * fatal: the money has arrived and the order is recorded, so a mail
     * failure must not reach the gateway, which would retry a callback that
     * already succeeded.
     */
    private function confirmByEmail(Order $order): void
    {
        if (! MailSettings::isConfigured()) {
            Log::info('Order confirmation not sent — no mail transport configured', [
                'order_no' => $order->order_no,
            ]);

            return;
        }

        try {
            Mail::to($order->customer_email)->send(
                new OrderPaid($order->fresh(['items'])),
            );

            Log::info('Order confirmation sent', ['order_no' => $order->order_no]);
        } catch (Throwable $e) {
            Log::error('Order confirmation failed to send', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function recordPayment(Order $order, string $billCode, PaymentVerification $result, PaymentStatus $status): void
    {
        Payment::updateOrCreate(
            ['order_id' => $order->id, 'provider' => 'toyyibpay'],
            [
                'bill_code' => $billCode,
                'provider_ref' => $result->providerRef,
                'amount_minor' => $result->amountMinor ?? $order->grand_total_minor,
                'status' => $status,
                'raw_response' => $this->scrub($result->raw),
                'paid_at' => $status === PaymentStatus::Paid ? now() : null,
            ],
        );
    }

    /** Credentials must never reach the database or a log (spec §24). */
    private function scrub(array $raw): array
    {
        foreach (['userSecretKey', 'secret', 'password'] as $key) {
            unset($raw[$key]);
        }

        return $raw;
    }

    private function orderForBillCode(string $billCode): ?Order
    {
        if ($billCode === '') {
            return null;
        }

        return Order::query()
            ->whereHas('payment', fn ($q) => $q->where('bill_code', $billCode))
            ->first();
    }
}
