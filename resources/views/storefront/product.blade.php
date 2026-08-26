@extends('layouts.storefront')
@section('title', $product->name)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
            <li class="breadcrumb-item">
                <a href="{{ route('products.index', ['category' => $product->category->slug]) }}">
                    {{ $product->category->name }}
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-5">
            @if ($product->image_path)
                <img src="{{ asset('uploads/'.$product->image_path) }}" alt="{{ $product->name }}"
                     class="img-fluid rounded product-thumb">
            @else
                <div class="product-thumb rounded d-flex align-items-center justify-content-center text-muted">
                    No image
                </div>
            @endif
        </div>

        <div class="col-md-7">
            <h1 class="h4">{{ $product->name }}</h1>

            @if ($product->description)
                <p class="text-muted">{{ $product->description }}</p>
            @endif

            <h2 class="h6 mt-4">Choose a variation</h2>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        @if ($variants->first()->option1_name !== '')
                            <th>{{ $variants->first()->option1_name }}</th>
                        @endif
                        @if ($variants->first()->option2_name !== '')
                            <th>{{ $variants->first()->option2_name }}</th>
                        @endif
                        <th class="money">Price</th>
                        <th>Availability</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($variants as $variant)
                        <tr>
                            @if ($variant->option1_name !== '')
                                <td>{{ $variant->option1_value }}</td>
                            @endif
                            @if ($variant->option2_name !== '')
                                <td>{{ $variant->option2_value }}</td>
                            @endif
                            <td class="money"><x-money :minor="$variant->price_minor" /></td>
                            <td>
                                @if ($variant->stock_qty === 0)
                                    <span class="badge text-bg-danger">Out of stock</span>
                                @else
                                    <span class="badge text-bg-success">In stock</span>
                                @endif
                            </td>
                            <td class="text-end">
                                {{-- Add-to-cart arrives in Phase 6. Out-of-stock
                                     variants will not be addable. --}}
                                <button type="button" class="btn btn-sm btn-shop" disabled
                                        title="Cart arrives in Phase 6">Add to cart</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mb-0">
                Each size and colour is priced and stocked separately.
            </p>
        </div>
    </div>
@endsection
