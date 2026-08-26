@extends('layouts.admin')
@section('title', $product->exists ? 'Edit Product' : 'New Product')
@section('heading', $product->exists ? 'Edit Product' : 'New Product')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data"
                  action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
                @csrf
                @if ($product->exists) @method('PUT') @endif

                <div class="row g-3">
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
                        <input type="text" name="slug" id="slug"
                               value="{{ old('slug', $product->slug) }}"
                               class="form-control @error('slug') is-invalid @enderror">
                        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" rows="4"
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="image" class="form-label">Image <span class="text-muted small">(jpg/png/webp, max 2 MB)</span></label>
                        <input type="file" name="image" id="image" accept="image/*"
                               class="form-control @error('image') is-invalid @enderror">
                        @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if ($product->image_path)
                            <img src="{{ asset('uploads/'.$product->image_path) }}" alt=""
                                 class="img-thumbnail mt-2" style="max-width: 8rem">
                        @endif
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
                                   @checked(old('is_active', $product->is_active ?? true))>
                            <label for="is_active" class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>

                <hr class="my-4">
                <button type="submit" class="btn btn-shop">Save</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        Price and stock live on the variations, not on the product.
    </p>
@endsection
