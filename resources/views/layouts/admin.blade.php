<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ $storeName }}</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-body-tertiary">
<div class="container-fluid">
    <div class="row">
        <aside class="col-12 col-md-3 col-lg-2 admin-sidebar p-3">
            <a href="{{ route('admin.dashboard') }}" class="navbar-brand text-white d-block mb-3">
                {{ $storeName }}
            </a>
            <ul class="nav nav-pills flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                       href="{{ route('admin.dashboard') }}">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}"
                       href="{{ route('admin.categories.index') }}">Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}"
                       href="{{ route('admin.products.index') }}">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}"
                       href="{{ route('admin.integrations.index') }}">Integrations</a>
                </li>
                {{-- Orders, shipments and settings land in Phase 9. --}}
            </ul>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-light w-100">Log out</button>
            </form>
        </aside>

        <main class="col-12 col-md-9 col-lg-10 py-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0">@yield('heading', 'Admin')</h1>
                <span class="text-muted small">{{ auth()->user()?->name }}</span>
            </div>
            <x-alerts />
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
