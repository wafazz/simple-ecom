<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

/** REQ-001 — the shop front page. */
class HomeController extends Controller
{
    public function index(): View
    {
        return view('storefront.home', [
            'categories' => Category::query()->active()->orderBy('name')->get(),
            // The newest arrivals, and only products a customer can actually
            // buy — the same sellability rule the listing uses.
            'featured' => Product::query()
                ->active()
                ->with(['category', 'images'])
                ->whereHas('variants', fn ($q) => $q->active())
                ->withMin(['variants as min_price_minor' => fn ($q) => $q->active()], 'price_minor')
                ->withSum(['variants as stock_total' => fn ($q) => $q->active()], 'stock_qty')
                ->latest('id')
                ->take(8)
                ->get(),
        ]);
    }
}
