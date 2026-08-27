@extends('layouts.admin')
@section('title', $order->order_no)
@section('heading', 'Order '.$order->order_no)

@section('content')
    @if ($order->order_status === \App\Enums\OrderStatus::NeedsReview)
        <div class="alert alert-warning">
            <strong>Needs review.</strong> Payment was received but stock could not be allocated for
            at least one line. Restock and fulfil, or refund the customer.
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">Items</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-end">Qty</th>
                            <th class="money">Unit</th>
                            <th class="money">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    {{ $item->product_name }}
                                    @if ($item->variation_label !== '')
                                        <div class="text-muted small">{{ $item->variation_label }}</div>
                                    @endif
                                </td>
                                <td class="text-muted"><code>{{ $item->sku }}</code></td>
                                <td class="text-end">{{ $item->qty }}</td>
                                <td class="money"><x-money :minor="$item->unit_price_minor" /></td>
                                <td class="money"><x-money :minor="$item->line_total_minor" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr><th colspan="4" class="text-end">Subtotal</th><td class="money"><x-money :minor="$order->subtotal_minor" /></td></tr>
                        <tr><th colspan="4" class="text-end">Shipping</th><td class="money"><x-money :minor="$order->shipping_fee_minor" /></td></tr>
                        <tr><th colspan="4" class="text-end">Total</th><td class="money fw-semibold"><x-money :minor="$order->grand_total_minor" /></td></tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer text-muted small">
                    Prices are a snapshot taken at purchase time — later catalogue edits do not change them.
                </div>
            </div>

            <div class="card">
                <div class="card-header">Payment</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><th style="width: 12rem">Status</th><td><x-status-badge :status="$order->payment_status" /></td></tr>
                        <tr><th>Provider</th><td>{{ $order->payment?->provider ?? '—' }}</td></tr>
                        <tr><th>Bill code</th><td><code>{{ $order->payment?->bill_code ?? '—' }}</code></td></tr>
                        <tr><th>Gateway reference</th><td><code>{{ $order->payment?->provider_ref ?? '—' }}</code></td></tr>
                        <tr><th>Paid at</th><td>{{ $order->payment?->paid_at?->toDayDateTimeString() ?? '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Fulfilment</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mb-3">
                        @csrf @method('PATCH')
                        <label for="order_status" class="form-label">Order status</label>
                        <div class="d-flex gap-1">
                            <select name="order_status" id="order_status" class="form-select form-select-sm">
                                @if (! in_array($order->order_status, \App\Enums\OrderStatus::selectable(), true))
                                    {{-- The order is in a system-set state. Show it as the
                                         current selection, otherwise saving the form would
                                         silently reassign it to the first option. --}}
                                    <option value="{{ $order->order_status->value }}" selected>
                                        {{ $order->order_status->label() }} (current)
                                    </option>
                                @endif
                                @foreach (\App\Enums\OrderStatus::selectable() as $case)
                                    <option value="{{ $case->value }}" @selected($order->order_status === $case)>
                                        {{ $case->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-shop">Save</button>
                        </div>
                    </form>

                    @if ($order->payment_status === \App\Enums\PaymentStatus::Paid)
                        <form method="POST" action="{{ route('admin.orders.refund', $order) }}"
                              onsubmit="return confirm('Mark this order refunded? Issue the actual refund in the ToyyibPay dashboard.');">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-danger w-100">Mark as refunded</button>
                        </form>
                        <p class="text-muted small mt-2 mb-0">
                            Records the refund only — no money moves from here.
                        </p>
                    @endif

                    <p class="text-muted small mt-3 mb-0">
                        Payment status is set by server-side verification, not by hand — the one
                        exception is recording a refund.
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Customer</div>
                <div class="card-body">
                    <p class="mb-1">{{ $order->customer_name }}</p>
                    <p class="mb-1 text-muted small">{{ $order->customer_email }}</p>
                    <p class="mb-0 text-muted small">{{ $order->customer_phone }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Shipping</div>
                <div class="card-body">
                    <address class="mb-3">
                        {{ $order->address_line }}<br>
                        {{ $order->postcode }} {{ $order->city }}<br>
                        {{ config('shop.states')[$order->state] ?? $order->state }}, {{ $order->country }}
                    </address>

                    <table class="table table-sm mb-0">
                        <tbody>
                        {{-- orders.courier_name records what the CUSTOMER was
                             quoted, which since REQ-006 is the weight-table
                             label ("Standard Delivery") — not a carrier. The
                             real courier only exists once a shipment is booked,
                             so the two are shown as the separate things they
                             are rather than one misleading row. --}}
                        <tr>
                            <th style="width: 9rem">Delivery charged</th>
                            <td>{{ $order->courier_name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Courier service</th>
                            <td>
                                @if ($order->shipment)
                                    {{ $order->shipment->courierLabel() }}
                                @else
                                    <span class="text-muted">Not booked yet</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Rate source</th>
                            <td>
                                @if ($order->shipping_rate_source === 'flat')
                                    <span class="badge text-bg-secondary">flat rate</span>
                                    <div class="text-muted small">Live rates were unavailable when this order was placed.</div>
                                @else
                                    <span class="badge text-bg-success">quoted</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>AWB</th><td><code>{{ $order->shipment?->awb_no ?? '—' }}</code></td></tr>
                        @if ($order->shipment?->cost_minor)
                            <tr>
                                <th>Courier charged</th>
                                <td>
                                    {{ $currencySymbol }}{{ \App\Support\Money::display($order->shipment->cost_minor) }}
                                    @if ($order->shipment->cost_minor > $order->shipping_fee_minor)
                                        <span class="badge text-bg-warning ms-1">above what the customer paid</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    </table>

                    @php($shipment = $order->shipment)

                    @if ($shipment?->status === \App\Enums\ShipmentStatus::NeedsReconciliation)
                        <div class="alert alert-danger small mt-2 mb-0">
                            <strong>Outcome unknown.</strong> The courier may already have been
                            paid for this parcel. Check the EasyParcel dashboard before doing
                            anything else — this will not retry on its own.
                        </div>
                    @elseif ($shipment && ! $shipment->status->isRetryable())
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <span class="badge text-bg-success">{{ $shipment->status->label() }}</span>
                            @if ($shipment->tracking_url)
                                <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer"
                                   class="small">Track parcel</a>
                            @endif
                            @if ($shipment->labelUrl())
                                <a href="{{ $shipment->labelUrl() }}" target="_blank" rel="noopener noreferrer"
                                   class="small">Print AWB label</a>
                            @elseif ($order->hasAwb())
                                <a href="{{ route('admin.orders.awb', ['order_ids' => [$order->id]]) }}"
                                   class="small">AWB details</a>
                            @endif
                        </div>
                    @else
                        @php($missing = \App\Support\ShipmentPayload::missingFor($order))

                        @if ($shipment?->status === \App\Enums\ShipmentStatus::Failed)
                            <div class="alert alert-warning small mt-2">
                                Last attempt failed: {{ $shipment->raw_response['error'] ?? 'no reason recorded' }}
                            </div>
                        @endif

                        @if ($order->payment_status !== \App\Enums\PaymentStatus::Paid)
                            <p class="text-muted small mt-2 mb-0">
                                Booking becomes available once payment is confirmed.
                            </p>
                        @elseif ($missing)
                            {{-- Named up front. Discovering these as a courier
                                 rejection means the request has already gone out. --}}
                            <div class="alert alert-secondary small mt-2 mb-0">
                                <strong>Not ready to book.</strong> Still missing:
                                <ul class="mb-0 mt-1">
                                    @foreach ($missing as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            {{-- A link to the quote-and-confirm screen, not a
                                 form that charges. The courier service is chosen
                                 there: what the customer paid is a weight-table
                                 figure, not a courier product, so the order
                                 cannot supply one. --}}
                            @if ($easyparcelConnected)
                                <a href="{{ route('admin.orders.book', ['order_ids' => [$order->id]]) }}"
                                   class="btn btn-shop btn-sm mt-2">
                                    <i class="bi bi-truck me-1"></i>Book courier…
                                </a>
                                <div class="form-text">
                                    Shows the courier quotes for this address first. Nothing is
                                    charged until you confirm there.
                                </div>
                            @else
                                {{-- Disabled, not hidden: a missing button reads as a
                                     broken page, a disabled one with a reason reads as a
                                     decision. <a> ignores the disabled attribute, so this
                                     is a real <button> and cannot be clicked or tabbed
                                     into. --}}
                                <button type="button" class="btn btn-shop btn-sm mt-2" disabled
                                        aria-describedby="ep-offline">
                                    <i class="bi bi-truck me-1"></i>Book courier…
                                </button>
                                <div class="form-text" id="ep-offline">
                                    EasyParcel is not connected, so nothing can be booked
                                    through it. Record the AWB by hand below, or
                                    <a href="{{ route('admin.integrations.index') }}">connect
                                    EasyParcel</a>.
                                </div>
                            @endif
                        @endif
                    @endif

                    @if ($order->canEnterAwbManually())
                        @php($manual = \App\Enums\Courier::tryFromLabel($shipment?->courier_name))

                        <hr class="my-3">

                        <form method="POST" enctype="multipart/form-data"
                              action="{{ route('admin.orders.awb.store', $order) }}">
                            @csrf

                            <div class="d-flex align-items-baseline justify-content-between mb-2">
                                <strong class="small">
                                    {{ $shipment?->isManual() ? 'Update the AWB' : 'Enter AWB manually' }}
                                </strong>
                                <span class="text-muted small">Books nothing — records what you already did</span>
                            </div>

                            <div class="mb-2">
                                <label for="courier" class="form-label small mb-1">Courier</label>
                                <select name="courier" id="courier" required
                                        class="form-select form-select-sm @error('courier') is-invalid @enderror">
                                    <option value="">Choose a courier…</option>
                                    @foreach ($couriers as $value => $label)
                                        <option value="{{ $value }}"
                                                @selected(old('courier', $manual?->value) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('courier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-2">
                                <label for="awb_no" class="form-label small mb-1">AWB number</label>
                                <input type="text" name="awb_no" id="awb_no" required maxlength="64"
                                       value="{{ old('awb_no', $shipment?->isManual() ? $shipment->awb_no : '') }}"
                                       placeholder="e.g. NVMY000123456789"
                                       class="form-control form-control-sm @error('awb_no') is-invalid @enderror">
                                @error('awb_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-2">
                                <label for="awb_file" class="form-label small mb-1">
                                    AWB label <span class="text-muted fw-normal">(optional)</span>
                                </label>
                                <input type="file" name="awb_file" id="awb_file"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       class="form-control form-control-sm @error('awb_file') is-invalid @enderror">
                                @error('awb_file')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                <div class="form-text">
                                    PDF, JPG or PNG, up to 8 MB.
                                    @if (filled($shipment?->label_path))
                                        A label is already stored —
                                        <a href="{{ route('admin.orders.awb.label', $order) }}"
                                           target="_blank" rel="noopener noreferrer">view it</a>.
                                        Uploading another replaces it.
                                    @endif
                                </div>
                            </div>

                            <button type="submit" class="btn btn-shop btn-sm">
                                <i class="bi bi-upc-scan me-1"></i>Save AWB
                            </button>
                            <div class="form-text">
                                The order status is not changed — move it to In Delivery yourself
                                when the parcel is collected.
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.orders.index') }}" class="btn btn-link mt-3">Back to orders</a>
@endsection
