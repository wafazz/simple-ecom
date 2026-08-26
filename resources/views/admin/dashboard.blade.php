@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="row">
        <div class="col-6 col-lg-3">
            <div class="small-box text-bg-secondary">
                <div class="inner">
                    <h3>{{ number_format($totalOrders) }}</h3>
                    <p>Total orders</p>
                </div>
                <i class="small-box-icon bi bi-receipt"></i>
                <a href="{{ route('admin.orders.index') }}" class="small-box-footer">
                    View all <i class="bi bi-arrow-right-circle"></i>
                </a>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="small-box text-bg-warning">
                <div class="inner">
                    <h3>{{ number_format($pendingOrders) }}</h3>
                    <p>Awaiting payment</p>
                </div>
                <i class="small-box-icon bi bi-hourglass-split"></i>
                <a href="{{ route('admin.orders.index', ['payment_status' => 'pending']) }}" class="small-box-footer">
                    View <i class="bi bi-arrow-right-circle"></i>
                </a>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="small-box text-bg-success">
                <div class="inner">
                    <h3>{{ number_format($paidOrders) }}</h3>
                    <p>Paid orders</p>
                </div>
                <i class="small-box-icon bi bi-check2-circle"></i>
                <a href="{{ route('admin.orders.index', ['payment_status' => 'paid']) }}" class="small-box-footer">
                    View <i class="bi bi-arrow-right-circle"></i>
                </a>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="small-box text-bg-primary">
                <div class="inner">
                    {{-- Settled money only. Pending orders are not sales. --}}
                    <h3 class="money">{{ \App\Support\Money::display($totalSalesMinor, config('shop.currency_symbol')) }}</h3>
                    <p>Total sales</p>
                </div>
                <i class="small-box-icon bi bi-cash-coin"></i>
                <span class="small-box-footer">Settled payments only</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="card-title h6 mb-0">Recent orders</h2>
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
            <div class="card mb-4">
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
                    @elseif ($needsReviewCount === 0)
                        <p class="text-muted mb-0">Nothing needs attention.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
