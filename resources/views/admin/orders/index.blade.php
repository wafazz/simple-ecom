@extends('layouts.admin')
@section('title', 'Orders')
@section('heading', 'Orders')

@section('content')
    @if ($needsReviewCount > 0)
        {{-- Money was taken but stock could not satisfy the line. A human must
             resolve these before anything else (Planning §7.5). --}}
        <div class="alert alert-warning">
            <strong>{{ $needsReviewCount }}</strong>
            order{{ $needsReviewCount === 1 ? '' : 's' }} need review — paid, but stock could not be allocated.
            <a href="{{ route('admin.orders.index', ['order_status' => 'needs_review']) }}">Show them</a>.
        </div>
    @endif

    <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="Order no, name or email" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
            <select name="order_status" class="form-select form-select-sm">
                <option value="">Any order status</option>
                {{-- Every case, including needs_review: an admin must be able to
                     FILTER for it even though they cannot assign it. --}}
                @foreach (\App\Enums\OrderStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['order_status'] ?? '') === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="payment_status" class="form-select form-select-sm">
                <option value="">Any payment status</option>
                @foreach (\App\Enums\PaymentStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['payment_status'] ?? '') === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-outline-secondary w-100">Filter</button>
        </div>
    </form>

    @php
        // What this page can actually offer. Nothing is shown for an order it
        // would only be refused on.
        $movable = $orders->filter(fn ($o) => $o->order_status->canStartProcessing());

        // Booking is only offered when EasyParcel could actually carry it out.
        // Unlike the order page, this list does not show a disabled button per
        // row — that would be one dead control per line. The reason is stated
        // once, above the table.
        $couldBook = $orders->filter(fn ($o) => $o->canBookShipment());
        $bookable = $easyparcelConnected ? $couldBook : collect();
        $printable = $orders->filter(fn ($o) => $o->hasAwb());
        $anyAction = $movable->isNotEmpty() || $bookable->isNotEmpty() || $printable->isNotEmpty();

        // Courier and AWB belong on the statuses where a parcel exists or is
        // about to. Shown on those filters even when nothing is booked yet, so
        // the gap is visible rather than the column simply being absent.
        $courierStatuses = [
            \App\Enums\OrderStatus::Processing,
            \App\Enums\OrderStatus::InDelivery,
            \App\Enums\OrderStatus::Completed,
            \App\Enums\OrderStatus::Returned,
        ];
        $filtered = ($filters['order_status'] ?? null)
            ? \App\Enums\OrderStatus::tryFrom($filters['order_status'])
            : null;
        $showsCourier = ($filtered && in_array($filtered, $courierStatuses, true))
            || $orders->contains(fn ($o) => $o->shipment !== null);
    @endphp

    @if (! $easyparcelConnected && $couldBook->isNotEmpty())
        {{-- Said once for the page rather than as a dead button on every row.
             Without this the Book courier action would simply be absent, which
             reads as a broken screen rather than a paused integration. --}}
        <div class="alert alert-secondary d-flex align-items-start gap-2 py-2 small">
            <i class="bi bi-info-circle mt-1"></i>
            <div>
                <strong>EasyParcel is not connected,</strong> so courier booking is
                unavailable — {{ $couldBook->count() }} order(s) here would otherwise be
                bookable. Open an order to record the courier and AWB by hand, or
                <a href="{{ route('admin.integrations.index') }}">connect EasyParcel</a>.
            </div>
        </div>
    @endif

    {{-- Standalone so the table is not wrapped in a form: each row carries its
         own single-order form, and nesting those inside a bulk form would be
         invalid HTML. The checkboxes reach this one by its id instead.

         One POST form for every action. A button with formmethod="get" would
         put the CSRF token in the query string, and from there into the access
         log — so the two read-only screens are reached by redirect instead. --}}
    <form method="POST" action="{{ route('admin.orders.bulk') }}" id="bulk-process">
        @csrf
    </form>

    @if ($anyAction)
        <div class="d-flex align-items-center flex-wrap gap-2 mb-2" data-bulk-bar hidden>
            <span class="text-muted small"><strong data-bulk-count>0</strong> selected</span>

            @if ($movable->isNotEmpty())
                <button type="submit" form="bulk-process" name="bulk_action" value="process"
                        class="btn btn-sm btn-shop">
                    <i class="bi bi-arrow-right-circle me-1"></i>Move to Processing
                </button>
            @endif

            @if ($bookable->isNotEmpty())
                {{-- Goes to a confirmation screen that quotes first. This
                     button charges nothing on its own. --}}
                <button type="submit" form="bulk-process" name="bulk_action" value="book"
                        class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-truck me-1"></i>Book courier…
                </button>
            @endif

            @if ($printable->isNotEmpty())
                <button type="submit" form="bulk-process" name="bulk_action" value="awb"
                        class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i>Print AWB
                </button>
            @endif

            <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bulk-clear>
                Clear
            </button>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-order-table>
                <thead>
                <tr>
                    <th style="width: 2.5rem">
                        @if ($anyAction)
                            <input type="checkbox" class="form-check-input" data-select-all
                                   aria-label="Select all orders on this page">
                        @endif
                    </th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th class="text-end">Items</th>
                    <th class="money">Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    @if ($showsCourier)
                        <th>Courier &amp; AWB</th>
                    @endif
                    <th>Placed</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($orders as $order)
                    @php
                        $canProcess = $order->order_status->canStartProcessing();
                        $canBook = $easyparcelConnected && $order->canBookShipment();
                        $canPrint = $order->hasAwb();

                        // An order still awaiting payment. Approving accepts it
                        // for fulfilment WITHOUT marking it paid — the money is
                        // the gateway's word, never the admin's.
                        $isPending = $order->order_status === \App\Enums\OrderStatus::Pending;
                        $unpaid = $order->payment_status !== \App\Enums\PaymentStatus::Paid;
                    @endphp
                    <tr>
                        <td>
                            @if ($canProcess || $canBook || $canPrint)
                                {{-- Associated to the bulk form by id, so the
                                     table itself stays outside any form. Each
                                     action decides for itself what it can use
                                     and reports whatever it skipped. --}}
                                <input type="checkbox" class="form-check-input" form="bulk-process"
                                       name="order_ids[]" value="{{ $order->id }}" data-row-check
                                       aria-label="Select order {{ $order->order_no }}">
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-decoration-none">
                                {{ $order->order_no }}
                            </a>
                        </td>
                        <td>
                            {{ $order->customer_name }}
                            <div class="text-muted small">{{ $order->customer_email }}</div>
                        </td>
                        <td class="text-end">{{ $order->items_count }}</td>
                        <td class="money"><x-money :minor="$order->grand_total_minor" /></td>
                        <td><x-status-badge :status="$order->payment_status" /></td>
                        <td><x-status-badge :status="$order->order_status" /></td>
                        @if ($showsCourier)
                            <td class="small">
                                @if ($order->shipment)
                                    {{ $order->shipment->courierLabel() }}
                                    <div class="text-muted">
                                        @if ($order->shipment->awb_no)
                                            AWB <code>{{ $order->shipment->awb_no }}</code>
                                            @if ($order->shipment->tracking_url)
                                                <a href="{{ $order->shipment->tracking_url }}" target="_blank"
                                                   rel="noopener noreferrer">track</a>
                                            @endif
                                        @else
                                            {{-- Documented: EasyParcel returns a null AWB at
                                                 submit time; the courier issues it shortly after. --}}
                                            <span class="text-warning">AWB not issued yet</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">Not booked</span>
                                @endif
                            </td>
                        @endif
                        <td class="text-muted small">{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end align-items-center">
                                @if ($isPending)
                                    {{-- Icon-only, so each carries its own label:
                                         title for a mouse, aria-label for a screen
                                         reader. An icon with neither is a button
                                         nobody can name. --}}
                                    <form method="POST" action="{{ route('admin.orders.approve', $order) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-outline-success"
                                                title="Approve order — accepts it for fulfilment. Does NOT mark it paid."
                                                aria-label="Approve order {{ $order->order_no }}">
                                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-outline-secondary"
                                                title="Cancel order"
                                                aria-label="Cancel order {{ $order->order_no }}">
                                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif

                                @if ($isPending && $unpaid)
                                    {{-- Two-step, because one stray click on an icon
                                         should not remove a row. Soft: the order and
                                         its number survive and can be restored. --}}
                                    <form method="POST" action="{{ route('admin.orders.destroy', $order) }}"
                                          data-confirm-delete>
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"
                                                title="Delete order"
                                                aria-label="Delete order {{ $order->order_no }}">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif

                                @if ($canProcess)
                                    {{-- Its own form, so this button moves exactly
                                         this order regardless of what is ticked. --}}
                                    <form method="POST" action="{{ route('admin.orders.process') }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="order_ids[]" value="{{ $order->id }}">
                                        <button class="btn btn-sm btn-outline-primary text-nowrap">
                                            Move to Processing
                                        </button>
                                    </form>
                                @endif

                                @if ($canBook)
                                    {{-- A link, not a form: this only opens the
                                         quote-and-confirm screen. --}}
                                    <a href="{{ route('admin.orders.book', ['order_ids' => [$order->id]]) }}"
                                       class="btn btn-sm btn-outline-primary text-nowrap">
                                        <i class="bi bi-truck me-1"></i>Book courier…
                                    </a>
                                @elseif ($canPrint)
                                    {{-- Already has an AWB, so booking is off
                                         the table entirely — printing is what
                                         is left to do. --}}
                                    <a href="{{ route('admin.orders.awb', ['order_ids' => [$order->id]]) }}"
                                       class="btn btn-sm btn-outline-secondary text-nowrap">
                                        <i class="bi bi-printer me-1"></i>Print AWB
                                    </a>
                                @elseif ($order->shipment)
                                    <span class="badge text-bg-warning text-nowrap">
                                        {{ $order->shipment->status->label() }}
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-muted text-center py-4">No orders found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
