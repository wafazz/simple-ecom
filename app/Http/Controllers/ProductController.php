<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** REQ-001 / REQ-002 — storefront catalogue. */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->filled('category')
            ? Category::query()->active()->where('slug', $request->string('category'))->first()
            : null;

        $products = Product::query()
            ->active()
            ->with('category')
            // Only products that can actually be bought. A product whose every
            // variant is inactive is not a product a customer can order.
            ->whereHas('variants', fn ($q) => $q->active())
            ->withMin(['variants as min_price_minor' => fn ($q) => $q->active()], 'price_minor')
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('storefront.products', [
            'products' => $products,
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'activeCategory' => $category,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $variants = $product->variants()
            ->active()
            ->orderBy('option1_value')
            ->orderBy('option2_value')
            ->get();

        abort_if($variants->isEmpty(), 404);

        return view('storefront.product', [
            'product' => $product->load('category'),
            'variants' => $variants,
        ]);
    }
}
