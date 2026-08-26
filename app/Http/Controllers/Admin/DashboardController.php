<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\View\View;

/** REQ-009 — the four counters spec §16 asks for. */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'totalOrders' => Order::query()->count(),
            'pendingOrders' => Order::query()->where('payment_status', PaymentStatus::Pending->value)->count(),
            'paidOrders' => Order::query()->where('payment_status', PaymentStatus::Paid->value)->count(),
            // Only settled money counts as sales.
            'totalSalesMinor' => (int) Order::query()
                ->where('payment_status', PaymentStatus::Paid->value)
                ->sum('grand_total_minor'),

            'recentOrders' => Order::query()->latest('id')->limit(8)->get(),

            // Eager-loaded: shouldBeStrict() turns a lazy load in the view into
            // an exception, and it would be an N+1 in production either way.
            'lowStock' => ProductVariant::query()
                ->with('product')
                ->where('stock_qty', '<=', Setting::getInt('low_stock_threshold', 5))
                ->orderBy('stock_qty')
                ->limit(8)
                ->get(),
        ]);
    }
}
