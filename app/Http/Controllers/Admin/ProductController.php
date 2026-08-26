<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** REQ-001 */
class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::query()
                ->with('category')
                ->withCount('variants')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(['is_active' => true]),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create([
            ...$request->safe()->except('image'),
            'image_path' => $this->storeImage($request),
        ]);

        // Every product must have at least one variant, so the admin is taken
        // straight there rather than left with an unbuyable product
        // (Planning §7).
        return redirect()
            ->route('admin.products.variations.index', $product)
            ->with('status', 'Product created. Add at least one variation so it can be sold.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $attributes = $request->safe()->except('image');

        if ($path = $this->storeImage($request)) {
            $this->deleteImage($product->image_path);
            $attributes['image_path'] = $path;
        }

        $product->update($attributes);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product updated.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with(
            'status',
            $product->is_active ? 'Product activated.' : 'Product deactivated.'
        );
    }

    /** Stored under a framework-generated name — never the client's filename. */
    private function storeImage(ProductRequest $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return Storage::disk('uploads')->putFile('products', $request->file('image'));
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('uploads')->delete($path);
        }
    }
}
