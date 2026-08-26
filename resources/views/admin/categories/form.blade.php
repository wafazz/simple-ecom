@extends('layouts.admin')
@section('title', $category->exists ? 'Edit Category' : 'New Category')
@section('heading', $category->exists ? 'Edit Category' : 'New Category')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST"
                  action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
                @csrf
                @if ($category->exists) @method('PUT') @endif

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" id="name" required
                           value="{{ old('name', $category->name) }}"
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug <span class="text-muted small">(optional — generated from the name)</span></label>
                    <input type="text" name="slug" id="slug"
                           value="{{ old('slug', $category->slug) }}"
                           class="form-control @error('slug') is-invalid @enderror">
                    @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
                           @checked(old('is_active', $category->is_active ?? true))>
                    <label for="is_active" class="form-check-label">Active</label>
                </div>

                <button type="submit" class="btn btn-shop">Save</button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
