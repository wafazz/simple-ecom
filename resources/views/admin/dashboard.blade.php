@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Owner / HQ Dashboard')

@php
    $symbol = config('shop.currency_symbol');
@endphp

@section('content')

    {{-- Headline figures. Two large tiles carry the money that matters most. --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-6">
            <div class="stat-tile stat-tile--lg stat-tile--sales">
                <p class="stat-tile__value">{{ \App\Support\Money::displayWhole($totalSalesMinor, $symbol) }}</p>
                <p class="stat-tile__label">Total Sales</p>
                <p class="stat-tile__note">(excl. pending &amp; cancelled)</p>
                <i class="stat-tile__icon bi bi-graph-up-arrow" aria-hidden="true"></i>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="stat-tile stat-tile--lg stat-tile--collection">
                <p class="stat-tile__value">{{ \App\Support\Money::displayWhole($totalCollectionMinor, $symbol) }}</p>
                <p class="stat-tile__label">Total Collection</p>
                {{-- This store takes ToyyibPay only; there is no COD to add in. --}}
                <p class="stat-tile__note">(payments received)</p>
                <i class="stat-tile__icon bi bi-cash-stack" aria-hidden="true"></i>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-tile stat-tile--orders">
                <p class="stat-tile__value">{{ number_format($ordersCount) }}</p>
                <p class="stat-tile__label">Orders</p>
                <i class="stat-tile__icon bi bi-cart3" aria-hidden="true"></i>
            </div>
        </div>

        <div class="col-12 col-md-4">
            @if ($avgOrderValueMinor !== null)
                <div class="stat-tile stat-tile--aov">
                    <p class="stat-tile__value">{{ \App\Support\Money::displayGrouped($avgOrderValueMinor, $symbol) }}</p>
                    <p class="stat-tile__label">Average Order Value</p>
                    <p class="stat-tile__note">across {{ number_format($soldOrdersCount) }} sold orders</p>
                    <i class="stat-tile__icon bi bi-receipt-cutoff" aria-hidden="true"></i>
                </div>
            @else
                <div class="stat-tile stat-tile--untracked">
                    <p class="stat-tile__value">No sales yet</p>
                    <p class="stat-tile__label">Average Order Value</p>
                    <i class="stat-tile__icon bi bi-receipt-cutoff" aria-hidden="true"></i>
                </div>
            @endif
        </div>

        <div class="col-12 col-md-4">
            @if ($paymentConversion !== null)
                <div class="stat-tile stat-tile--conversion">
                    <p class="stat-tile__value">{{ number_format($paymentConversion, 1) }}%</p>
                    <p class="stat-tile__label">Payment Conversion</p>
                    <p class="stat-tile__note">{{ number_format($paidOrdersCount) }} of {{ number_format($ordersCount) }} orders paid</p>
                    <i class="stat-tile__icon bi bi-bullseye" aria-hidden="true"></i>
                </div>
            @else
                <div class="stat-tile stat-tile--untracked">
                    <p class="stat-tile__value">No orders yet</p>
                    <p class="stat-tile__label">Payment Conversion</p>
                    <i class="stat-tile__icon bi bi-bullseye" aria-hidden="true"></i>
                </div>
            @endif
        </div>
    </div>

    {{-- Period comparisons. --}}
    <div class="row g-3 mb-4">
        @foreach ($comparisons as $period)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card compare-card">
                    <div class="card-body">
                        <p class="compare-card__title">{{ $period['label'] }}</p>

                        <div class="compare-row">
                            <span class="compare-row__label">Sales</span>
                            <span class="compare-row__value">
                                {{ \App\Support\Money::displayGrouped($period['sales_minor'], $symbol) }}
                            </span>
                            <x-delta :change="$period['sales_change']" />
                        </div>

                        <div class="compare-row compare-row--collection">
                            <span class="compare-row__label">Collection</span>
                            <span class="compare-row__value">
                                {{ \App\Support\Money::displayGrouped($period['collection_minor'], $symbol) }}
                            </span>
                            <x-delta :change="$period['collection_change']" />
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title h6 mb-0">Recent orders</h2>
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-secondary">All orders</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th class="money">Total</th>
                                <th>Payment</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $order) }}"
                                           class="text-decoration-none">{{ $order->order_no }}</a>
                                    </td>
                                    <td class="text-truncate" style="max-width: 12rem">{{ $order->customer_name }}</td>
                                    <td class="money"><x-money :minor="$order->grand_total_minor" /></td>
                                    <td><x-status-badge :status="$order->payment_status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No orders yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h6 mb-0">Needs attention</h2>
                </div>
                <div class="card-body">
                    @if ($needsReviewCount > 0)
                        <div class="callout callout-warning mb-3">
                            <strong>{{ $needsReviewCount }}</strong>
                            order{{ $needsReviewCount === 1 ? '' : 's' }} paid but not stockable.
                            <a href="{{ route('admin.orders.index', ['order_status' => 'needs_review']) }}">Resolve</a>
                        </div>
                    @endif

                    @if ($pendingOrders > 0)
                        <div class="callout callout-info mb-3">
                            <strong>{{ number_format($pendingOrders) }}</strong>
                            order{{ $pendingOrders === 1 ? '' : 's' }} awaiting payment, worth
                            <strong>{{ \App\Support\Money::displayGrouped($awaitingPaymentMinor, $symbol) }}</strong>.
                            {{-- Deliberately absent from Total Sales, which excludes
                                 unpaid orders — so it is shown here instead. --}}
                            <a href="{{ route('admin.orders.index', ['payment_status' => 'pending']) }}">View</a>
                        </div>
                    @endif

                    @if ($lowStock->isNotEmpty())
                        <p class="text-muted small mb-2">Low or out of stock</p>
                        <ul class="list-group list-group-flush">
                            @foreach ($lowStock as $variant)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span>
                                        {{ $variant->product->name }}
                                        @if ($variant->variationLabel() !== '')
                                            <span class="text-muted small">{{ $variant->variationLabel() }}</span>
                                        @endif
                                        <br><code class="small">{{ $variant->sku }}</code>
                                    </span>
                                    <span class="badge text-bg-{{ $variant->stock_qty === 0 ? 'danger' : 'warning' }}">
                                        {{ $variant->stock_qty }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($needsReviewCount === 0 && $pendingOrders === 0)
                        <p class="text-muted mb-0">Nothing needs attention.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
