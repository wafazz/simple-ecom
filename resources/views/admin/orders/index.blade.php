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
                {{-- Every case, including needs_review: an admin must be able to
                     FILTER for it even though they cannot assign it. --}}
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

    @php
        // How many rows on THIS page can actually move. Nothing is offered for
        // an order that would only be refused.
        $movable = $orders->filter(fn ($o) => $o->order_status->canStartProcessing());
    @endphp

    {{-- Standalone so the table is not wrapped in a form: each row carries its
         own single-order form, and nesting those inside a bulk form would be
         invalid HTML. The checkboxes reach this one by its id instead. --}}
    <form method="POST" action="{{ route('admin.orders.process') }}" id="bulk-process">
        @csrf @method('PATCH')
    </form>

    @if ($movable->isNotEmpty())
        <div class="d-flex align-items-center gap-2 mb-2" data-bulk-bar hidden>
            <span class="text-muted small"><strong data-bulk-count>0</strong> selected</span>
            <button type="submit" form="bulk-process" class="btn btn-sm btn-shop">
                <i class="bi bi-arrow-right-circle me-1"></i>Move to Processing
            </button>
            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bulk-clear>
                Clear
            </button>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-order-table>
                <thead>
                <tr>
                    <th style="width: 2.5rem">
                        @if ($movable->isNotEmpty())
                            <input type="checkbox" class="form-check-input" data-select-all
                                   aria-label="Select all new orders on this page">
                        @endif
                    </th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th class="text-end">Items</th>
                    <th class="money">Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Placed</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    @php $canProcess = $order->order_status->canStartProcessing(); @endphp
                    <tr>
                        <td>
                            @if ($canProcess)
                                {{-- Associated to the bulk form by id, so the
                                     table itself stays outside any form. --}}
                                <input type="checkbox" class="form-check-input" form="bulk-process"
                                       name="order_ids[]" value="{{ $order->id }}" data-row-check
                                       aria-label="Select order {{ $order->order_no }}">
                            @endif
                        </td>
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
                        <td class="text-end">
                            @if ($canProcess)
                                {{-- Its own form, so this button moves exactly
                                     this order regardless of what is ticked. --}}
                                <form method="POST" action="{{ route('admin.orders.process') }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="order_ids[]" value="{{ $order->id }}">
                                    <button class="btn btn-sm btn-outline-primary text-nowrap">
                                        Move to Processing
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted text-center py-4">No orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
