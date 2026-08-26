<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Services\CartService;
use App\Services\EasyParcelService;
use App\Support\ShippingQuote;
use App\Support\ShippingRate;
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
            // Priced from the store's own weight table. The zone is not known
            // until a state is chosen, so West is shown until then and the
            // figure updates as soon as the customer picks one.
            'weightG' => $this->cart->totalWeightG(),
            'billableKilos' => ShippingRate::billableKilos($this->cart->totalWeightG()),
            'fallbackQuote' => ShippingRate::quoteFor('MY-14', $this->cart->totalWeightG()),
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
                'order_status' => OrderStatus::Pending,
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
     * The delivery charge, computed server-side from the store's weight table.
     *
     * Nothing the browser sent is used. Not a price, not a service id, not a
     * weight — the state comes from the validated address and the weight is
     * re-read from the cart, so what is charged cannot be influenced from
     * outside (§17). This is why there is no longer anything to "match": the
     * figure is derived, not chosen.
     */
    private function resolveShippingQuote(CheckoutRequest $request): ShippingQuote
    {
        return ShippingRate::quoteFor(
            $request->validated()['state'],
            $this->cart->totalWeightG(),
        );
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
