@extends('layouts.storefront')
@section('title', $activeCategory?->name ?? 'Shop')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">
                {{ $activeCategory?->name ?? 'All products' }}
            </li>
        </ol>
    </nav>

    <div class="row g-4">
        {{-- Filters. A plain GET form: it works with its Apply button alone,
             and app.js only makes it submit on change. --}}
        <div class="col-lg-3">
            <form class="filter-rail" method="GET" action="{{ route('products.index') }}" data-filter-form>
                @if ($search !== '')
                    <input type="hidden" name="q" value="{{ $search }}">
                @endif

                <div class="filter-group">
                    <p class="filter-group__title">Category</p>
                    <a href="{{ route('products.index', array_filter(['q' => $search, 'sort' => $sort])) }}"
                       class="filter-link {{ $activeCategory ? '' : 'is-active' }}">All products</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('products.index', array_filter([
                                'category' => $category->slug, 'q' => $search, 'sort' => $sort,
                           ])) }}"
                           class="filter-link {{ $activeCategory?->id === $category->id ? 'is-active' : '' }}">
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>

                @if ($activeCategory)
                    <input type="hidden" name="category" value="{{ $activeCategory->slug }}">
                @endif

                <div class="filter-group">
                    <label class="filter-group__title" for="max_price">Maximum price</label>
                    <input type="range" class="price-range" id="max_price" name="max_price"
                           min="1" max="{{ (int) ceil($ceilingMinor / 100) }}" step="1"
                           value="{{ $maxPrice ?? (int) ceil($ceilingMinor / 100) }}"
                           data-price-input>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>{{ $currencySymbol }}1</span>
                        <span>
                            up to {{ $currencySymbol }}<span data-price-output>{{ number_format((float) ($maxPrice ?? ceil($ceilingMinor / 100)), 2) }}</span>
                        </span>
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-group__title" for="sort">Sort by</label>
                    <select name="sort" id="sort" class="form-select form-select-sm">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn btn-shop btn-sm w-100" data-filter-apply>Apply</button>
                    <a href="{{ route('products.index') }}" class="btn btn-quiet btn-sm w-100 mt-2">Clear all</a>
                </div>
            </form>
        </div>

        <div class="col-lg-9">
            <div class="section-head">
                <h2 class="mb-0">
                    @if ($search !== '')
                        Results for &ldquo;{{ $search }}&rdquo;
                    @else
                        {{ $activeCategory?->name ?? 'All products' }}
                    @endif
                </h2>
                <span class="result-count">
                    {{ $products->total() }} {{ Str::plural('product', $products->total()) }}
                </span>
            </div>

            @if ($products->isEmpty())
                <div class="empty-state">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <p class="mb-1">Nothing matched that.</p>
                    <p class="small mb-3">Try a broader price range, or clear the filters.</p>
                    <a href="{{ route('products.index') }}" class="btn btn-quiet btn-sm">Clear filters</a>
                </div>
            @else
                <div class="row g-3 g-md-4">
                    @foreach ($products as $product)
                        <div class="col-6 col-xl-4">
                            <x-product-card :product="$product" />
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
@endsection
