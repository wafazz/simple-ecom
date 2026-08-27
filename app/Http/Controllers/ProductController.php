<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** REQ-001 / REQ-002 — storefront catalogue. */
class ProductController extends Controller
{
    /** Whitelist — the query string never reaches orderBy() unfiltered. */
    private const SORTS = [
        'name' => ['name', 'asc'],
        'price_asc' => ['min_price_minor', 'asc'],
        'price_desc' => ['min_price_minor', 'desc'],
        'newest' => ['created_at', 'desc'],
    ];

    private const SORT_LABELS = [
        'name' => 'Name (A–Z)',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'newest' => 'Newest first',
    ];

    public function index(Request $request): View
    {
        $category = $request->filled('category')
            ? Category::query()->active()->where('slug', $request->string('category'))->first()
            : null;

        // Sorting is whitelisted, never taken from the query string directly —
        // an unrecognised value falls back rather than reaching the builder.
        $sort = in_array($request->query('sort'), array_keys(self::SORTS), true)
            ? $request->query('sort')
            : 'name';

        $search = trim((string) $request->query('q'));
        $maxPrice = $request->filled('max_price') ? (int) round((float) $request->query('max_price') * 100) : null;

        $products = Product::query()
            ->active()
            // `images` is loaded so a product with no cover can still show one
            // of its extra views. One query for the page, not one per card.
            ->with(['category', 'images'])
            // Only products that can actually be bought. A product whose every
            // variant is inactive is not a product a customer can order.
            ->whereHas('variants', fn ($q) => $q->active())
            ->withMin(['variants as min_price_minor' => fn ($q) => $q->active()], 'price_minor')
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search): void {
                // LIKE, not a full-text index: this catalogue is small and an
                // index would be maintenance for no measurable gain (§36).
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            }))
            // Expressed against the variants, not HAVING on the subquery
            // column: SQLite rejects a HAVING clause on a non-aggregate query,
            // and the suites run on SQLite as well as MariaDB.
            ->when($maxPrice !== null, fn ($q) => $q->whereHas(
                'variants',
                fn ($v) => $v->active()->where('price_minor', '<=', $maxPrice)
            ))
            ->orderBy(...self::SORTS[$sort])
            ->paginate(12)
            ->withQueryString();

        return view('storefront.products', [
            'products' => $products,
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'activeCategory' => $category,
            'sort' => $sort,
            'sorts' => self::SORT_LABELS,
            'search' => $search,
            'maxPrice' => $request->query('max_price'),
            'ceilingMinor' => $this->priceCeilingMinor(),
        ]);
    }

    /**
     * The label for an option axis, from whichever variant actually names it.
     *
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function axisName($variants, int $axis): string
    {
        return (string) $variants->pluck("option{$axis}_name")->filter()->first();
    }

    /**
     * Can the swatch grid express every variant of this product?
     *
     * Only when each variant carries a value on each axis the grid would draw.
     * One row with a blank option leaves a variant no swatch can select, and a
     * picker that cannot reach a variant must not be the only way to buy.
     *
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function swatchesCanRepresent($variants): bool
    {
        foreach ([1, 2] as $axis) {
            if ($this->axisName($variants, $axis) === '') {
                continue;
            }

            foreach ($variants as $variant) {
                if ((string) $variant->{"option{$axis}_value"} === '') {
                    return false;
                }
            }
        }

        return $this->axisName($variants, 1) !== '';
    }

    /** The top of the price slider — the dearest thing actually on sale. */
    private function priceCeilingMinor(): int
    {
        $max = (int) ProductVariant::query()
            ->active()
            ->whereHas('product', fn ($q) => $q->active())
            ->max('price_minor');

        // Round up to a whole ringgit so the slider ends on a tidy number.
        return max((int) ceil($max / 100) * 100, 100);
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
            'product' => $product->load('category', 'images'),
            'variants' => $variants,
            // Rendered into the page for the variant picker. Price and stock
            // are DISPLAY values only — the cart re-reads both from the
            // database on every add, so a tampered figure buys nothing (§17).
            'variantData' => $variants->map(fn (ProductVariant $v): array => [
                'id' => $v->id,
                'option1' => $v->option1_value,
                'option2' => $v->option2_value,
                // Symbol-free: the template prints it once, outside the span
                // this value is written into.
                'price' => Money::format($v->price_minor),
                'stock' => $v->stock_qty,
                'sku' => $v->sku,
            ])->values()->all(),
            // Taken from ANY variant carrying a name, not from first(). The
            // set is ordered by option value, so a row with a blank second
            // option sorts first — and reading the name off that row hid the
            // whole axis, leaving every one of its variants unreachable.
            'option1Name' => $this->axisName($variants, 1),
            'option2Name' => $this->axisName($variants, 2),
            'option1Values' => $variants->pluck('option1_value')->unique()->filter()->values()->all(),
            'option2Values' => $variants->pluck('option2_value')->unique()->filter()->values()->all(),

            // Swatches can only represent variants that carry a value on every
            // axis drawn. A half-filled axis makes some combination unnameable,
            // and the plain <select> — which lists every variant, always — is
            // the honest fallback.
            'useSwatches' => $this->swatchesCanRepresent($variants),
        ]);
    }
}
