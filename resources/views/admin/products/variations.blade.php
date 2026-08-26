@extends('layouts.admin')
@section('title', 'Variations')
@section('heading', $product->name.' — Variations')

@section('content')
    @if ($variants->isEmpty())
        <div class="alert alert-warning">
            This product has no variations yet, so it cannot be sold. Add at least one.
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

    <div class="card">
        <div class="card-body">
            <h2 class="h6 mb-3">Add a variation</h2>
            <form method="POST" action="{{ route('admin.products.variations.store', $product) }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label for="sku" class="form-label">SKU</label>
                    <input type="text" name="sku" id="sku" required value="{{ old('sku') }}"
                           class="form-control @error('sku') is-invalid @enderror">
                    @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label for="price" class="form-label">Price ({{ config('shop.currency_symbol') }})</label>
                    <input type="text" inputmode="decimal" name="price" id="price" required
                           value="{{ old('price') }}" placeholder="30.00"
                           class="form-control @error('price') is-invalid @enderror">
                    @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label for="stock_qty" class="form-label">Stock</label>
                    <input type="number" name="stock_qty" id="stock_qty" min="0" required
                           value="{{ old('stock_qty', 0) }}" class="form-control @error('stock_qty') is-invalid @enderror">
                    @error('stock_qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label for="weight_g" class="form-label">Weight (g)</label>
                    <input type="number" name="weight_g" id="weight_g" min="0" required
                           value="{{ old('weight_g', config('shop.default_weight_g')) }}"
                           class="form-control @error('weight_g') is-invalid @enderror">
                    @error('weight_g') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        @foreach (\App\Enums\VariantStatus::cases() as $case)
                            <option value="{{ $case->value }}" @selected(old('status') === $case->value)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-3">
                    <label for="option1_name" class="form-label">Option 1 name</label>
                    <input type="text" name="option1_name" id="option1_name" placeholder="Size"
                           value="{{ old('option1_name') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="option1_value" class="form-label">Option 1 value</label>
                    <input type="text" name="option1_value" id="option1_value" placeholder="M"
                           value="{{ old('option1_value') }}"
                           class="form-control @error('option1_value') is-invalid @enderror">
                    @error('option1_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="option2_name" class="form-label">Option 2 name</label>
                    <input type="text" name="option2_name" id="option2_name" placeholder="Color"
                           value="{{ old('option2_name') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="option2_value" class="form-label">Option 2 value</label>
                    <input type="text" name="option2_value" id="option2_value" placeholder="Black"
                           value="{{ old('option2_value') }}" class="form-control">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-shop">Add variation</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-link">Back to products</a>
                </div>
            </form>
            <p class="text-muted small mt-3 mb-0">
                Leave both option fields blank for a product with no options. Two axes maximum.
            </p>
        </div>
    </div>
@endsection
