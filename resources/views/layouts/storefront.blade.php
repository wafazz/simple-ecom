<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $storeName) — {{ $storeName }}</title>
    <meta name="description" content="@yield('meta_description', $storeName.' — browse the catalogue and check out as a guest.')">

    {{-- Locally hosted. Never a CDN at runtime (Planning §5, spec §6). --}}
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    {{-- Storefront only. The admin keeps app.css, so a change to the shop
         cannot reach back into the admin screens. --}}
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
</head>
<body>
<a href="#main" class="visually-hidden-focusable btn btn-shop m-2">Skip to content</a>

<header class="shop-header">
    <nav class="navbar navbar-expand-lg py-2">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">{{ $storeName }}</a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                    data-bs-target="#shopNav" aria-controls="shopNav" aria-expanded="false"
                    aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="shopNav">
                <ul class="navbar-nav me-auto ms-lg-3">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}"
                           href="{{ route('home') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"
                           href="{{ route('products.index') }}">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('order-status.*') ? 'active' : '' }}"
                           href="{{ route('order-status.show') }}">Track order</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2 py-2 py-lg-0">
                    {{-- A plain GET form: searching works with JavaScript off. --}}
                    <form class="header-search" method="GET" action="{{ route('products.index') }}" role="search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" name="q" value="{{ request('q') }}"
                               class="form-control form-control-sm" placeholder="Search products"
                               aria-label="Search products">
                    </form>

                    <a class="cart-link" href="{{ route('cart.index') }}">
                        <i class="bi bi-bag" aria-hidden="true"></i>
                        <span class="d-lg-none d-xl-inline">Cart</span>
                        <span class="cart-count" data-cart-count @if ($cartCount < 1) hidden @endif>{{ $cartCount }}</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="main" class="container py-4">
    <x-alerts />
    @yield('content')
</main>

<footer class="shop-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <h3>{{ $storeName }}</h3>
                <p class="mb-0" style="max-width: 24rem">
                    Everyday pieces, priced honestly and posted quickly. Check out as a
                    guest — no account needed.
                </p>
            </div>
            <div class="col-6 col-md-3">
                <h3>Shop</h3>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><a href="{{ route('products.index') }}">All products</a></li>
                    <li><a href="{{ route('cart.index') }}">Your cart</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <h3>Orders</h3>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><a href="{{ route('order-status.show') }}">Track your order</a></li>
                    <li>Payment by FPX &amp; online banking</li>
                </ul>
            </div>
        </div>

        <div class="shop-footer__bar">
            <span>&copy; {{ date('Y') }} {{ $storeName }}</span>
            <span>All prices in {{ $currency }}</span>
        </div>
    </div>
</footer>

{{-- The navbar toggler uses data-bs-toggle, which needs Bootstrap's JS.
     Without this the mobile menu silently does nothing. --}}
<script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
