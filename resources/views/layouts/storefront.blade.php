<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $storeName) — {{ $storeName }}</title>
    {{-- Locally hosted. Never a CDN at runtime (Planning §5, spec §6). --}}
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<nav class="navbar navbar-expand-md navbar-shop">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('home') }}">{{ $storeName }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
                aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('products.index') }}">Products</a></li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="{{ route('order-status.show') }}">Track Order</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('cart.index') }}">
                        Cart
                        @if ($cartCount > 0)
                            <span class="badge text-bg-light">{{ $cartCount }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
    <x-alerts />
    @yield('content')
</main>

<footer class="shop-footer py-3 mt-4">
    <div class="container text-muted d-flex justify-content-between flex-wrap gap-2">
        <span>&copy; {{ date('Y') }} {{ $storeName }}</span>
        <span>All prices in {{ $currency }}</span>
    </div>
</footer>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
