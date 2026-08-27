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
 * The cart lives in the session and stores variant_id => {qty, nameset}.
 * Product name, variation label and price are re-read from the database on
 * every render and again at order creation, so a stale session can never carry
 * an old price into a real order — the nameset is the one thing the customer
 * types, and even its PRICE is re-read from the product.
 *
 * Keyed by variant_id, which IS the variation identity — T-Shirt/M/Black and
 * T-Shirt/L/Black are distinct keys with no comparison logic (spec §10).
 *
 * ⚠ One nameset per line, because the key is the variant alone. Adding the same
 * jersey again with a different name REPLACES it rather than making a second
 * line, and the flash message says so. Two different names on the same size
 * means two separate visits to the cart — a deliberate limit, not an oversight
 * (see the scope note on the nameset feature).
 */
class CartService
{
    private const KEY = 'cart';

    public function __construct(private readonly Session $session) {}

    /**
     * @return array<int, array{qty: int, nameset: array{name: string, number: string}|null}>
     */
    public function raw(): array
    {
        $normalised = [];

        foreach ($this->session->get(self::KEY, []) as $variantId => $line) {
            // A session written before namesets existed holds a bare integer.
            // Live carts must survive the deploy rather than throw on the next
            // page load.
            $normalised[(int) $variantId] = is_array($line)
                ? ['qty' => (int) ($line['qty'] ?? 0), 'nameset' => $line['nameset'] ?? null]
                : ['qty' => (int) $line, 'nameset' => null];
        }

        return $normalised;
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    /** Total units, for the nav badge. */
    public function count(): int
    {
        return (int) array_sum(array_column($this->raw(), 'qty'));
    }

    public function qtyFor(int $variantId): int
    {
        return $this->raw()[$variantId]['qty'] ?? 0;
    }

    /** @return array{name: string, number: string}|null */
    public function namesetFor(int $variantId): ?array
    {
        return $this->raw()[$variantId]['nameset'] ?? null;
    }

    /**
     * Adds to the existing quantity and clamps to available stock, so the cart
     * can never hold more than could actually be sold.
     *
     * @return int the resulting quantity for that line (0 if nothing could be added)
     */
    public function add(ProductVariant $variant, int $qty, ?array $nameset = null): int
    {
        $requested = $this->qtyFor($variant->id) + max(1, $qty);
        $resulting = min($requested, $variant->stock_qty);

        if ($resulting < 1) {
            return 0;
        }

        // Passing null keeps whatever the line already had, so adding a second
        // shirt does not quietly wipe the name off the first.
        $this->write($variant->id, $resulting, $nameset ?? $this->namesetFor($variant->id));

        return $resulting;
    }

    /** Sets an absolute quantity. Zero removes the line. */
    public function set(ProductVariant $variant, int $qty): void
    {
        if ($qty < 1) {
            $this->remove($variant->id);

            return;
        }

        $this->write($variant->id, min($qty, $variant->stock_qty), $this->namesetFor($variant->id));
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
            $qty = min($cart[$variant->id]['qty'], $variant->stock_qty);

            // The name is the customer's, but the FEE is the product's, re-read
            // here like every other price. A nameset held in a session from
            // before a price change, or from a product that has since turned
            // the option off, is dropped rather than honoured.
            $nameset = $variant->product->offersNameset()
                ? $cart[$variant->id]['nameset']
                : null;

            $namesetPriceMinor = $nameset !== null ? (int) $variant->product->nameset_price_minor : 0;

            return (object) [
                'variant' => $variant,
                'qty' => $qty,
                'unit_price_minor' => $variant->price_minor,
                'nameset' => $nameset,
                'nameset_price_minor' => $namesetPriceMinor,
                // Per shirt: two of the same jersey are two prints.
                'line_total_minor' => Money::lineTotal($variant->price_minor + $namesetPriceMinor, $qty),
                'stock_qty' => $variant->stock_qty,
                'in_stock' => $variant->stock_qty > 0,
                'reduced' => $qty < $cart[$variant->id]['qty'],
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

    /** @param  array{name: string, number: string}|null  $nameset */
    private function write(int $variantId, int $qty, ?array $nameset = null): void
    {
        $cart = $this->raw();
        $cart[$variantId] = ['qty' => $qty, 'nameset' => $nameset];
        $this->session->put(self::KEY, $cart);
    }
}
