@extends('layouts.storefront')
@section('title', $activeCategory?->name ?? 'Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $activeCategory?->name ?? 'All Products' }}</h1>
    </div>

    <div class="mb-4 d-flex flex-wrap gap-2">
        <a href="{{ route('products.index') }}"
           class="btn btn-sm {{ $activeCategory ? 'btn-outline-secondary' : 'btn-shop' }}">All</a>
        @foreach ($categories as $category)
            <a href="{{ route('products.index', ['category' => $category->slug]) }}"
               class="btn btn-sm {{ $activeCategory?->id === $category->id ? 'btn-shop' : 'btn-outline-secondary' }}">
                {{ $category->name }}
            </a>
        @endforeach
    </div>

    @if ($products->isEmpty())
        <p class="text-muted">No products available{{ $activeCategory ? ' in this category' : '' }}.</p>
    @else
        <div class="row g-3">
            @foreach ($products as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100">
                        @if ($product->image_path)
                            <img src="{{ asset('uploads/'.$product->image_path) }}" alt="{{ $product->name }}"
                                 class="card-img-top product-thumb">
                        @else
                            <div class="product-thumb d-flex align-items-center justify-content-center text-muted small">
                                No image
                            </div>
                        @endif
                        <div class="card-body d-flex flex-column">
                            <h2 class="h6">{{ $product->name }}</h2>
                            <p class="text-muted small mb-2">{{ $product->category->name }}</p>
                            <p class="mb-3">
                                {{-- Cheapest sellable variant. Price lives on the variant. --}}
                                <span class="text-muted small">from</span>
                                <x-money :minor="$product->min_price_minor" />
                            </p>
                            <a href="{{ route('products.show', $product) }}"
                               class="btn btn-sm btn-shop mt-auto">View</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $products->links() }}</div>
    @endif
@endsection
