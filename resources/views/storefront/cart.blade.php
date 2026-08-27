@extends('layouts.storefront')
@section('title', 'Cart')

@section('content')
    <ol class="steps">
        <li class="is-current">Cart</li>
        <li>Details</li>
        <li>Payment</li>
    </ol>

    <h1 class="h3 mb-4">Your cart</h1>

    @if ($lines->isEmpty())
        <div class="empty-state">
            <i class="bi bi-bag" aria-hidden="true"></i>
            <p class="mb-1">Your cart is empty.</p>
            <p class="small mb-3">Anything you add is held for this browser session.</p>
            <a href="{{ route('products.index') }}" class="btn btn-shop">Browse products</a>
        </div>
    @else
        @if ($lines->contains('reduced', true))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                Some quantities were reduced because stock changed since you added them.
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="panel">
                    @foreach ($lines as $line)
                        @php $cover = $line->variant->product->coverUrl(); @endphp
                        <div class="d-flex gap-3 p-3 {{ $loop->last ? '' : 'border-bottom' }}">
                            @if ($cover)
                                <img src="{{ $cover }}" alt="" class="line-thumb">
                            @else
                                <span class="line-thumb d-flex align-items-center justify-content-center text-muted">
                                    <i class="bi bi-image" aria-hidden="true"></i>
                                </span>
                            @endif

                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('products.show', $line->variant->product) }}"
                                   class="fw-semibold text-decoration-none d-block">
                                    {{ $line->variant->product->name }}
                                </a>
                                @if ($line->variant->variationLabel() !== '')
                                    <div class="text-muted small">{{ $line->variant->variationLabel() }}</div>
                                @endif
                                @if ($line->nameset)
                                    <div class="small">
                                        <span class="badge text-bg-light">Nameset</span>
                                        {{ trim($line->nameset['name'].' '.$line->nameset['number']) }}
                                        @if ($line->nameset_price_minor > 0)
                                            <span class="text-muted">
                                                +{{ $currencySymbol }}{{ \App\Support\Money::format($line->nameset_price_minor) }} per shirt
                                            </span>
                                        @endif
                                    </div>
                                @endif
                                <div class="text-muted small"><code>{{ $line->variant->sku }}</code></div>

                                <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                    <form method="POST" action="{{ route('cart.update', $line->variant->id) }}"
                                          class="d-flex align-items-center gap-2">
                                        @csrf @method('PATCH')
                                        <div class="qty">
                                            <button type="button" data-qty-step="-1" aria-label="Decrease quantity">−</button>
                                            <input type="number" name="qty" min="0" max="{{ $line->stock_qty }}"
                                                   value="{{ $line->qty }}" aria-label="Quantity">
                                            <button type="button" data-qty-step="1" aria-label="Increase quantity">+</button>
                                        </div>
                                        <button class="btn btn-quiet btn-sm">Update</button>
                                    </form>

                                    <form method="POST" action="{{ route('cart.destroy', $line->variant->id) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger text-decoration-none px-1">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="text-end">
                                <div class="fw-semibold money"><x-money :minor="$line->line_total_minor" /></div>
                                <div class="text-muted small money">
                                    <x-money :minor="$line->unit_price_minor" /> each
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('products.index') }}" class="btn btn-quiet mt-3">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Continue shopping
                </a>
            </div>

            <div class="col-lg-4">
                <div class="panel summary">
                    <div class="panel__head">Order summary</div>
                    <div class="panel__body">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span><x-money :minor="$subtotalMinor" /></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span class="text-muted">Calculated at checkout</span>
                        </div>
                        <div class="summary-row summary-row--total">
                            <span>Total so far</span>
                            <span><x-money :minor="$subtotalMinor" /></span>
                        </div>

                        <a href="{{ route('checkout.create') }}" class="btn btn-shop w-100 mt-3">
                            Checkout
                        </a>

                        <p class="text-muted small text-center mt-3 mb-0">
                            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                            Prices and stock are re-checked when you place the order.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
