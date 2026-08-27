@extends('layouts.storefront')
@section('title', 'Checkout')

@section('content')
    <ol class="steps">
        <li class="is-done"><a href="{{ route('cart.index') }}" class="text-decoration-none">Cart</a></li>
        <li class="is-current">Details</li>
        <li>Payment</li>
    </ol>

    <h1 class="h3 mb-4">Checkout</h1>

    <form method="POST" action="{{ route('checkout.store') }}">
        @csrf
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="panel mb-3">
                    <div class="panel__head">Your details</div>
                    <div class="panel__body">
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

                <div class="panel">
                    <div class="panel__head">Shipping address</div>
                    <div class="panel__body">
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
                <div class="panel summary">
                    <div class="panel__head">Order summary</div>
                    <div class="panel__body">
                        <table class="table table-sm align-middle">
                            <tbody>
                            @foreach ($lines as $line)
                                <tr>
                                    <td>
                                        {{ $line->variant->product->name }}
                                        @if ($line->variant->variationLabel() !== '')
                                            <span class="text-muted small">({{ $line->variant->variationLabel() }})</span>
                                        @endif
                                        @if ($line->nameset)
                                            <div class="text-muted small">
                                                Nameset: {{ trim($line->nameset['name'].' '.$line->nameset['number']) }}
                                            </div>
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
                                <th class="text-end">
                                    Delivery
                                    <div class="text-muted small fw-normal" id="ship-basis">
                                        {{ $billableKilos }} kg · choose a state
                                    </div>
                                </th>
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
                        <p class="text-muted small mb-3" id="ship-note">
                            Charged by parcel weight. Pick your state above and the
                            figure updates.
                        </p>

                        {{-- No courier choice and no service id is posted. The
                             charge is derived on the server from the submitted
                             state and the cart's own weight, so there is nothing
                             here for a customer to influence (§17). --}}

                        <button type="submit" class="btn btn-shop btn-lg w-100">Place order &amp; pay</button>

                        <p class="text-muted small mt-3 mb-0">
                            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                            You will be taken to ToyyibPay to complete payment. Every
                            price, including delivery, is recalculated on the server when
                            you place the order.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
/* Shows the delivery charge for the chosen state. The endpoint prices from the
   store's weight table, so this is a display convenience only — the same
   calculation runs again server-side from the address actually submitted, and
   THAT is what the customer is charged. */
(function () {
    var state = document.getElementById('state');
    var out = document.getElementById('shipping-total');
    var basis = document.getElementById('ship-basis');
    var note = document.getElementById('ship-note');
    var grand = document.getElementById('grand-total');
    var postcode = document.getElementById('postcode');
    if (!state || !out || !grand) return;

    var subtotal = parseInt(document.querySelector('[data-subtotal]').dataset.subtotal, 10);

    function money(minor) {
        var sign = minor < 0 ? '-' : '';
        minor = Math.abs(minor);
        return sign + '{{ config('shop.currency_symbol') }}' +
            Math.floor(minor / 100) + '.' + String(minor % 100).padStart(2, '0');
    }

    function refresh() {
        if (!state.value) return;

        fetch('{{ route('shipping.quote') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            // A postcode is required by the endpoint but does not change the
            // price; only the state decides the zone.
            body: JSON.stringify({ postcode: postcode.value || '00000', state: state.value })
        })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
            if (!data) return;

            out.textContent = money(data.price_minor);
            grand.textContent = money(subtotal + data.price_minor);
            if (basis) basis.textContent = data.kilos + ' kg · ' + data.zone;
            if (note) note.textContent = 'Charged by parcel weight to ' + data.zone + '.';
        })
        .catch(function () { /* leave the figure as it stands */ });
    }

    state.addEventListener('change', refresh);
    if (state.value) refresh();
})();
</script>
@endpush
