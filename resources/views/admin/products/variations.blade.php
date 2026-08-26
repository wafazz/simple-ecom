@extends('layouts.admin')
@section('title', 'Stock')
@section('heading', $product->name.' — Stock')

@section('content')
    @if ($variants->isEmpty())
        <div class="alert alert-warning">
            This product has no variations yet, so it cannot be sold.
            <a href="{{ route('admin.products.edit', $product) }}">Add one on the product form</a>.
        </div>
    @endif

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>SKU</th>
                    <th>Options</th>
                    <th class="money">Price</th>
                    <th class="text-end">Weight</th>
                    <th>Status</th>
                    <th style="width: 14rem">Stock</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($variants as $variant)
                    <tr>
                        <td><code>{{ $variant->sku }}</code></td>
                        <td>{{ $variant->variationLabel() ?: '—' }}</td>
                        <td class="money"><x-money :minor="$variant->price_minor" /></td>
                        <td class="text-end text-muted">{{ number_format($variant->weight_g) }} g</td>
                        <td>
                            <x-status-badge :status="$variant->status" />
                            @if ($variant->stock_qty === 0)
                                <span class="badge text-bg-danger">Out of stock</span>
                            @elseif ($variant->stock_qty <= $lowStockThreshold)
                                <span class="badge text-bg-warning">Low stock</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST"
                                  action="{{ route('admin.products.variations.stock', [$product, $variant]) }}"
                                  class="d-flex gap-1">
                                @csrf @method('PATCH')
                                <input type="number" name="stock_qty" min="0" required
                                       value="{{ $variant->stock_qty }}" class="form-control form-control-sm">
                                <button class="btn btn-sm btn-outline-secondary">Set</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-shop btn-sm">
            <i class="bi bi-pencil"></i> Edit product &amp; variations
        </a>
        <a href="{{ route('admin.products.index') }}" class="btn btn-link btn-sm">Back to products</a>
    </div>

    <p class="text-muted small mt-3 mb-0">
        This screen adjusts quantities only. Variations are added, changed and removed on
        the product form.
    </p>
@endsection
