@extends('layouts.admin')
@section('title', 'Orders')
@section('heading', 'Orders')

@section('content')
    @if ($needsReviewCount > 0)
        {{-- Money was taken but stock could not satisfy the line. A human must
             resolve these before anything else (Planning §7.5). --}}
        <div class="alert alert-warning">
            <strong>{{ $needsReviewCount }}</strong>
            order{{ $needsReviewCount === 1 ? '' : 's' }} need review — paid, but stock could not be allocated.
            <a href="{{ route('admin.orders.index', ['order_status' => 'needs_review']) }}">Show them</a>.
        </div>
    @endif

    <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Order no, name or email" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
            <select name="order_status" class="form-select form-select-sm">
                <option value="">Any order status</option>
                @foreach (\App\Enums\OrderStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['order_status'] ?? '') === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="payment_status" class="form-select form-select-sm">
                <option value="">Any payment status</option>
                @foreach (\App\Enums\PaymentStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['payment_status'] ?? '') === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-outline-secondary w-100">Filter</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th class="text-end">Items</th>
                    <th class="money">Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Placed</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-decoration-none">
                                {{ $order->order_no }}
                            </a>
                        </td>
                        <td>
                            {{ $order->customer_name }}
                            <div class="text-muted small">{{ $order->customer_email }}</div>
                        </td>
                        <td class="text-end">{{ $order->items_count }}</td>
                        <td class="money"><x-money :minor="$order->grand_total_minor" /></td>
                        <td><x-status-badge :status="$order->payment_status" /></td>
                        <td><x-status-badge :status="$order->order_status" /></td>
                        <td class="text-muted small">{{ $order->created_at->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted text-center py-4">No orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
