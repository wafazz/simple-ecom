<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
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

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Re-resolved from the database and checked for sellability. The posted
        // id is an identifier, never a source of price or availability.
        $variant = $this->sellableVariant((int) $data['variant_id']);

        if ($variant === null) {
            return $this->added($request, 'That item is no longer available.', ok: false);
        }

        if ($variant->stock_qty < 1) {
            return $this->added($request, "{$variant->product->name} is out of stock.", ok: false);
        }

        $requested = (int) ($data['qty'] ?? 1);
        $before = $this->cart->qtyFor($variant->id);
        $resulting = $this->cart->add($variant, $requested);

        if ($resulting === 0) {
            return $this->added($request, 'That item is out of stock.', ok: false);
        }

        $label = trim($variant->product->name.' '.$variant->variationLabel());
        $capped = $resulting < $before + $requested;

        return $this->added($request, $capped
            ? "Added {$label}. Only {$resulting} available, so the quantity was capped."
            : "Added {$label} to your cart.");
    }

    /**
     * One outcome, two audiences.
     *
     * The fetch-based Add to cart wants JSON and a new badge count; a browser
     * with JavaScript off posts the same form and must still get a redirect
     * and a flash message. Both come from the SAME code path — the moment they
     * diverge, one of them starts drifting untested.
     */
    private function added(Request $request, string $message, bool $ok = true): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'cart_count' => $this->cart->count(),
            ], $ok ? 200 : 422);
        }

        return $ok
            ? redirect()->route('cart.index')->with('status', $message)
            : back()->with('error', $message);
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
