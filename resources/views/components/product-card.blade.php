@props(['product'])

{{-- One card, used by the home page and the listing. Kept in one place so the
     two can never drift apart. --}}
@php
    $cover = $product->coverUrl();
    // stock_total is only loaded on screens that asked for it; a missing value
    // must not be rendered as "sold out".
    $stock = $product->stock_total ?? null;
@endphp

<article class="product-card">
    <a class="product-card__media" href="{{ route('products.show', $product) }}"
       aria-label="{{ $product->name }}">
        @if ($cover)
            <img src="{{ $cover }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <span class="product-card__placeholder"><i class="bi bi-image" aria-hidden="true"></i></span>
        @endif

        @if ($stock !== null && $stock < 1)
            <span class="product-flag product-flag--out">Sold out</span>
        @elseif ($stock !== null && $stock <= (int) config('shop.low_stock_threshold', 5))
            <span class="product-flag">Low stock</span>
        @endif
    </a>

    <div class="product-card__body">
        <h3 class="product-card__title">
            <a href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
        </h3>

        @if ($product->relationLoaded('category') && $product->category)
            <p class="product-card__cat">{{ $product->category->name }}</p>
        @endif

        <p class="product-card__price mb-0">
            {{-- Cheapest sellable variant. Price lives on the variant. --}}
            <span class="from">from</span><x-money :minor="$product->min_price_minor" />
        </p>
    </div>
</article>
