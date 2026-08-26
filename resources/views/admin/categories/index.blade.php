@extends('layouts.admin')
@section('title', 'Categories')
@section('heading', 'Categories')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.categories.create') }}" class="btn btn-shop btn-sm">Add category</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th class="text-end">Products</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td class="text-muted"><code>{{ $category->slug }}</code></td>
                        <td class="text-end">{{ $category->products_count }}</td>
                        <td>
                            <span class="badge text-bg-{{ $category->is_active ? 'success' : 'secondary' }}">
                                {{ $category->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('admin.categories.toggle', $category) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-{{ $category->is_active ? 'warning' : 'success' }}">
                                    {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center py-4">No categories yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $categories->links() }}</div>

    <p class="text-muted small mt-3 mb-0">
        Categories are deactivated, never deleted — deleting would orphan order history.
    </p>
@endsection
