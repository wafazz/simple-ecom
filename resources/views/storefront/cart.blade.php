@extends('layouts.storefront')
@section('title', 'Cart')

@section('content')
    <h1 class="h4 mb-3">Your Cart</h1>

    @if ($lines->isEmpty())
        <p class="text-muted">Your cart is empty.</p>
        <a href="{{ route('products.index') }}" class="btn btn-shop">Browse products</a>
    @else
        @if ($lines->contains('reduced', true))
            <div class="alert alert-warning">
                Some quantities were reduced because stock changed since you added them.
            </div>
        @endif

        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Item</th>
                        <th class="money">Price</th>
                        <th style="width: 11rem">Qty</th>
                        <th class="money">Total</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>
                                <a href="{{ route('products.show', $line->variant->product) }}"
                                   class="text-decoration-none">{{ $line->variant->product->name }}</a>
                                @if ($line->variant->variationLabel() !== '')
                                    <div class="text-muted small">{{ $line->variant->variationLabel() }}</div>
                                @endif
                                <div class="text-muted small"><code>{{ $line->variant->sku }}</code></div>
                            </td>
                            <td class="money"><x-money :minor="$line->unit_price_minor" /></td>
                            <td>
                                <form method="POST" action="{{ route('cart.update', $line->variant->id) }}"
                                      class="d-flex gap-1">
                                    @csrf @method('PATCH')
                                    <input type="number" name="qty" min="0" max="{{ $line->stock_qty }}"
                                           value="{{ $line->qty }}" class="form-control form-control-sm">
                                    <button class="btn btn-sm btn-outline-secondary">Update</button>
                                </form>
                            </td>
                            <td class="money"><x-money :minor="$line->line_total_minor" /></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('cart.destroy', $line->variant->id) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Subtotal</th>
                        <td class="money fw-semibold"><x-money :minor="$subtotalMinor" /></td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between flex-wrap gap-2">
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Continue shopping</a>
            <a href="{{ route('checkout.create') }}" class="btn btn-shop">Checkout</a>
        </div>

        <p class="text-muted small mt-3 mb-0">Shipping is calculated at checkout.</p>
    @endif
@endsection
