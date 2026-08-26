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
     * Orders that do not count as business. Defined once, on the enum
     * (OrderStatus::countsAsSale), so the dashboard and any future report
     * cannot drift apart on what "a sale" means.
     *
     * `needs_review` DOES count: money was taken, it just cannot be fulfilled.
     */
    private static function excludedFromSales(): array
    {
        return array_map(
            fn (OrderStatus $s): string => $s->value,
            array_filter(OrderStatus::cases(), fn (OrderStatus $s): bool => ! $s->countsAsSale())
        );
    }

    public function index(): View
    {
        $now = now();
        $totalSalesMinor = $this->salesMinor();
        $ordersCount = Order::query()->count();
        $paidOrdersCount = Order::query()->where('payment_status', PaymentStatus::Paid->value)->count();
        $soldOrdersCount = $this->soldOrdersCount();

        return view('admin.dashboard', [
            'totalSalesMinor' => $totalSalesMinor,
            'totalCollectionMinor' => $this->collectionMinor(),
            'ordersCount' => $ordersCount,

            // Average order value across orders that actually count as sales.
            // Null rather than zero when there are none — an average of nothing
            // is not RM 0, it is undefined.
            'avgOrderValueMinor' => $soldOrdersCount > 0
                ? intdiv($totalSalesMinor, $soldOrdersCount)
                : null,

            // How many placed orders end up paid. The closest thing this store
            // has to a funnel metric: the rest are abandoned at the gateway.
            'paymentConversion' => $ordersCount > 0
                ? ($paidOrdersCount / $ordersCount) * 100
                : null,
            'paidOrdersCount' => $paidOrdersCount,
            'soldOrdersCount' => $soldOrdersCount,

            // Money placed but never collected — invisible in Total Sales,
            // which excludes it by definition.
            'awaitingPaymentMinor' => (int) Order::query()
                ->where('payment_status', PaymentStatus::Pending->value)
                ->sum('grand_total_minor'),

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
            ->whereNotIn('order_status', self::excludedFromSales())
            ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->sum('grand_total_minor');
    }

    /** How many orders count as sales — the denominator for average order value. */
    private function soldOrdersCount(): int
    {
        return Order::query()
            ->whereNotIn('order_status', self::excludedFromSales())
            ->count();
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
