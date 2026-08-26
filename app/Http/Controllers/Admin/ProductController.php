<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VariantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use League\Flysystem\FilesystemException;

/** REQ-001 / REQ-002 — product and its variations are defined in one form. */
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
            'galleryImages' => collect(),
            'variantRows' => [$this->blankRow()],
            'productType' => 'simple',
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::create([
                ...$request->safe()->only(['category_id', 'name', 'slug', 'description', 'is_active']),
                'image_path' => $this->storeImage($request),
            ]);

            $this->syncVariants($product, $request);
            $this->syncGallery($product, $request);

            return $product;
        });

        return redirect()
            ->route('admin.products.index')
            ->with('status', "{$product->name} created with ".$product->variants()->count().' variation(s).');
    }

    public function edit(Product $product): View
    {
        $variants = $product->variants()->orderBy('option1_value')->orderBy('option2_value')->get();

        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
            'galleryImages' => $product->images,
            'variantRows' => $variants->map(fn ($v): array => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => Money::format($v->price_minor),
                'stock_qty' => $v->stock_qty,
                'weight_g' => $v->weight_g,
                'length_mm' => $v->length_mm ?: '',
                'width_mm' => $v->width_mm ?: '',
                'height_mm' => $v->height_mm ?: '',
                'status' => $v->status->value,
                'option1_name' => $v->option1_name,
                'option1_value' => $v->option1_value,
                'option2_name' => $v->option2_name,
                'option2_value' => $v->option2_value,
            ])->all(),
            // A product is "simple" when its single variant carries no options.
            'productType' => $variants->count() === 1 && $variants->first()?->variationLabel() === ''
                ? 'simple'
                : 'variable',
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $retained = DB::transaction(function () use ($request, $product): array {
            $attributes = $request->safe()->only(['category_id', 'name', 'slug', 'description', 'is_active']);

            if ($path = $this->storeImage($request)) {
                $this->deleteImage($product->image_path);
                $attributes['image_path'] = $path;
            }

            $product->update($attributes);
            $this->syncGallery($product, $request);

            return $this->syncVariants($product, $request);
        });

        $message = 'Product updated.';

        if ($retained !== []) {
            // Not a silent outcome: the admin removed rows and got something
            // other than deletion.
            $message .= ' '.count($retained).' variation(s) had order history and were '
                .'deactivated instead of deleted: '.implode(', ', $retained).'.';
        }

        return redirect()->route('admin.products.index')->with('status', $message);
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with(
            'status',
            $product->is_active ? 'Product activated.' : 'Product deactivated.'
        );
    }

    /**
     * Upsert the submitted rows, then deal with the ones that disappeared.
     *
     * A removed variant is DELETED only when nothing references it. Order items
     * hold a foreign key with restrictOnDelete, because purchase history must
     * outlive the catalogue (Planning §12.2) — so a variant that has ever been
     * sold is deactivated instead. Deleting it would either throw or, with a
     * looser FK, silently orphan an order line.
     *
     * @return array<int, string> SKUs kept back rather than deleted
     */
    private function syncVariants(Product $product, ProductRequest $request): array
    {
        $keptIds = [];

        foreach ($request->variantRows() as $row) {
            $attributes = [
                'sku' => trim((string) $row['sku']),
                'price_minor' => $request->priceMinorFor($row),
                'stock_qty' => (int) $row['stock_qty'],
                'weight_g' => (int) $row['weight_g'],
                'length_mm' => (int) ($row['length_mm'] ?? 0),
                'width_mm' => (int) ($row['width_mm'] ?? 0),
                'height_mm' => (int) ($row['height_mm'] ?? 0),
                'status' => $row['status'],
            ];

            // '' not NULL: MySQL treats NULLs as distinct in a unique index, so
            // nullable option slots would permit duplicate "no-option" variants.
            foreach ([1, 2] as $axis) {
                $value = trim((string) ($row["option{$axis}_value"] ?? ''));
                $attributes["option{$axis}_value"] = $value;
                // A name with no value is a half-filled axis; collapse it.
                $attributes["option{$axis}_name"] = $value === ''
                    ? ''
                    : trim((string) ($row["option{$axis}_name"] ?? ''));
            }

            // Explicit rather than updateOrCreate(['id' => ...]): that merges the
            // search key into the create, and `id` is not fillable.
            //
            // The lookup is scoped to THIS product, so an id belonging to another
            // product simply does not match and a new variant is created — SKU
            // uniqueness then rejects it, rather than one product's form
            // silently rewriting another's variant.
            $existing = filled($row['id'] ?? null)
                ? $product->variants()->whereKey($row['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attributes);
                $variant = $existing;
            } else {
                $variant = $product->variants()->create($attributes);
            }

            $keptIds[] = $variant->id;
        }

        $deactivated = [];

        foreach ($product->variants()->whereNotIn('id', $keptIds)->withCount('orderItems')->get() as $removed) {
            if ($removed->order_items_count > 0) {
                $removed->update(['status' => VariantStatus::Inactive]);
                $deactivated[] = $removed->sku;

                Log::info('Variant retained with order history', [
                    'variant_id' => $removed->id,
                    'sku' => $removed->sku,
                ]);

                continue;
            }

            $removed->delete();
        }

        return $deactivated;
    }

    /** @return array<string, mixed> */
    private function blankRow(): array
    {
        return [
            'id' => null,
            'sku' => '',
            'price' => '',
            'stock_qty' => 0,
            'weight_g' => (int) config('shop.default_weight_g', 500),
            'length_mm' => '',
            'width_mm' => '',
            'height_mm' => '',
            'status' => VariantStatus::Active->value,
            'option1_name' => '',
            'option1_value' => '',
            'option2_name' => '',
            'option2_value' => '',
        ];
    }

    /**
     * Stored under a framework-generated name — never the client's filename.
     *
     * ⚠ The `uploads` disk is configured `'throw' => false`, which reads as "a
     * write failure is silent". It is not, and that gap produced a bare 500 on
     * a live VPS:
     *
     *   - Laravel's FilesystemAdapter catches only UnableToWriteFile and
     *     UnableToSetVisibility. Flysystem creates `products/` on the first
     *     upload, and if that mkdir fails it throws UnableToCreateDirectory,
     *     which nothing catches — so a plain directory-permission problem
     *     crashed the whole request with no usable message.
     *   - When the failure IS swallowed, putFile() returns `false`, which would
     *     otherwise be written into image_path as an empty value: a product
     *     that looks saved and has no picture.
     *
     * Both now surface as a validation error on the field the admin touched,
     * with the real cause in the log.
     */
    private function storeImage(ProductRequest $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        try {
            $path = Storage::disk('uploads')->putFile('products', $request->file('image'));
        } catch (FilesystemException $e) {
            $this->failUpload($e->getMessage());
        }

        if (! is_string($path) || $path === '') {
            $this->failUpload('putFile() returned no path.');
        }

        return $path;
    }

    /**
     * Turn a storage failure into something the admin can act on.
     *
     * The detail goes to the log, not the screen: the message carries absolute
     * server paths, which do not belong in a rendered response (§17).
     */
    private function failUpload(string $reason): never
    {
        Log::error('Product image upload failed.', [
            'disk_root' => config('filesystems.disks.uploads.root'),
            'reason' => $reason,
        ]);

        throw ValidationException::withMessages([
            'image' => 'The image could not be saved. The uploads folder is most '
                .'likely not writable by the web server — see DEPLOYMENT.md. '
                .'The product itself was not saved.',
        ]);
    }

    /**
     * Add the newly uploaded gallery views and drop the ones ticked for
     * removal. The file is deleted only after the row is gone, so a failed
     * delete leaves an orphaned file rather than a gallery pointing at nothing.
     */
    private function syncGallery(Product $product, ProductRequest $request): void
    {
        foreach ($request->input('remove_images', []) as $imageId) {
            // Scoped to this product: a forged id cannot reach another
            // product's gallery (§17 — never trust a posted identifier).
            $image = $product->images()->whereKey($imageId)->first();

            if ($image !== null) {
                $path = $image->path;
                $image->delete();
                $this->deleteImage($path);
            }
        }

        if (! $request->hasFile('gallery')) {
            return;
        }

        $next = (int) $product->images()->max('sort_order');

        foreach ($request->file('gallery') as $file) {
            try {
                $path = Storage::disk('uploads')->putFile('products', $file);
            } catch (FilesystemException $e) {
                $this->failUpload($e->getMessage());
            }

            if (! is_string($path) || $path === '') {
                $this->failUpload('putFile() returned no path for a gallery image.');
            }

            $product->images()->create(['path' => $path, 'sort_order' => ++$next]);
        }
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('uploads')->delete($path);
        }
    }
}
