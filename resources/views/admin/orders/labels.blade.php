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
        <button type="button" class="btn btn-shop" data-open-all
                data-count="{{ $orders->count() }}">
            <i class="bi bi-box-arrow-up-right me-1"></i>Open all {{ $orders->count() }} label(s)
        </button>
        <button type="button" class="btn btn-quiet" data-copy-all>
            <i class="bi bi-clipboard me-1"></i>Copy all links
        </button>
        <a href="{{ route('admin.orders.index', ['order_status' => 'processing']) }}"
           class="btn btn-secondary">Back to orders</a>

        {{-- A browser opens the FIRST window.open() of a gesture and blocks the
             rest. Silently opening one label out of ten is the worst possible
             outcome, so the count is checked and said out loud. --}}
        <div class="alert alert-warning mt-3 d-none" data-blocked-notice>
            <strong>Your browser blocked <span data-blocked-count></span> of the labels.</strong>
            Allow pop-ups for this site and press the button again, or open them
            one at a time from the list below — a link you click yourself is never
            blocked. Labels you have already opened are ticked.
        </div>
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
                                <span class="text-success d-none" data-opened-tick
                                      title="Opened">&check;</span>
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
(function () {
    var button = document.querySelector('[data-open-all]');
    if (!button) return;

    var notice = document.querySelector('[data-blocked-notice]');
    var blockedOut = document.querySelector('[data-blocked-count]');

    function links() { return Array.prototype.slice.call(document.querySelectorAll('[data-label-url]')); }

    button.addEventListener('click', function () {
        var blocked = 0;

        links().forEach(function (a) {
            /* No features string. Passing one — even just 'noopener' — asks for
               a popup WINDOW rather than a tab, and popups are blocked far more
               aggressively than tabs. It also makes window.open() return null by
               spec, which would make every open look blocked. */
            var win = window.open(a.href, '_blank');

            if (win) {
                // Same protection the rel="noopener" on the link gives.
                win.opener = null;

                var tick = a.parentElement.querySelector('[data-opened-tick]');
                if (tick) tick.classList.remove('d-none');
            } else {
                blocked++;
            }
        });

        if (blocked > 0) {
            if (blockedOut) blockedOut.textContent = blocked;
            if (notice) notice.classList.remove('d-none');
        } else if (notice) {
            notice.classList.add('d-none');
        }
    });

    // Clicking a single label yourself is never blocked; tick it too so a
    // part-finished batch is easy to pick back up.
    links().forEach(function (a) {
        a.addEventListener('click', function () {
            var tick = a.parentElement.querySelector('[data-opened-tick]');
            if (tick) tick.classList.remove('d-none');
        });
    });

    // The fallback that always works, even with pop-ups fully disabled.
    var copy = document.querySelector('[data-copy-all]');
    if (copy) {
        copy.addEventListener('click', function () {
            var urls = links().map(function (a) { return a.href; }).join('\n');
            var original = copy.innerHTML;

            navigator.clipboard.writeText(urls).then(function () {
                copy.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied ' + links().length + ' link(s)';
                window.setTimeout(function () { copy.innerHTML = original; }, 2200);
            }).catch(function () {
                // No clipboard permission (or an insecure origin): show them
                // instead of failing quietly.
                window.prompt('Copy these label links:', urls);
            });
        });
    }
})();
</script>
@endpush
