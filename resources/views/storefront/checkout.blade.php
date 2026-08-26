@extends('layouts.storefront')
@section('title', 'Checkout')

@section('content')
    <h1 class="h4 mb-3">Checkout</h1>

    <form method="POST" action="{{ route('checkout.store') }}">
        @csrf
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Your details</h2>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="customer_name" class="form-label">Full name</label>
                                <input type="text" name="customer_name" id="customer_name" required
                                       value="{{ old('customer_name') }}"
                                       class="form-control @error('customer_name') is-invalid @enderror">
                                @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="customer_email" class="form-label">Email</label>
                                <input type="email" name="customer_email" id="customer_email" required
                                       value="{{ old('customer_email') }}"
                                       class="form-control @error('customer_email') is-invalid @enderror">
                                @error('customer_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">You will need this to track your order.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="customer_phone" class="form-label">Phone</label>
                                <input type="text" name="customer_phone" id="customer_phone" required
                                       value="{{ old('customer_phone') }}"
                                       class="form-control @error('customer_phone') is-invalid @enderror">
                                @error('customer_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Shipping address</h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="address_line" class="form-label">Address</label>
                                <input type="text" name="address_line" id="address_line" required
                                       value="{{ old('address_line') }}"
                                       class="form-control @error('address_line') is-invalid @enderror">
                                @error('address_line') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" name="city" id="city" required
                                       value="{{ old('city') }}"
                                       class="form-control @error('city') is-invalid @enderror">
                                @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="state" class="form-label">State</label>
                                <select name="state" id="state" required
                                        class="form-select @error('state') is-invalid @enderror">
                                    <option value="">Choose…</option>
                                    @foreach (config('shop.states') as $code => $name)
                                        <option value="{{ $code }}" @selected(old('state') === $code)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="postcode" class="form-label">Postcode</label>
                                <input type="text" name="postcode" id="postcode" required inputmode="numeric"
                                       value="{{ old('postcode') }}" placeholder="50000"
                                       class="form-control @error('postcode') is-invalid @enderror">
                                @error('postcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" name="country" id="country" value="MY" readonly
                                       class="form-control-plaintext">
                                <input type="hidden" name="country" value="MY">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Order summary</h2>
                        <table class="table table-sm align-middle">
                            <tbody>
                            @foreach ($lines as $line)
                                <tr>
                                    <td>
                                        {{ $line->variant->product->name }}
                                        @if ($line->variant->variationLabel() !== '')
                                            <span class="text-muted small">({{ $line->variant->variationLabel() }})</span>
                                        @endif
                                        <span class="text-muted small">× {{ $line->qty }}</span>
                                    </td>
                                    <td class="money"><x-money :minor="$line->line_total_minor" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr>
                                <th class="text-end">Subtotal</th>
                                <td class="money" data-subtotal="{{ $subtotalMinor }}"><x-money :minor="$subtotalMinor" /></td>
                            </tr>
                            <tr>
                                <th class="text-end">Shipping</th>
                                <td class="money" id="shipping-total"><x-money :minor="$fallbackQuote->priceMinor" /></td>
                            </tr>
                            <tr>
                                <th class="text-end">Total</th>
                                <td class="money fw-semibold" id="grand-total">
                                    <x-money :minor="$subtotalMinor + $fallbackQuote->priceMinor" />
                                </td>
                            </tr>
                            </tfoot>
                        </table>

                        <hr>

                        <h3 class="h6">Delivery</h3>
                        <button type="button" id="get-rates" class="btn btn-outline-secondary btn-sm mb-2">
                            Get courier rates
                        </button>
                        <p class="text-muted small" id="rates-hint">
                            Enter your postcode and state, then check rates.
                        </p>

                        <div id="rate-options">
                            {{-- Always one selectable option so an order can be
                                 placed even when the rate API is unreachable. --}}
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shipping_service_id"
                                       id="rate-flat" value="{{ $fallbackQuote->serviceId }}"
                                       data-price="{{ $fallbackQuote->priceMinor }}" checked>
                                <label class="form-check-label" for="rate-flat">
                                    {{ $fallbackQuote->label() }} —
                                    <x-money :minor="$fallbackQuote->priceMinor" />
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-shop w-100">Place order &amp; pay</button>

                        <p class="text-muted small mt-3 mb-0">
                            You will be taken to ToyyibPay to complete payment.
                            Courier rates are quoted at this step from Phase 8; a flat rate applies for now.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
// Fetches courier rates for the entered address. The chosen service_id is an
// identifier only — the server re-quotes and prices it again on submit, so
// nothing here can influence what is charged.
(function () {
    var btn = document.getElementById('get-rates');
    if (!btn) return;

    var options = document.getElementById('rate-options');
    var hint = document.getElementById('rates-hint');
    var subtotal = parseInt(document.querySelector('[data-subtotal]').dataset.subtotal, 10);

    function money(minor) {
        var sign = minor < 0 ? '-' : '';
        minor = Math.abs(minor);
        return sign + '{{ config('shop.currency_symbol') }}' +
            Math.floor(minor / 100) + '.' + String(minor % 100).padStart(2, '0');
    }

    function recalc() {
        var picked = options.querySelector('input[name="shipping_service_id"]:checked');
        var ship = picked ? parseInt(picked.dataset.price, 10) : 0;
        document.getElementById('shipping-total').textContent = money(ship);
        document.getElementById('grand-total').textContent = money(subtotal + ship);
    }

    options.addEventListener('change', recalc);

    btn.addEventListener('click', function () {
        var postcode = document.getElementById('postcode').value;
        var state = document.getElementById('state').value;

        if (!postcode || !state) {
            hint.textContent = 'Enter your postcode and state first.';
            return;
        }

        btn.disabled = true;
        hint.textContent = 'Checking rates…';

        fetch('{{ route('shipping.quote') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ postcode: postcode, state: state })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var quotes = data.quotes || [];
            if (!quotes.length) { hint.textContent = 'No rates available; a flat rate applies.'; return; }

            options.innerHTML = '';
            quotes.forEach(function (q, i) {
                var id = 'rate-' + i;
                var div = document.createElement('div');
                div.className = 'form-check';
                div.innerHTML = '<input class="form-check-input" type="radio" name="shipping_service_id"' +
                    ' id="' + id + '" value="' + q.service_id + '" data-price="' + q.price_minor + '"' +
                    (i === 0 ? ' checked' : '') + '>' +
                    '<label class="form-check-label" for="' + id + '">' +
                    q.label + ' — ' + q.price +
                    (q.delivery_duration ? ' <span class="text-muted small">(' + q.delivery_duration + ')</span>' : '') +
                    (q.is_flat ? ' <span class="badge text-bg-secondary">flat rate</span>' : '') +
                    '</label>';
                options.appendChild(div);
            });

            hint.textContent = quotes[0].is_flat
                ? 'Live rates unavailable — a flat rate applies.'
                : 'Choose a delivery option.';
            recalc();
        })
        .catch(function () { hint.textContent = 'Could not fetch rates; a flat rate applies.'; })
        .finally(function () { btn.disabled = false; });
    });
})();
</script>
@endpush
