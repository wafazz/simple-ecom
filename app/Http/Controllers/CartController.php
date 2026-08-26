<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** REQ-003 — Planning §8. */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(): View
    {
        $lines = $this->cart->lines();

        return view('storefront.cart', [
            'lines' => $lines,
            'subtotalMinor' => (int) $lines->sum('line_total_minor'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Re-resolved from the database and checked for sellability. The posted
        // id is an identifier, never a source of price or availability.
        $variant = $this->sellableVariant((int) $data['variant_id']);

        if ($variant === null) {
            return back()->with('error', 'That item is no longer available.');
        }

        if ($variant->stock_qty < 1) {
            return back()->with('error', "{$variant->product->name} is out of stock.");
        }

        $requested = (int) ($data['qty'] ?? 1);
        $before = $this->cart->qtyFor($variant->id);
        $resulting = $this->cart->add($variant, $requested);

        if ($resulting === 0) {
            return back()->with('error', 'That item is out of stock.');
        }

        $label = trim($variant->product->name.' '.$variant->variationLabel());
        $capped = $resulting < $before + $requested;

        return redirect()->route('cart.index')->with(
            'status',
            $capped
                ? "Added {$label}. Only {$resulting} available, so the quantity was capped."
                : "Added {$label} to your cart."
        );
    }

    public function update(Request $request, int $variant): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $model = $this->sellableVariant($variant);

        if ($model === null) {
            $this->cart->remove($variant);

            return back()->with('error', 'That item is no longer available and was removed.');
        }

        $this->cart->set($model, (int) $data['qty']);

        return back()->with('status', 'Cart updated.');
    }

    public function destroy(int $variant): RedirectResponse
    {
        $this->cart->remove($variant);

        return back()->with('status', 'Item removed.');
    }

    private function sellableVariant(int $variantId): ?ProductVariant
    {
        return ProductVariant::query()
            ->with('product')
            ->active()
            ->whereKey($variantId)
            ->whereHas('product', fn ($q) => $q->active())
            ->first();
    }
}
