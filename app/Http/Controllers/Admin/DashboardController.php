<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\View\View;

/** REQ-007 / REQ-009 — owner dashboard. */
class DashboardController extends Controller
{
    /**
     * Orders that do not count as business: never paid for, or cancelled.
     * Everything else is a real sale, including `needs_review` — money was
     * taken there, it just cannot be fulfilled yet.
     */
    private const EXCLUDED_FROM_SALES = [
        OrderStatus::PendingPayment,
        OrderStatus::Cancelled,
    ];

    public function index(): View
    {
        $now = now();
        $adsCostMinor = Setting::getInt('ads_cost_minor', 0);
        $totalSalesMinor = $this->salesMinor();

        return view('admin.dashboard', [
            'totalSalesMinor' => $totalSalesMinor,
            'totalCollectionMinor' => $this->collectionMinor(),
            'ordersCount' => Order::query()->count(),

            // No advertising spend exists in this system's data — this is a
            // figure the admin maintains in Settings. Zero means "not tracked",
            // and the tiles say so rather than showing a fabricated number.
            'adsCostMinor' => $adsCostMinor,
            'roas' => $adsCostMinor > 0 ? $totalSalesMinor / $adsCostMinor : null,

            'comparisons' => [
                $this->comparison('Today vs Yesterday',
                    $now->copy()->startOfDay(), $now,
                    $now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()),

                $this->comparison('This Week vs Last Week',
                    $now->copy()->startOfWeek(), $now,
                    $now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()),

                $this->comparison('This Month vs Last Month',
                    $now->copy()->startOfMonth(), $now,
                    $now->copy()->subMonthNoOverflow()->startOfMonth(),
                    $now->copy()->subMonthNoOverflow()->endOfMonth()),

                $this->comparison('This Year vs Last Year',
                    $now->copy()->startOfYear(), $now,
                    $now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()),
            ],

            'pendingOrders' => Order::query()
                ->where('payment_status', PaymentStatus::Pending->value)->count(),
            'recentOrders' => Order::query()->latest('id')->limit(8)->get(),
            'lowStock' => ProductVariant::query()
                ->with('product')
                ->where('stock_qty', '<=', Setting::getInt('low_stock_threshold', 5))
                ->orderBy('stock_qty')
                ->limit(6)
                ->get(),
        ]);
    }

    /**
     * One window's sales and collection, plus the same figures for the window
     * before it and the change between them.
     *
     * @return array<string, mixed>
     */
    private function comparison(
        string $label,
        CarbonInterface $from,
        CarbonInterface $to,
        CarbonInterface $prevFrom,
        CarbonInterface $prevTo,
    ): array {
        $sales = $this->salesMinor($from, $to);
        $collection = $this->collectionMinor($from, $to);
        $prevSales = $this->salesMinor($prevFrom, $prevTo);
        $prevCollection = $this->collectionMinor($prevFrom, $prevTo);

        return [
            'label' => $label,
            'sales_minor' => $sales,
            'sales_change' => Money::percentChange($prevSales, $sales),
            'collection_minor' => $collection,
            'collection_change' => Money::percentChange($prevCollection, $collection),
        ];
    }

    /** Booked business: everything except never-paid and cancelled orders. */
    private function salesMinor(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        return (int) Order::query()
            ->whereNotIn('order_status', array_map(
                fn (OrderStatus $s): string => $s->value,
                self::EXCLUDED_FROM_SALES
            ))
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->sum('grand_total_minor');
    }

    /** Money actually received — settled payments only. */
    private function collectionMinor(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        return (int) Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->sum('grand_total_minor');
    }
}
