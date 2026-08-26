<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VariationRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** REQ-002 / REQ-008 — Planning §7.1, §7.5. */
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

    public function store(VariationRequest $request, Product $product): RedirectResponse
    {
        $product->variants()->create($this->attributes($request));

        return redirect()
            ->route('admin.products.variations.index', $product)
            ->with('status', 'Variation added.');
    }

    public function update(VariationRequest $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->assertBelongsTo($product, $variant);

        $variant->update($this->attributes($request));

        return redirect()
            ->route('admin.products.variations.index', $product)
            ->with('status', 'Variation updated.');
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

    /**
     * @return array<string, mixed>
     */
    private function attributes(VariationRequest $request): array
    {
        $data = $request->safe()->except('price');

        // '' not NULL — the unique index treats NULLs as distinct, so a null
        // option slot would allow duplicate "no-option" variants (Planning §7.1).
        foreach (['option1_name', 'option1_value', 'option2_name', 'option2_value'] as $field) {
            $data[$field] = (string) ($data[$field] ?? '');
        }

        // An option value with no name (or vice versa) is a half-filled axis;
        // collapse it so the row is internally consistent.
        foreach ([1, 2] as $axis) {
            if ($data["option{$axis}_value"] === '') {
                $data["option{$axis}_name"] = '';
            }
        }

        $data['price_minor'] = $request->priceMinor();

        return $data;
    }

    private function assertBelongsTo(Product $product, ProductVariant $variant): void
    {
        // Nested route params are two independent ids; without this check an
        // admin could edit another product's variant by editing the URL.
        abort_unless($variant->product_id === $product->id, 404);
    }
}
