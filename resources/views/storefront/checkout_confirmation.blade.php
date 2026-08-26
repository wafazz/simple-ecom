@extends('layouts.storefront')
@section('title', 'Order Received')

@section('content')
    <div class="text-center py-4">
        <h1 class="h4">Thank you — your order has been created</h1>
        <p class="text-muted mb-1">Order number</p>
        <p class="h5">{{ $order->order_no }}</p>
        <p class="text-muted">
            Keep this number. You can check your order any time from
            <a href="{{ route('order-status.show') }}">Track Order</a> using it and your email address.
        </p>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm align-middle mb-0">
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->product_name }}
                            @if ($item->variation_label !== '')
                                <span class="text-muted small">({{ $item->variation_label }})</span>
                            @endif
                            <span class="text-muted small">× {{ $item->qty }}</span>
                        </td>
                        <td class="money"><x-money :minor="$item->line_total_minor" /></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr><th class="text-end">Subtotal</th><td class="money"><x-money :minor="$order->subtotal_minor" /></td></tr>
                <tr><th class="text-end">Shipping</th><td class="money"><x-money :minor="$order->shipping_fee_minor" /></td></tr>
                <tr><th class="text-end">Total</th><td class="money fw-semibold"><x-money :minor="$order->grand_total_minor" /></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3">
        Payment status: <x-status-badge :status="$order->payment_status" />
        — online payment is wired up in Phase 7.
    </p>
@endsection
