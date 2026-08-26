@extends('layouts.admin')
@section('title', 'Book courier')
@section('heading', 'Book courier')

@section('content')
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>This spends your EasyParcel credit.</strong>
        One charge per parcel, taken the moment you confirm. A booking cannot be
        undone from here — cancel it in the EasyParcel dashboard.
    </div>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Deliver to</th>
                    <th class="text-end">Weight</th>
                    <th class="money">Customer paid</th>
                    <th class="money">Courier quote</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    @php
                        $order = $row['order'];
                        $ready = $row['missing'] === [] && $row['quotes'] !== [];
                        $cheapest = $row['quotes'][0] ?? null;
                    @endphp
                    <tr class="{{ $ready ? '' : 'table-secondary' }}">
                        <td>
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-decoration-none">
                                {{ $order->order_no }}
                            </a>
                            <div class="text-muted small">{{ $order->customer_name }}</div>
                        </td>
                        <td class="small">
                            {{ $order->city }}, {{ $order->postcode }}
                            <div class="text-muted">{{ config('shop.states')[$order->state] ?? $order->state }}</div>
                        </td>
                        <td class="text-end">
                            {{ \App\Support\Dimensions::gToKg($row['weight_g']) }} kg
                        </td>
                        <td class="money"><x-money :minor="$order->shipping_fee_minor" /></td>
                        <td class="money">
                            @if ($row['missing'] !== [])
                                {{-- Named before anything is charged. Finding
                                     this out as a courier rejection means the
                                     money-spending call already happened. --}}
                                <span class="text-danger small">
                                    Not ready — {{ implode('; ', $row['missing']) }}
                                </span>
                            @elseif ($cheapest === null)
                                <span class="text-danger small">No courier quoted this address.</span>
                            @else
                                <x-money :minor="$cheapest->priceMinor" />
                                @if ($cheapest->priceMinor > $order->shipping_fee_minor)
                                    <div class="text-warning small">
                                        above what the customer paid
                                    </div>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($bookable->isEmpty())
        <div class="alert alert-danger">
            Nothing here can be booked yet. Fix what is listed above and try again.
        </div>
        <a href="{{ route('admin.orders.index', ['order_status' => 'processing']) }}"
           class="btn btn-secondary">Back to orders</a>
    @elseif ($services === [])
        {{-- Offering a service that suits three of five would fail on the other
             two AFTER the first three had already been charged. --}}
        <div class="alert alert-danger">
            <strong>No single courier service covers every parcel here.</strong>
            The destinations differ enough that no one service was quoted for all
            of them. Book them in smaller groups, or one at a time.
        </div>
        <a href="{{ route('admin.orders.index', ['order_status' => 'processing']) }}"
           class="btn btn-secondary">Back to orders</a>
    @else
        <form method="POST" action="{{ route('admin.orders.book.store') }}" class="card">
            @csrf
            @foreach ($bookable as $row)
                <input type="hidden" name="order_ids[]" value="{{ $row['order']->id }}">
            @endforeach

            <div class="card-body">
                <label for="service_id" class="form-label">Courier service</label>
                <select name="service_id" id="service_id" class="form-select" required>
                    @foreach ($services as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text">
                    Only services quoted for <strong>every</strong> parcel in this batch are listed.
                </div>

                <button type="submit" class="btn btn-shop mt-3">
                    <i class="bi bi-truck me-1"></i>
                    Book {{ $bookable->count() }} shipment{{ $bookable->count() === 1 ? '' : 's' }} now
                </button>
                <a href="{{ route('admin.orders.index', ['order_status' => 'processing']) }}"
                   class="btn btn-link">Cancel</a>
            </div>
        </form>
    @endif
@endsection
