<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** REQ-004 — Planning §9. */
class CheckoutController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function create(): View|RedirectResponse
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = (int) $lines->sum('line_total_minor');
        $shipping = $this->shippingFeeMinor();

        return view('storefront.checkout', [
            'lines' => $lines,
            'subtotalMinor' => $subtotal,
            'shippingFeeMinor' => $shipping,
            'grandTotalMinor' => $subtotal + $shipping,
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

        // Every figure below is derived from the database, never from the
        // request. The browser cannot influence what is charged (spec §17).
        $subtotalMinor = (int) $lines->sum('line_total_minor');
        $shippingFeeMinor = $this->shippingFeeMinor();
        $grandTotalMinor = $subtotalMinor + $shippingFeeMinor;

        $order = $this->createOrderWithRetry(function () use ($request, $lines, $subtotalMinor, $shippingFeeMinor, $grandTotalMinor): Order {
            $order = new Order($request->validated());

            // Set explicitly: these are guarded out of $fillable precisely so a
            // request can never reach them.
            $order->forceFill([
                'order_no' => $this->generateOrderNumber(),
                'subtotal_minor' => $subtotalMinor,
                'shipping_fee_minor' => $shippingFeeMinor,
                'grand_total_minor' => $grandTotalMinor,
                'order_status' => OrderStatus::PendingPayment,
                'payment_status' => PaymentStatus::Pending,
                // Phase 8 replaces this with the selected EasyParcel service.
                'shipping_rate_source' => 'flat',
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

        // Phase 7 redirects to ToyyibPay from here.
        return redirect()
            ->route('checkout.confirmation', $order->order_no)
            ->with('status', 'Order created.');
    }

    public function confirmation(string $orderNo): View
    {
        $order = Order::query()->where('order_no', $orderNo)->with('items')->firstOrFail();

        return view('storefront.checkout_confirmation', ['order' => $order]);
    }

    /**
     * Flat rate until Phase 8 wires EasyParcel quotations. The fallback is a
     * real setting, not a placeholder: it stays in use whenever the rate API is
     * unreachable (Planning §11.B.6, OQ-04).
     */
    private function shippingFeeMinor(): int
    {
        return Setting::getInt('flat_shipping_fee_minor', 1000);
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
