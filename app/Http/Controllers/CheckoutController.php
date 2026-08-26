<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Services\CartService;
use App\Services\EasyParcelService;
use App\Support\ShippingQuote;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** REQ-004 — Planning §9. */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly EasyParcelService $easyparcel,
    ) {}

    public function create(): View|RedirectResponse
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = (int) $lines->sum('line_total_minor');

        return view('storefront.checkout', [
            'lines' => $lines,
            'subtotalMinor' => $subtotal,
            // Rates are fetched by AJAX once an address is entered; until then
            // the flat rate is shown so a total is always visible.
            'fallbackQuote' => $this->easyparcel->flatQuote(),
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Your cart is empty.');
        }

        // Stock is CHECKED here, not held. No reservation — the decrement
        // happens once inside the verified-payment transaction (Planning §7.5).
        foreach ($lines as $line) {
            if ($line->qty < 1 || $line->qty > $line->stock_qty) {
                return redirect()
                    ->route('cart.index')
                    ->with('error', 'Stock changed while you were checking out. Please review your cart.');
            }
        }

        // Every figure below is derived from the database or from a fresh
        // server-side quotation, never from the request (spec §17).
        $subtotalMinor = (int) $lines->sum('line_total_minor');

        $quote = $this->resolveShippingQuote($request);
        $shippingFeeMinor = $quote->priceMinor;
        $grandTotalMinor = $subtotalMinor + $shippingFeeMinor;

        $order = $this->createOrderWithRetry(function () use ($request, $lines, $subtotalMinor, $shippingFeeMinor, $grandTotalMinor, $quote): Order {
            // shipping_service_id is validated input but NOT an order
            // attribute — it is an identifier used to re-quote, and Order
            // deliberately does not make it fillable.
            $order = new Order($request->safe()->except('shipping_service_id'));

            // Set explicitly: these are guarded out of $fillable precisely so a
            // request can never reach them.
            $order->forceFill([
                'order_no' => $this->generateOrderNumber(),
                'subtotal_minor' => $subtotalMinor,
                'shipping_fee_minor' => $shippingFeeMinor,
                'grand_total_minor' => $grandTotalMinor,
                'order_status' => OrderStatus::PendingPayment,
                'payment_status' => PaymentStatus::Pending,
                'courier_name' => $quote->label(),
                'courier_service_id' => $quote->serviceId,
                'shipping_rate_source' => $quote->source,
            ])->save();

            foreach ($lines as $line) {
                // Snapshot. A later catalogue edit must never rewrite history
                // (Planning §9.3).
                $order->items()->create([
                    'product_variant_id' => $line->variant->id,
                    'product_name' => $line->variant->product->name,
                    'variation_label' => $line->variant->variationLabel(),
                    'sku' => $line->variant->sku,
                    'unit_price_minor' => $line->unit_price_minor,
                    'qty' => $line->qty,
                    'line_total_minor' => $line->line_total_minor,
                ]);
            }

            return $order;
        });

        $this->cart->clear();

        Log::info('Order created', [
            'order_no' => $order->order_no,
            'grand_total_minor' => $order->grand_total_minor,
            'items' => $lines->count(),
        ]);

        return redirect()->route('payment.pay', $order->order_no);
    }

    public function confirmation(string $orderNo): View
    {
        $order = Order::query()->where('order_no', $orderNo)->with('items')->firstOrFail();

        return view('storefront.checkout_confirmation', ['order' => $order]);
    }

    /**
     * Re-quotes server-side and matches the customer's chosen service_id
     * (Planning §11.B.4).
     *
     * The browser posts an identifier, never a price. If that service is no
     * longer offered — or the API is unreachable — the flat rate applies rather
     * than whatever the browser claimed.
     */
    private function resolveShippingQuote(CheckoutRequest $request): ShippingQuote
    {
        $chosen = $request->validated()['shipping_service_id'] ?? null;

        if ($chosen === null || $chosen === 'flat') {
            return $this->easyparcel->flatQuote();
        }

        $quotes = $this->easyparcel->quote(
            $request->validated()['postcode'],
            $request->validated()['state'],
            $this->cart->totalWeightG(),
        );

        foreach ($quotes as $quote) {
            if ($quote->serviceId === $chosen) {
                return $quote;
            }
        }

        Log::warning('Chosen courier is no longer quoted; using flat rate', ['service_id' => $chosen]);

        return $this->easyparcel->flatQuote();
    }

    /**
     * The UNIQUE key on orders.order_no is the real guard, so a collision is
     * handled by retrying the whole transaction rather than by locking or by
     * SELECT MAX() (Planning §9.3).
     */
    private function createOrderWithRetry(callable $persist, int $attempts = 5): Order
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction($persist);
            } catch (QueryException $e) {
                if ($attempt >= $attempts || ! $this->isDuplicateOrderNumber($e)) {
                    throw $e;
                }

                Log::warning('Order number collided, retrying', ['attempt' => $attempt]);
            }
        }
    }

    private function isDuplicateOrderNumber(QueryException $e): bool
    {
        return $e->getCode() === '23000' && str_contains($e->getMessage(), 'order_no');
    }

    /**
     * ORD-YYYYMMDD-NNNN, sequential within the day. Not random: with 4 digits a
     * random suffix collides about half the time by ~120 orders in a day.
     */
    private function generateOrderNumber(): string
    {
        $sequence = Order::query()->whereDate('created_at', today())->count() + 1;

        return sprintf('ORD-%s-%04d', now()->format('Ymd'), $sequence);
    }
}
