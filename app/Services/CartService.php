<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * REQ-003 — Planning §8.
 *
 * The cart lives in the session and stores ONLY variant_id => qty. Product
 * name, variation label and price are re-read from the database on every render
 * and again at order creation, so a stale session can never carry an old price
 * into a real order.
 *
 * Keyed by variant_id, which IS the variation identity — T-Shirt/M/Black and
 * T-Shirt/L/Black are distinct keys with no comparison logic (spec §10).
 */
class CartService
{
    private const KEY = 'cart';

    public function __construct(private readonly Session $session) {}

    /** @return array<int, int> variant_id => qty */
    public function raw(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    /** Total units, for the nav badge. */
    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function qtyFor(int $variantId): int
    {
        return $this->raw()[$variantId] ?? 0;
    }

    /**
     * Adds to the existing quantity and clamps to available stock, so the cart
     * can never hold more than could actually be sold.
     *
     * @return int the resulting quantity for that line (0 if nothing could be added)
     */
    public function add(ProductVariant $variant, int $qty): int
    {
        $requested = $this->qtyFor($variant->id) + max(1, $qty);
        $resulting = min($requested, $variant->stock_qty);

        if ($resulting < 1) {
            return 0;
        }

        $this->write($variant->id, $resulting);

        return $resulting;
    }

    /** Sets an absolute quantity. Zero removes the line. */
    public function set(ProductVariant $variant, int $qty): void
    {
        if ($qty < 1) {
            $this->remove($variant->id);

            return;
        }

        $this->write($variant->id, min($qty, $variant->stock_qty));
    }

    public function remove(int $variantId): void
    {
        $cart = $this->raw();
        unset($cart[$variantId]);
        $this->session->put(self::KEY, $cart);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * Hydrates the cart from the database with LIVE prices.
     *
     * Lines whose variant has vanished or been deactivated are pruned from the
     * session here rather than rendered — the customer cannot buy them, so
     * carrying them to checkout would only fail later.
     *
     * @return Collection<int, object>
     */
    public function lines(): Collection
    {
        $cart = $this->raw();

        if ($cart === []) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->with('product')
            ->active()
            ->whereKey(array_keys($cart))
            ->whereHas('product', fn ($q) => $q->active())
            ->get()
            ->keyBy('id');

        foreach (array_keys($cart) as $variantId) {
            if (! $variants->has($variantId)) {
                $this->remove($variantId);
            }
        }

        return $variants->map(function (ProductVariant $variant) use ($cart): object {
            // Clamp to current stock: it may have fallen since this was added.
            $qty = min($cart[$variant->id], $variant->stock_qty);

            return (object) [
                'variant' => $variant,
                'qty' => $qty,
                'unit_price_minor' => $variant->price_minor,
                'line_total_minor' => Money::lineTotal($variant->price_minor, $qty),
                'stock_qty' => $variant->stock_qty,
                'in_stock' => $variant->stock_qty > 0,
                'reduced' => $qty < $cart[$variant->id],
            ];
        })->values();
    }

    public function subtotalMinor(): int
    {
        return (int) $this->lines()->sum('line_total_minor');
    }

    /**
     * Parcel weight for the shipping quotation. A variant with no weight falls
     * back to the store default so a quote is never requested at zero weight
     * (Planning §11.B.4, OQ-01).
     */
    public function totalWeightG(): int
    {
        $default = Setting::getInt('default_weight_g', 500);

        return (int) $this->lines()->sum(
            fn (object $line): int => ($line->variant->weight_g ?: $default) * $line->qty
        );
    }

    private function write(int $variantId, int $qty): void
    {
        $cart = $this->raw();
        $cart[$variantId] = $qty;
        $this->session->put(self::KEY, $cart);
    }
}
