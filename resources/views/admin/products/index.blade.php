@extends('layouts.admin')
@section('title', 'Products')
@section('heading', 'Products')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.products.create') }}" class="btn btn-shop btn-sm">Add product</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="text-end">Variations</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>
                            {{ $product->name }}
                            <div class="text-muted small"><code>{{ $product->slug }}</code></div>
                        </td>
                        <td>{{ $product->category->name }}</td>
                        <td class="text-end">
                            @if ($product->variants_count === 0)
                                {{-- Unbuyable: every product needs at least one variant. --}}
                                <span class="badge text-bg-danger">none</span>
                            @else
                                {{ $product->variants_count }}
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $product->is_active ? 'success' : 'secondary' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <a href="{{ route('admin.products.variations.index', $product) }}" class="btn btn-sm btn-outline-primary">Stock</a>
                            <form method="POST" action="{{ route('admin.products.toggle', $product) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-{{ $product->is_active ? 'warning' : 'success' }}">
                                    {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center py-4">No products yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>
@endsection
