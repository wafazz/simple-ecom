@extends('layouts.admin')
@section('title', $order->order_no)
@section('heading', 'Order '.$order->order_no)

@section('content')
    @if ($order->order_status === \App\Enums\OrderStatus::NeedsReview)
        <div class="alert alert-warning">
            <strong>Needs review.</strong> Payment was received but stock could not be allocated for
            at least one line. Restock and fulfil, or refund the customer.
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">Items</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-end">Qty</th>
                            <th class="money">Unit</th>
                            <th class="money">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    {{ $item->product_name }}
                                    @if ($item->variation_label !== '')
                                        <div class="text-muted small">{{ $item->variation_label }}</div>
                                    @endif
                                </td>
                                <td class="text-muted"><code>{{ $item->sku }}</code></td>
                                <td class="text-end">{{ $item->qty }}</td>
                                <td class="money"><x-money :minor="$item->unit_price_minor" /></td>
                                <td class="money"><x-money :minor="$item->line_total_minor" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr><th colspan="4" class="text-end">Subtotal</th><td class="money"><x-money :minor="$order->subtotal_minor" /></td></tr>
                        <tr><th colspan="4" class="text-end">Shipping</th><td class="money"><x-money :minor="$order->shipping_fee_minor" /></td></tr>
                        <tr><th colspan="4" class="text-end">Total</th><td class="money fw-semibold"><x-money :minor="$order->grand_total_minor" /></td></tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer text-muted small">
                    Prices are a snapshot taken at purchase time — later catalogue edits do not change them.
                </div>
            </div>

            <div class="card">
                <div class="card-header">Payment</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><th style="width: 12rem">Status</th><td><x-status-badge :status="$order->payment_status" /></td></tr>
                        <tr><th>Provider</th><td>{{ $order->payment?->provider ?? '—' }}</td></tr>
                        <tr><th>Bill code</th><td><code>{{ $order->payment?->bill_code ?? '—' }}</code></td></tr>
                        <tr><th>Gateway reference</th><td><code>{{ $order->payment?->provider_ref ?? '—' }}</code></td></tr>
                        <tr><th>Paid at</th><td>{{ $order->payment?->paid_at?->toDayDateTimeString() ?? '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Fulfilment</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mb-3">
                        @csrf @method('PATCH')
                        <label for="order_status" class="form-label">Order status</label>
                        <div class="d-flex gap-1">
                            <select name="order_status" id="order_status" class="form-select form-select-sm">
                                @foreach (\App\Enums\OrderStatus::cases() as $case)
                                    <option value="{{ $case->value }}" @selected($order->order_status === $case)>
                                        {{ $case->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-shop">Save</button>
                        </div>
                    </form>

                    @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                        <form method="POST" action="{{ route('admin.orders.refund', $order) }}"
                              onsubmit="return confirm('Mark this order refunded? Issue the actual refund in the ToyyibPay dashboard.');">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-danger w-100">Mark as refunded</button>
                        </form>
                        <p class="text-muted small mt-2 mb-0">
                            Records the refund only — no money moves from here.
                        </p>
                    @endif

                    <p class="text-muted small mt-3 mb-0">
                        Payment status is set by server-side verification, not by hand — the one
                        exception is recording a refund.
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Customer</div>
                <div class="card-body">
                    <p class="mb-1">{{ $order->customer_name }}</p>
                    <p class="mb-1 text-muted small">{{ $order->customer_email }}</p>
                    <p class="mb-0 text-muted small">{{ $order->customer_phone }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Shipping</div>
                <div class="card-body">
                    <address class="mb-3">
                        {{ $order->address_line }}<br>
                        {{ $order->postcode }} {{ $order->city }}<br>
                        {{ config('shop.states')[$order->state] ?? $order->state }}, {{ $order->country }}
                    </address>

                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><th style="width: 9rem">Courier</th><td>{{ $order->courier_name ?? '—' }}</td></tr>
                        <tr>
                            <th>Rate source</th>
                            <td>
                                @if ($order->shipping_rate_source === 'flat')
                                    <span class="badge text-bg-secondary">flat rate</span>
                                    <div class="text-muted small">Live rates were unavailable when this order was placed.</div>
                                @else
                                    <span class="badge text-bg-success">quoted</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>AWB</th><td><code>{{ $order->shipment?->awb_no ?? '—' }}</code></td></tr>
                        </tbody>
                    </table>

                    @unless ($order->shipment)
                        <p class="text-muted small mt-2 mb-0">
                            Shipment booking arrives in Phase 8b.
                        </p>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.orders.index') }}" class="btn btn-link mt-3">Back to orders</a>
@endsection
