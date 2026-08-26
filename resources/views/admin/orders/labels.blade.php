@extends('layouts.admin')
@section('title', 'Print AWB')
@section('heading', 'Airway bills')

@section('content')
    <p class="text-muted">
        Labels are PDFs hosted by EasyParcel, so this page links to them rather than
        embedding them — a bundled preview here would print blank. Open them all,
        then print from your PDF viewer.
    </p>

    <div class="mb-3 d-print-none">
        <button type="button" class="btn btn-shop" data-open-all>
            <i class="bi bi-box-arrow-up-right me-1"></i>Open all {{ $orders->count() }} label(s)
        </button>
        <a href="{{ route('admin.orders.index', ['order_status' => 'processing']) }}"
           class="btn btn-secondary">Back to orders</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Courier</th>
                    <th>AWB</th>
                    <th class="d-print-none"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-decoration-none">
                                {{ $order->order_no }}
                            </a>
                        </td>
                        <td>
                            {{ $order->customer_name }}
                            <div class="text-muted small">{{ $order->city }}, {{ $order->postcode }}</div>
                        </td>
                        <td>{{ $order->shipment->courier_name ?? '—' }}</td>
                        <td><code>{{ $order->shipment->awb_no }}</code></td>
                        <td class="text-end d-print-none">
                            @if ($order->hasLabel())
                                <a href="{{ $order->shipment->label_url }}" target="_blank"
                                   rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"
                                   data-label-url>
                                    <i class="bi bi-printer me-1"></i>Label
                                </a>
                            @else
                                {{-- Documented behaviour: awb_url is null in
                                     EasyParcel's own success example. The label
                                     appears once the courier issues it. --}}
                                <span class="text-muted small">Label not issued yet</span>
                            @endif
                            @if ($order->shipment->tracking_url)
                                <a href="{{ $order->shipment->tracking_url }}" target="_blank"
                                   rel="noopener noreferrer" class="btn btn-sm btn-link">Track</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
/* Opens each label in its own tab. Browsers block a burst of popups unless the
   click is what triggered them, so they are opened synchronously from the
   handler rather than on a timer. */
document.querySelector('[data-open-all]')?.addEventListener('click', function () {
    document.querySelectorAll('[data-label-url]').forEach(function (a) {
        window.open(a.href, '_blank', 'noopener');
    });
});
</script>
@endpush
