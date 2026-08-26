@extends('layouts.admin')
@section('title', 'Settings')
@section('heading', 'Store Settings')

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf @method('PUT')

                <div class="card mb-3">
                    <div class="card-header">Store</div>
                    <div class="card-body row g-3">
                        <div class="col-md-12">
                            <label for="store_name" class="form-label">Store name</label>
                            <input type="text" name="store_name" id="store_name" required
                                   value="{{ old('store_name', $settings['store_name'] ?? config('shop.store_name')) }}"
                                   class="form-control @error('store_name') is-invalid @enderror">
                            @error('store_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="store_email" class="form-label">Store email</label>
                            <input type="email" name="store_email" id="store_email" required
                                   value="{{ old('store_email', $settings['store_email'] ?? '') }}"
                                   class="form-control @error('store_email') is-invalid @enderror">
                            @error('store_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="store_phone" class="form-label">Store phone</label>
                            <input type="text" name="store_phone" id="store_phone" required
                                   value="{{ old('store_phone', $settings['store_phone'] ?? '') }}"
                                   class="form-control @error('store_phone') is-invalid @enderror">
                            @error('store_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="currency" class="form-label">Currency</label>
                            <input type="text" name="currency" id="currency" required maxlength="3"
                                   value="{{ old('currency', $settings['currency'] ?? 'MYR') }}"
                                   class="form-control @error('currency') is-invalid @enderror">
                            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="low_stock_threshold" class="form-label">Low stock at</label>
                            <input type="number" name="low_stock_threshold" id="low_stock_threshold" min="0" required
                                   value="{{ old('low_stock_threshold', $settings['low_stock_threshold'] ?? 5) }}"
                                   class="form-control @error('low_stock_threshold') is-invalid @enderror">
                            @error('low_stock_threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Shipping origin &amp; fallback</div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <p class="text-muted small mb-0">
                                Parcels are quoted from this address. A wrong origin means a wrong rate on
                                <em>every</em> order.
                            </p>
                        </div>
                        <div class="col-md-4">
                            <label for="pickup_postcode" class="form-label">Pickup postcode</label>
                            <input type="text" name="pickup_postcode" id="pickup_postcode" required inputmode="numeric"
                                   value="{{ old('pickup_postcode', $settings['pickup_postcode'] ?? '') }}"
                                   class="form-control @error('pickup_postcode') is-invalid @enderror">
                            @error('pickup_postcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label for="pickup_state" class="form-label">Pickup state</label>
                            <select name="pickup_state" id="pickup_state" required
                                    class="form-select @error('pickup_state') is-invalid @enderror">
                                @foreach (config('shop.states') as $code => $name)
                                    <option value="{{ $code }}"
                                        @selected(old('pickup_state', $settings['pickup_state'] ?? '') === $code)>
                                        {{ $name }} ({{ $code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('pickup_state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="default_weight_g" class="form-label">Default weight (g)</label>
                            <input type="number" name="default_weight_g" id="default_weight_g" min="1" required
                                   value="{{ old('default_weight_g', $settings['default_weight_g'] ?? 500) }}"
                                   class="form-control @error('default_weight_g') is-invalid @enderror">
                            <div class="form-text">Used when a variation has no weight, so a quote is never requested at zero.</div>
                            @error('default_weight_g') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="flat_shipping_fee" class="form-label">Flat shipping fee ({{ config('shop.currency_symbol') }})</label>
                            <input type="text" inputmode="decimal" name="flat_shipping_fee" id="flat_shipping_fee" required
                                   value="{{ old('flat_shipping_fee', \App\Support\Money::format((int) ($settings['flat_shipping_fee_minor'] ?? 1000))) }}"
                                   class="form-control @error('flat_shipping_fee') is-invalid @enderror">
                            <div class="form-text">Charged when live courier rates are unavailable.</div>
                            @error('flat_shipping_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-shop">Save settings</button>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Integration credentials</div>
                <div class="card-body">
                    {{-- Status only. Credential values live in .env and are never
                         rendered into a field or sent to JavaScript (spec §16). --}}
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                        <tr>
                            <th style="width: 12rem">ToyyibPay</th>
                            <td>
                                <span class="badge text-bg-{{ $toyyibpayConfigured ? 'success' : 'secondary' }}">
                                    {{ $toyyibpayConfigured ? 'Configured' : 'Not configured' }}
                                </span>
                                @if ($sandbox)
                                    <span class="badge text-bg-warning">sandbox</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>EasyParcel</th>
                            <td>
                                <span class="badge text-bg-{{ $easyparcelConfigured ? 'success' : 'secondary' }}">
                                    {{ $easyparcelConfigured ? 'Configured' : 'Not configured' }}
                                </span>
                                <span class="badge text-bg-{{ $easyparcelConnected ? 'success' : 'warning' }}">
                                    {{ $easyparcelConnected ? 'Connected' : 'Not connected' }}
                                </span>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <p class="text-muted small mt-3 mb-0">
                        Credentials are set in <code>.env</code> on the server and are never displayed or
                        editable here. Manage the EasyParcel connection on the
                        <a href="{{ route('admin.integrations.index') }}">Integrations</a> screen.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
