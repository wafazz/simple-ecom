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
                            <label for="flat_shipping_fee" class="form-label">Legacy flat fee ({{ config('shop.currency_symbol') }})</label>
                            <input type="text" inputmode="decimal" name="flat_shipping_fee" id="flat_shipping_fee" required
                                   value="{{ old('flat_shipping_fee', \App\Support\Money::format((int) ($settings['flat_shipping_fee_minor'] ?? 1000))) }}"
                                   class="form-control @error('flat_shipping_fee') is-invalid @enderror">
                            <div class="form-text">
                                Not charged any more — delivery is priced from the weight table below.
                                Kept only so older orders still read correctly.
                            </div>
                            @error('flat_shipping_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h2 class="card-title h6 mb-0">Delivery charges by weight</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            What the <strong>customer</strong> pays. Part kilos round up, the way a
                            courier bills — a 1.2&nbsp;kg parcel is charged as 2&nbsp;kg. This is
                            separate from what EasyParcel charges you when the shipment is booked;
                            the difference shows on each order.
                        </p>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Zone</th>
                                    <th style="width: 12rem">1st kilo ({{ config('shop.currency_symbol') }})</th>
                                    <th style="width: 12rem">Each next kilo ({{ config('shop.currency_symbol') }})</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ([
                                    ['west', 'West Malaysia', 'Peninsular — every state except the three below.'],
                                    ['east', 'East Malaysia', 'Sabah, Sarawak and W.P. Labuan.'],
                                ] as [$zone, $label, $note])
                                    <tr>
                                        <td>
                                            <strong>{{ $label }}</strong>
                                            <div class="text-muted small">{{ $note }}</div>
                                        </td>
                                        @foreach (['first', 'next'] as $part)
                                            @php $field = 'ship_'.$zone.'_'.$part; @endphp
                                            <td>
                                                <input type="text" inputmode="decimal" required
                                                       name="{{ $field }}" id="{{ $field }}"
                                                       aria-label="{{ $label }} {{ $part === 'first' ? 'first kilo' : 'each next kilo' }}"
                                                       value="{{ old($field, \App\Support\Money::format((int) ($settings[$field.'_minor'] ?? \App\Support\ShippingRate::{$part === 'first' ? 'firstKiloMinor' : 'nextKiloMinor'}($zone)))) }}"
                                                       class="form-control @error($field) is-invalid @enderror">
                                                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="text-muted small mb-0 mt-3">
                            Example at the West figures above: 0.4&nbsp;kg and 1.0&nbsp;kg both pay the
                            first-kilo price; 1.1&nbsp;kg pays first&nbsp;+&nbsp;one add-on; 2.3&nbsp;kg pays
                            first&nbsp;+&nbsp;two.
                        </p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Sender details &amp; parcel defaults</div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <p class="text-muted small mb-0">
                                Courier <em>quotes</em> need only a postcode and state. <strong>Booking a
                                shipment</strong> needs the full sender address and a parcel size — without
                                them a booking cannot be assembled at all.
                            </p>
                        </div>

                        @foreach ([
                            ['pickup_name', 'Sender name', 'text', 'col-md-6'],
                            ['pickup_company', 'Company (optional)', 'text', 'col-md-6'],
                            ['pickup_phone', 'Sender phone', 'text', 'col-md-4'],
                            ['pickup_phone_country_code', 'Phone country code', 'text', 'col-md-2'],
                            ['pickup_email', 'Sender email', 'email', 'col-md-6'],
                            ['pickup_address_1', 'Address line 1', 'text', 'col-md-6'],
                            ['pickup_address_2', 'Address line 2 (optional)', 'text', 'col-md-6'],
                            ['pickup_city', 'City', 'text', 'col-md-6'],
                        ] as [$field, $label, $type, $cols])
                            <div class="{{ $cols }}">
                                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                <input type="{{ $type }}" name="{{ $field }}" id="{{ $field }}"
                                       value="{{ old($field, $settings[$field] ?? config('shop.'.$field)) }}"
                                       class="form-control @error($field) is-invalid @enderror">
                                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach

                        <div class="col-12"><hr class="my-1"></div>

                        @foreach ([
                            ['default_length_mm', 'Default length (mm)'],
                            ['default_width_mm', 'Default width (mm)'],
                            ['default_height_mm', 'Default height (mm)'],
                        ] as [$field, $label])
                            <div class="col-md-3">
                                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                <input type="number" min="1" name="{{ $field }}" id="{{ $field }}" required
                                       value="{{ old($field, $settings[$field] ?? config('shop.'.$field)) }}"
                                       class="form-control @error($field) is-invalid @enderror">
                                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach

                        <div class="col-md-3">
                            <label for="collection_lead_days" class="form-label">Collection in (days)</label>
                            <input type="number" min="0" max="30" name="collection_lead_days"
                                   id="collection_lead_days" required
                                   value="{{ old('collection_lead_days', $settings['collection_lead_days'] ?? config('shop.collection_lead_days')) }}"
                                   class="form-control @error('collection_lead_days') is-invalid @enderror">
                            <div class="form-text">1 = ask the courier to collect tomorrow.</div>
                            @error('collection_lead_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
