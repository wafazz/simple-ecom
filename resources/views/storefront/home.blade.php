@extends('layouts.storefront')

@section('title', 'Home')

@section('content')
    <section class="hero">
        <div class="hero__inner">
            <p class="eyebrow">{{ $storeName }}</p>
            <h1>Everyday pieces, made to be worn out.</h1>
            <p>
                A small catalogue, chosen carefully. Pick your size and colour, check out
                as a guest, and track the parcel from the same link.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('products.index') }}" class="btn btn-shop px-4 py-2">Shop everything</a>
                <a href="{{ route('order-status.show') }}" class="btn btn-quiet px-4 py-2">Track an order</a>
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="mb-5">
            <div class="section-head">
                <h2>Browse by category</h2>
                <a href="{{ route('products.index') }}" class="text-decoration-none small">All products →</a>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}"
                       class="btn btn-quiet px-3">{{ $category->name }}</a>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <div class="section-head">
            <h2>New arrivals</h2>
            <a href="{{ route('products.index', ['sort' => 'newest']) }}"
               class="text-decoration-none small">See all →</a>
        </div>

        @if ($featured->isEmpty())
            <div class="empty-state">
                <i class="bi bi-bag" aria-hidden="true"></i>
                <p class="mb-0">Nothing in the catalogue yet — please check back soon.</p>
            </div>
        @else
            <div class="row g-3 g-md-4">
                @foreach ($featured as $product)
                    <div class="col-6 col-lg-3">
                        <x-product-card :product="$product" />
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
