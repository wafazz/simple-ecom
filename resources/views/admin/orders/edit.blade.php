@extends('layouts.admin')
@section('title', 'Edit '.$order->order_no)
@section('heading', 'Edit delivery details — '.$order->order_no)

@section('content')
    <div class="alert alert-warning">
        <strong>Delivery and contact details only.</strong>
        The items, the prices and the shipping fee are not editable — this order has been
        settled, and changing what it is worth would leave it disagreeing with the amount
        the customer actually paid.
        <br>
        Changing the postcode or state does <strong>not</strong> re-quote the shipping fee.
        {{ $order->customer_name }} was charged
        {{ $currencySymbol }}{{ \App\Support\Money::format($order->shipping_fee_minor) }}
        for delivery and that figure stays as it is.
    </div>

    <form method="POST" action="{{ route('admin.orders.details', $order) }}">
        @csrf
        @method('PATCH')

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">Customer</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Name</label>
                            <input type="text" name="customer_name" id="customer_name" maxlength="255"
                                   value="{{ old('customer_name', $order->customer_name) }}"
                                   class="form-control @error('customer_name') is-invalid @enderror">
                            @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="customer_email" class="form-label">Email</label>
                            <input type="email" name="customer_email" id="customer_email" maxlength="255"
                                   value="{{ old('customer_email', $order->customer_email) }}"
                                   class="form-control @error('customer_email') is-invalid @enderror">
                            @error('customer_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Where any future email about this order goes. The confirmation
                                already sent is not resent.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="customer_phone" class="form-label">Phone</label>
                            <input type="text" name="customer_phone" id="customer_phone" maxlength="32"
                                   value="{{ old('customer_phone', $order->customer_phone) }}"
                                   class="form-control @error('customer_phone') is-invalid @enderror">
                            @error('customer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">The courier calls this number on delivery.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">Delivery address</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="address_line" class="form-label">Address</label>
                            <input type="text" name="address_line" id="address_line" maxlength="255"
                                   value="{{ old('address_line', $order->address_line) }}"
                                   class="form-control @error('address_line') is-invalid @enderror">
                            @error('address_line')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-5">
                                <label for="postcode" class="form-label">Postcode</label>
                                <input type="text" name="postcode" id="postcode" maxlength="5" inputmode="numeric"
                                       value="{{ old('postcode', $order->postcode) }}"
                                       class="form-control @error('postcode') is-invalid @enderror">
                                @error('postcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-7">
                                <label for="city" class="form-label">City</label>
                                <input type="text" name="city" id="city" maxlength="100"
                                       value="{{ old('city', $order->city) }}"
                                       class="form-control @error('city') is-invalid @enderror">
                                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="state" class="form-label">State</label>
                            {{-- The stored value is the ISO code, which is what a later
                                 EasyParcel booking reads. The name is only the label. --}}
                            <select name="state" id="state"
                                    class="form-select @error('state') is-invalid @enderror">
                                @foreach ($states as $code => $name)
                                    <option value="{{ $code }}"
                                            @selected(old('state', $order->state) === $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save details</button>
            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
