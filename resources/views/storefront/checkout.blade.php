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
                                <td class="money"><x-money :minor="$subtotalMinor" /></td>
                            </tr>
                            <tr>
                                <th class="text-end">Shipping</th>
                                <td class="money"><x-money :minor="$shippingFeeMinor" /></td>
                            </tr>
                            <tr>
                                <th class="text-end">Total</th>
                                <td class="money fw-semibold"><x-money :minor="$grandTotalMinor" /></td>
                            </tr>
                            </tfoot>
                        </table>

                        <button type="submit" class="btn btn-shop w-100">Place order</button>

                        <p class="text-muted small mt-3 mb-0">
                            Courier rates are quoted at this step from Phase 8; a flat rate applies for now.
                            Payment is added in Phase 7.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
