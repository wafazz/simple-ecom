@extends('layouts.admin')
@section('title', $product->exists ? 'Edit Product' : 'New Product')
@section('heading', $product->exists ? 'Edit Product' : 'New Product')

@php
    $rows = old('variants', $variantRows);
    $type = old('product_type', $productType);
    $symbol = config('shop.currency_symbol');
@endphp

@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">Product</h2></div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" name="name" id="name" required
                               value="{{ old('name', $product->name) }}"
                               class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="category_id" class="form-label">Category</label>
                        <select name="category_id" id="category_id" required
                                class="form-select @error('category_id') is-invalid @enderror">
                            <option value="">Choose…</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    @selected(old('category_id', $product->category_id) == $category->id)>
                                    {{ $category->name }}@if (! $category->is_active) (inactive)@endif
                                </option>
                            @endforeach
                        </select>
                        @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="slug" class="form-label">Slug <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $product->slug) }}"
                               class="form-control @error('slug') is-invalid @enderror">
                        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" rows="4"
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">Image &amp; status</h2></div>
                <div class="card-body">
                    <label for="image" class="form-label">Image <span class="text-muted small">(jpg/png/webp, max 2 MB)</span></label>
                    <input type="file" name="image" id="image" accept="image/*"
                           class="form-control @error('image') is-invalid @enderror">
                    @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror

                    @if ($product->image_path)
                        <img src="{{ asset('uploads/'.$product->image_path) }}" alt=""
                             class="img-thumbnail mt-2" style="max-width: 8rem">
                    @endif

                    <div class="form-check mt-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
                               @checked(old('is_active', $product->is_active ?? true))>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="card-title h6 mb-0">Variations</h2>
            <div class="btn-group btn-group-sm" role="group" aria-label="Product type">
                <input type="radio" class="btn-check" name="product_type" id="type_simple" value="simple"
                       @checked($type === 'simple')>
                <label class="btn btn-outline-secondary" for="type_simple">Simple</label>

                <input type="radio" class="btn-check" name="product_type" id="type_variable" value="variable"
                       @checked($type === 'variable')>
                <label class="btn btn-outline-secondary" for="type_variable">Has variations</label>
            </div>
        </div>

        <div class="card-body">
            <p class="text-muted small">
                Price and stock live on the variation, never on the product. A <strong>simple</strong>
                product is one variation with no options; choose <strong>has variations</strong> for
                size and colour combinations.
            </p>

            @error('variants') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

            <div class="table-responsive">
                <table class="table align-middle mb-0" id="variant-table">
                    <thead>
                    <tr>
                        <th style="min-width: 9rem">SKU</th>
                        <th class="opt-col" style="min-width: 12rem">Option 1</th>
                        <th class="opt-col" style="min-width: 12rem">Option 2</th>
                        <th style="min-width: 7rem">Price ({{ $symbol }})</th>
                        <th style="min-width: 6rem">Stock</th>
                        <th style="min-width: 7rem">Weight (g)</th>
                        <th style="min-width: 7rem">Status</th>
                        <th class="opt-col"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $i => $row)
                        <tr>
                            <td>
                                <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                                <input type="text" name="variants[{{ $i }}][sku]" required
                                       value="{{ $row['sku'] ?? '' }}"
                                       class="form-control form-control-sm @error("variants.$i.sku") is-invalid @enderror">
                                @error("variants.$i.sku") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </td>
                            <td class="opt-col">
                                <div class="d-flex gap-1">
                                    <input type="text" name="variants[{{ $i }}][option1_name]" placeholder="Size"
                                           value="{{ $row['option1_name'] ?? '' }}" class="form-control form-control-sm">
                                    <input type="text" name="variants[{{ $i }}][option1_value]" placeholder="M"
                                           value="{{ $row['option1_value'] ?? '' }}"
                                           class="form-control form-control-sm @error("variants.$i.option1_value") is-invalid @enderror">
                                </div>
                                @error("variants.$i.option1_value") <div class="text-danger small">{{ $message }}</div> @enderror
                            </td>
                            <td class="opt-col">
                                <div class="d-flex gap-1">
                                    <input type="text" name="variants[{{ $i }}][option2_name]" placeholder="Color"
                                           value="{{ $row['option2_name'] ?? '' }}" class="form-control form-control-sm">
                                    <input type="text" name="variants[{{ $i }}][option2_value]" placeholder="Black"
                                           value="{{ $row['option2_value'] ?? '' }}" class="form-control form-control-sm">
                                </div>
                            </td>
                            <td>
                                <input type="text" inputmode="decimal" name="variants[{{ $i }}][price]" required
                                       value="{{ $row['price'] ?? '' }}" placeholder="30.00"
                                       class="form-control form-control-sm @error("variants.$i.price") is-invalid @enderror">
                                @error("variants.$i.price") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                <input type="number" min="0" name="variants[{{ $i }}][stock_qty]" required
                                       value="{{ $row['stock_qty'] ?? 0 }}" class="form-control form-control-sm">
                            </td>
                            <td>
                                <input type="number" min="0" name="variants[{{ $i }}][weight_g]" required
                                       value="{{ $row['weight_g'] ?? config('shop.default_weight_g') }}"
                                       class="form-control form-control-sm">
                            </td>
                            <td>
                                <select name="variants[{{ $i }}][status]" class="form-select form-select-sm">
                                    @foreach (\App\Enums\VariantStatus::cases() as $case)
                                        <option value="{{ $case->value }}"
                                            @selected(($row['status'] ?? 'active') === $case->value)>{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="opt-col text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-variant"
                                        title="Remove this variation">&times;</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary mt-3 opt-col" id="add-variant">
                <i class="bi bi-plus-lg"></i> Add variation
            </button>

            <p class="text-muted small mt-3 mb-0 opt-col">
                Two option axes maximum. Removing a variation that has already been ordered
                deactivates it instead of deleting it, so order history stays intact.
            </p>
        </div>
    </div>

    <button type="submit" class="btn btn-shop">{{ $product->exists ? 'Save product' : 'Create product' }}</button>
    <a href="{{ route('admin.products.index') }}" class="btn btn-link">Cancel</a>
</form>
@endsection

@push('scripts')
<script>
// Repeatable variation rows. No jQuery: this app ships none.
(function () {
    var table = document.getElementById('variant-table');
    if (!table) return;

    var body = table.querySelector('tbody');
    var addBtn = document.getElementById('add-variant');
    var simple = document.getElementById('type_simple');
    var variable = document.getElementById('type_variable');

    // Rows are named by index. Renumbering after a removal keeps the array
    // contiguous so validation error keys line up with the visible rows.
    function renumber() {
        Array.prototype.forEach.call(body.rows, function (row, i) {
            Array.prototype.forEach.call(row.querySelectorAll('[name^="variants["]'), function (input) {
                input.name = input.name.replace(/variants\[\d*\]/, 'variants[' + i + ']');
            });
        });
    }

    function applyType() {
        var isSimple = simple.checked;
        // A simple product is exactly one variation with no options, so the
        // option columns and the add button are hidden rather than merely
        // ignored — the server enforces the same rule.
        table.classList.toggle('hide-options', isSimple);
        addBtn.hidden = isSimple;

        document.querySelectorAll('.opt-col').forEach(function (el) { el.hidden = isSimple; });

        if (isSimple) {
            while (body.rows.length > 1) { body.deleteRow(body.rows.length - 1); }
            var first = body.rows[0];
            if (first) {
                first.querySelectorAll('[name*="option"]').forEach(function (i) { i.value = ''; });
            }
            renumber();
        }
    }

    addBtn.addEventListener('click', function () {
        var template = body.rows[0];
        if (!template) return;

        var clone = template.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'hidden') { input.value = ''; }        // new row: no id
            else if (input.name.indexOf('[stock_qty]') !== -1) { input.value = '0'; }
            else if (input.name.indexOf('[weight_g]') === -1) { input.value = ''; }
        });
        clone.querySelectorAll('.invalid-feedback, .text-danger').forEach(function (e) { e.remove(); });
        clone.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });

        body.appendChild(clone);
        renumber();
    });

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-variant');
        if (!btn) return;

        if (body.rows.length === 1) {
            // Every product needs at least one variation or it cannot be sold.
            return;
        }

        btn.closest('tr').remove();
        renumber();
    });

    simple.addEventListener('change', applyType);
    variable.addEventListener('change', applyType);
    applyType();
})();
</script>
@endpush
