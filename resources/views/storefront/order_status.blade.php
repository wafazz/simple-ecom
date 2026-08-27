@extends('layouts.storefront')

@section('title', 'Track Order')

@section('content')
    <h1 class="h4 mb-3">Track Your Order</h1>

    <div class="panel mb-4">
        <div class="panel__body">
            <form method="POST" action="{{ route('order-status.lookup') }}" class="row g-3">
                @csrf
                <div class="col-md-5">
                    <label for="order_no" class="form-label">Order number</label>
                    <input type="text" name="order_no" id="order_no" required
                           value="{{ old('order_no') }}" placeholder="ORD-20260826-0001"
                           class="form-control @error('order_no') is-invalid @enderror">
                </div>
                <div class="col-md-5">
                    <label for="email" class="form-label">Email used at checkout</label>
                    <input type="email" name="email" id="email" required
                           value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-shop w-100">Track</button>
                </div>
            </form>
        </div>
    </div>

    @if (! empty($notFound))
        <div class="alert alert-warning">
            We could not find an order with that number and email address.
        </div>
    @endif

    @if ($order)
        <div class="panel">
            <div class="panel__body">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <p class="text-muted small mb-1">Order</p>
                        <p class="h5 mb-0">{{ $order->order_no }}</p>
                    </div>
                    <div class="text-end">
                        <p class="text-muted small mb-1">Status</p>
                        <x-status-badge :status="$order->order_status" />
                        <x-status-badge :status="$order->payment_status" />
                    </div>
                </div>

                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Item</th>
                        <th>Variation</th>
                        <th class="text-end">Qty</th>
                        <th class="money">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}</td>
                            <td class="text-muted">
                                {{ $item->variation_label }}
                                @if ($item->hasNameset())
                                    <div class="small">Nameset: {{ $item->namesetLabel() }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ $item->qty }}</td>
                            <td class="money"><x-money :minor="$item->line_total_minor" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Subtotal</th>
                        <td class="money"><x-money :minor="$order->subtotal_minor" /></td>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Shipping</th>
                        <td class="money"><x-money :minor="$order->shipping_fee_minor" /></td>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Total</th>
                        <td class="money fw-semibold"><x-money :minor="$order->grand_total_minor" /></td>
                    </tr>
                    </tfoot>
                </table>

                @if ($order->shipment?->awb_no)
                    <p class="mb-0">
                        <span class="text-muted small d-block">Tracking</span>
                        {{ $order->shipment->courier_name }} — {{ $order->shipment->awb_no }}
                        @if ($order->shipment->tracking_url)
                            <a href="{{ $order->shipment->tracking_url }}" target="_blank" rel="noopener">Track parcel</a>
                        @endif
                    </p>
                @endif
            </div>
        </div>
    @endif
@endsection
