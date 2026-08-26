<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * REQ-008 — stock adjustment only.
 *
 * Variants are DEFINED on the product form (Planning §7.1); this screen exists
 * for the job an admin does daily: changing quantities. Two places to create a
 * variant would be two places to get the option rules wrong.
 */
class VariationController extends Controller
{
    public function index(Product $product): View
    {
        return view('admin.products.variations', [
            'product' => $product,
            'variants' => $product->variants()->orderBy('option1_value')->orderBy('option2_value')->get(),
            'lowStockThreshold' => Setting::getInt('low_stock_threshold', 5),
        ]);
    }

    /**
     * Stock adjustment is a separate action from editing the variation: it is
     * the one the admin performs often, and it is logged because a stock number
     * that changed unexpectedly is a question someone will eventually ask.
     */
    public function updateStock(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->assertBelongsTo($product, $variant);

        $validated = $request->validate([
            'stock_qty' => ['required', 'integer', 'min:0'],
        ]);

        $before = $variant->stock_qty;
        $variant->update($validated);

        Log::info('Admin adjusted stock', [
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'from' => $before,
            'to' => $variant->stock_qty,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', "Stock for {$variant->sku} updated.");
    }

    private function assertBelongsTo(Product $product, ProductVariant $variant): void
    {
        // Nested route params are two independent ids; without this check an
        // admin could edit another product's variant by editing the URL.
        abort_unless($variant->product_id === $product->id, 404);
    }
}
