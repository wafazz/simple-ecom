@extends('layouts.storefront')
@section('title', $product->name)
@section('meta_description', Str::limit(strip_tags($product->description ?? $product->name), 150))

@section('content')
    @php
        $gallery = $product->galleryUrls();
        $lowStock = (int) config('shop.low_stock_threshold', 5);
    @endphp

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Shop</a></li>
            <li class="breadcrumb-item">
                <a href="{{ route('products.index', ['category' => $product->category->slug]) }}">
                    {{ $product->category->name }}
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-6">
            @if ($gallery === [])
                <div class="gallery__placeholder"><i class="bi bi-image" aria-hidden="true"></i></div>
            @else
                <div class="gallery" data-gallery>
                    <div class="gallery__main">
                        <img src="{{ $gallery[0] }}" alt="{{ $product->name }}">
                    </div>

                    @if (count($gallery) > 1)
                        <div class="gallery__thumbs">
                            @foreach ($gallery as $i => $url)
                                <button type="button" data-full="{{ $url }}"
                                        class="gallery__thumb {{ $i === 0 ? 'is-active' : '' }}"
                                        aria-label="View image {{ $i + 1 }} of {{ count($gallery) }}">
                                    <img src="{{ $url }}" alt="" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="col-lg-6">
            <div class="buy-panel">
                <p class="eyebrow mb-0">{{ $product->category->name }}</p>
                <h1>{{ $product->name }}</h1>

                <p class="buy-panel__price mb-1">
                    {{-- format(), not display(): display() carries its own "RM", and the
                         symbol is printed OUTSIDE the span so the picker can swap the
                         number without having to know the currency. --}}
                    {{ $currencySymbol }}<span data-variant-price>{{ \App\Support\Money::format($variants->min('price_minor')) }}</span>
                </p>

                @if ($product->description)
                    <p class="buy-panel__desc">{{ $product->description }}</p>
                @endif

                {{-- The picker upgrades this form. Without JavaScript the
                     <select> below is visible and the form posts normally, so
                     every variant is still buyable. --}}
                <form method="POST" action="{{ route('cart.store') }}"
                      data-ajax-cart data-cart-url="{{ route('cart.index') }}">
                    @csrf

                    <div data-variant-picker
                         data-low-stock="{{ $lowStock }}"
                         data-variants='@json($variantData)'>

                        @if ($useSwatches && $option1Name !== '' && $option1Values !== [])
                            <div class="option-group">
                                <p class="option-group__label">
                                    {{ $option1Name }}
                                    <span class="option-group__chosen" data-chosen-axis="1"></span>
                                </p>
                                <div class="swatches">
                                    @foreach ($option1Values as $value)
                                        <button type="button" class="swatch" data-axis="1"
                                                data-value="{{ $value }}" aria-pressed="false">{{ $value }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($useSwatches && $option2Name !== '' && $option2Values !== [])
                            <div class="option-group">
                                <p class="option-group__label">
                                    {{ $option2Name }}
                                    <span class="option-group__chosen" data-chosen-axis="2"></span>
                                </p>
                                <div class="swatches">
                                    @foreach ($option2Values as $value)
                                        <button type="button" class="swatch" data-axis="2"
                                                data-value="{{ $value }}" aria-pressed="false">{{ $value }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- The submitted value, always. app.js sets it from the
                             swatches; with JS off the customer picks it here. --}}
                        {{-- Hidden only when the swatches above can reach every
                             variant. When they cannot, this is the ONLY control
                             that can, so it stays on screen. --}}
                        <div class="{{ $useSwatches ? 'option-group js-hidden' : 'option-group' }}">
                            <label class="option-group__label" for="variant_id">Variation</label>
                            <select name="variant_id" id="variant_id" class="form-select"
                                    data-variant-select required>
                                @foreach ($variants as $variant)
                                    {{-- Price AND stock state are written into the
                                         option text: with JavaScript off this
                                         select is the only place a customer can
                                         read either. --}}
                                    <option value="{{ $variant->id }}" @disabled($variant->stock_qty < 1)>
                                        {{ $variant->variationLabel() !== '' ? $variant->variationLabel() : $variant->sku }}
                                        — {{ $currencySymbol }}{{ \App\Support\Money::format($variant->price_minor) }}
                                        · {{ $variant->stock_qty < 1 ? 'Out of stock' : 'In stock' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="qty">
                                <button type="button" data-qty-step="-1" aria-label="Decrease quantity">−</button>
                                <input type="number" name="qty" value="1" min="1"
                                       max="{{ max($variants->max('stock_qty'), 1) }}"
                                       data-variant-qty aria-label="Quantity">
                                <button type="button" data-qty-step="1" aria-label="Increase quantity">+</button>
                            </div>

                            <span class="stock-line" data-variant-stock></span>
                        </div>

                        <button type="submit" class="btn btn-shop btn-lg w-100" data-variant-add>
                            Add to cart
                        </button>

                        <p class="small text-muted mt-2 mb-0">
                            SKU <code data-variant-sku></code>
                        </p>
                    </div>
                </form>

                <ul class="trust-list">
                    <li><i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span>Pay by FPX or online banking. You are returned here when it clears.</span></li>
                    <li><i class="bi bi-box-seam" aria-hidden="true"></i>
                        <span>Posted with a tracked courier — the tracking number reaches you by email.</span></li>
                    <li><i class="bi bi-person-check" aria-hidden="true"></i>
                        <span>Guest checkout. No account, no password to remember.</span></li>
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        /* The <select> is the fallback. Hide it only once the picker is running,
           so a JS error leaves a usable page rather than an unusable one. */
        document.querySelectorAll('.js-hidden').forEach(function (el) { el.hidden = true; });
    </script>
@endpush
