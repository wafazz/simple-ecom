@extends('layouts.storefront')

@section('title', 'Home')

@section('content')
    @if ($slides->isEmpty())
        {{-- The shop's own hero, shown until the first banner is added. A blank
             band on a new install would read as a broken page. --}}
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
    @else
        @php($multiple = $slides->count() > 1)

        {{-- One banner is a hero, not a carousel: arrows, dots and a timer that
             move between a single slide are chrome that does nothing. --}}
        <section @class(['hero-slider', 'carousel slide' => $multiple])
                 @if ($multiple) id="heroSlider" data-bs-ride="carousel" data-bs-interval="6000"
                     data-bs-pause="hover" aria-roledescription="carousel" aria-label="Featured" @endif>

            <div @class(['carousel-inner' => $multiple])>
                @foreach ($slides as $i => $slide)
                    <div @class(['hero-slide', 'carousel-item' => $multiple, 'active' => $multiple && $i === 0])
                         @if ($multiple) aria-roledescription="slide"
                             aria-label="{{ $i + 1 }} of {{ $slides->count() }}" @endif>

                        @if ($slide->imageUrl())
                            {{-- The first banner is what the visitor waits for, so it
                                 loads eagerly and is flagged high priority; the rest
                                 wait until they are needed. --}}
                            <img src="{{ $slide->imageUrl() }}" alt=""
                                 class="hero-slide__img"
                                 style="object-position: {{ $slide->objectPosition() }}"
                                 loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                 fetchpriority="{{ $i === 0 ? 'high' : 'auto' }}"
                                 decoding="async">
                            <span class="hero-slide__scrim" aria-hidden="true"></span>
                        @endif

                        <div class="hero-slide__inner">
                            @if ($slide->eyebrow)
                                <p class="eyebrow">{{ $slide->eyebrow }}</p>
                            @endif

                            <h1>{{ $slide->headline }}</h1>

                            @if ($slide->subtext)
                                <p>{{ $slide->subtext }}</p>
                            @endif

                            @if ($slide->buttons() !== [])
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($slide->buttons() as $n => $button)
                                        <a href="{{ $button['url'] }}"
                                           class="btn {{ $n === 0 ? 'btn-shop' : 'btn-quiet' }} px-4 py-2">
                                            {{ $button['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($multiple)
                <button class="carousel-control-prev" type="button"
                        data-bs-target="#heroSlider" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous banner</span>
                </button>
                <button class="carousel-control-next" type="button"
                        data-bs-target="#heroSlider" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next banner</span>
                </button>

                <div class="carousel-indicators">
                    @foreach ($slides as $i => $slide)
                        <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="{{ $i }}"
                                @class(['active' => $i === 0]) @if ($i === 0) aria-current="true" @endif
                                aria-label="Banner {{ $i + 1 }}"></button>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

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
