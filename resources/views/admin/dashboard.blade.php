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
            @if ($adsCostMinor > 0)
                <div class="stat-tile stat-tile--ads">
                    <p class="stat-tile__value">{{ \App\Support\Money::displayWhole($adsCostMinor, $symbol) }}</p>
                    <p class="stat-tile__label">Ads Cost</p>
                    <i class="stat-tile__icon bi bi-megaphone" aria-hidden="true"></i>
                </div>
            @else
                {{-- Ad spend is not in the order data. Showing RM 0 here would
                     read as "we spent nothing", which is a different claim from
                     "we do not track this". --}}
                <div class="stat-tile stat-tile--untracked">
                    <p class="stat-tile__value">Not tracked</p>
                    <p class="stat-tile__label">Ads Cost</p>
                    <p class="stat-tile__note">
                        <a href="{{ route('admin.settings.edit') }}" class="text-white text-decoration-underline">
                            Enter it in Settings
                        </a>
                    </p>
                    <i class="stat-tile__icon bi bi-megaphone" aria-hidden="true"></i>
                </div>
            @endif
        </div>

        <div class="col-12 col-md-4">
            @if ($roas !== null)
                <div class="stat-tile stat-tile--roas">
                    <p class="stat-tile__value">{{ number_format($roas, 2) }}x</p>
                    <p class="stat-tile__label">ROAS</p>
                    <p class="stat-tile__note">sales ÷ ads cost</p>
                    <i class="stat-tile__icon bi bi-bullseye" aria-hidden="true"></i>
                </div>
            @else
                <div class="stat-tile stat-tile--untracked">
                    <p class="stat-tile__value">Not tracked</p>
                    <p class="stat-tile__label">ROAS</p>
                    <p class="stat-tile__note">needs an ads cost</p>
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
                        <p class="mb-3">
                            <span class="badge text-bg-secondary">{{ $pendingOrders }}</span>
                            awaiting payment —
                            <a href="{{ route('admin.orders.index', ['payment_status' => 'pending']) }}">view</a>
                        </p>
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
