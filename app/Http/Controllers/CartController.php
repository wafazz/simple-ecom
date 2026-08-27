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

            // Nameset. Accepted here, but only kept if the PRODUCT offers it —
            // see namesetFrom(). A posted nameset on a product without the
            // option is dropped, never priced.
            'nameset' => ['nullable', 'boolean'],
            'nameset_name' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 .\'-]+$/'],
            'nameset_number' => ['nullable', 'string', 'max:3', 'regex:/^[0-9]+$/'],
        ], [
            'nameset_name.regex' => 'The name may only contain letters, numbers, spaces, full stops, hyphens and apostrophes.',
            'nameset_number.regex' => 'The number may only contain digits.',
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
        $previousNameset = $this->cart->namesetFor($variant->id);
        $nameset = $this->namesetFrom($data, $variant);

        $resulting = $this->cart->add($variant, $requested, $nameset);

        if ($resulting === 0) {
            return $this->added($request, 'That item is out of stock.', ok: false);
        }

        $label = trim($variant->product->name.' '.$variant->variationLabel());
        $capped = $resulting < $before + $requested;

        $message = $capped
            ? "Added {$label}. Only {$resulting} available, so the quantity was capped."
            : "Added {$label} to your cart.";

        // One nameset per line. Say plainly when a new one has taken the place
        // of an old one, rather than letting the change go unnoticed.
        if ($nameset !== null && $previousNameset !== null && $nameset !== $previousNameset) {
            $message .= ' The nameset on that line is now '
                .trim($nameset['name'].' '.$nameset['number']).'.';
        } elseif ($nameset !== null) {
            $message .= ' Nameset: '.trim($nameset['name'].' '.$nameset['number']).'.';
        }

        return $this->added($request, $message);
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

    /**
     * The nameset to store, or null.
     *
     * Gated on the PRODUCT, not on the request: a hand-made form posting a
     * nameset for a product that does not offer one would otherwise put an
     * unpriced instruction in front of whoever packs the parcel. A ticked box
     * with neither field filled in is also nothing.
     *
     * @param  array<string, mixed>  $data
     * @return array{name: string, number: string}|null
     */
    private function namesetFrom(array $data, ProductVariant $variant): ?array
    {
        if (! ($data['nameset'] ?? false) || ! $variant->product->offersNameset()) {
            return null;
        }

        // Jerseys are printed in capitals; storing them that way means the
        // order, the cart and the print sheet all read the same.
        $name = mb_strtoupper(trim((string) ($data['nameset_name'] ?? '')));
        $number = trim((string) ($data['nameset_number'] ?? ''));

        if ($name === '' && $number === '') {
            return null;
        }

        return ['name' => $name, 'number' => $number];
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
