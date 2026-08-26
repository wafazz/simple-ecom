@extends('layouts.storefront')
@section('title', 'Payment Result')

@php
    $paid = $order->payment_status === \App\Enums\PaymentStatus::Paid;
    $failed = $order->payment_status === \App\Enums\PaymentStatus::Failed;
@endphp

@section('content')
    <div class="text-center py-4">
        @if ($paid)
            <h1 class="h4 text-success">Payment received</h1>
            <p class="text-muted">Thank you. Your order is being processed.</p>
        @elseif ($failed)
            <h1 class="h4 text-danger">Payment was not completed</h1>
            <p class="text-muted">Nothing has been charged. You can try again below.</p>
        @else
            {{-- Includes the deliberate `unverified` case: we do not claim a
                 payment succeeded on evidence we could not read (§11.A.6). --}}
            <h1 class="h4">Payment is still being confirmed</h1>
            <p class="text-muted">
                We have not received confirmation yet. If money has left your account,
                it will be matched to this order shortly — please do not pay twice.
            </p>
        @endif

        <p class="text-muted small mb-1">Order number</p>
        <p class="h5">{{ $order->order_no }}</p>
        <p><x-status-badge :status="$order->order_status" /> <x-status-badge :status="$order->payment_status" /></p>
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
                <tr><th class="text-end">Total</th><td class="money fw-semibold"><x-money :minor="$order->grand_total_minor" /></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2 flex-wrap">
        <a href="{{ route('order-status.show') }}" class="btn btn-outline-secondary">Track this order</a>
        @if (! $paid)
            <a href="{{ route('payment.pay', $order->order_no) }}" class="btn btn-shop">Try payment again</a>
        @endif
    </div>
@endsection
