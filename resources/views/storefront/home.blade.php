@extends('layouts.storefront')

@section('title', 'Home')

@section('content')
    <div class="p-4 p-md-5 mb-4 rounded bg-body-secondary">
        <h1 class="h3">{{ $storeName }}</h1>
        <p class="text-muted">Browse the catalogue, pick your size and colour, and check out as a guest.</p>
        <a href="{{ route('products.index') }}" class="btn btn-shop">Browse products</a>
    </div>

    <h2 class="h5 mb-3">Categories</h2>

    @if ($categories->isEmpty())
        <p class="text-muted">No categories yet.</p>
    @else
        <div class="row g-3">
            @foreach ($categories as $category)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h6 mb-0">
                                <a class="stretched-link text-decoration-none"
                                   href="{{ route('products.index', ['category' => $category->slug]) }}">
                                    {{ $category->name }}
                                </a>
                            </h3>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="text-muted small mt-4 mb-0">
        Cart and checkout arrive in Phase 6.
    </p>
@endsection
