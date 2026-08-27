<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ $storeName }}</title>

    {{-- All vendored locally. No CDN at runtime, no build step (spec §6). --}}
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('vendor/adminlte/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/app.css') }}">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle navigation">
                        <i class="bi bi-list"></i>
                    </a>
                </li>
                <li class="nav-item d-none d-md-block">
                    <a href="{{ route('home') }}" class="nav-link" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right me-1"></i> View store
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto">
                @if ($needsReviewCount ?? 0)
                    {{-- Paid orders whose stock could not be allocated. Surfaced
                         everywhere, not just on the order list. --}}
                    <li class="nav-item">
                        <a href="{{ route('admin.orders.index', ['order_status' => 'needs_review']) }}"
                           class="nav-link position-relative" title="Orders needing review">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-warning">
                                {{ $needsReviewCount }}
                            </span>
                        </a>
                    </li>
                @endif
                <li class="nav-item dropdown">
                    <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="d-none d-sm-inline">{{ auth()->user()?->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.password.edit') }}">
                                <i class="bi bi-key me-2"></i> Change password
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-box-arrow-right me-2"></i> Log out
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="{{ route('admin.dashboard') }}" class="brand-link">
                <i class="bi bi-shop brand-image opacity-75 ms-3 me-2"></i>
                <span class="brand-text fw-light">{{ $storeName }}</span>
            </a>
        </div>

        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation">
                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}"
                           class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <i class="nav-icon bi bi-speedometer2"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    @php
                        $onOrders = request()->routeIs('admin.orders.*');
                        $activeStatus = request()->query('order_status');
                    @endphp

                    {{-- Treeview. Opens whenever an order screen is showing, so the
                         current filter is never hidden behind a collapsed parent. --}}
                    <li class="nav-item {{ $onOrders ? 'menu-open' : '' }}">
                        {{-- href="#" is load-bearing, not laziness: AdminLTE's treeview
                             handler only calls preventDefault() when the parent's href
                             is exactly "#". Given a real URL it toggles AND navigates.
                             This entry opens and closes the submenu; "All Orders" below
                             is what actually reaches the list. --}}
                        <a href="#" role="button"
                           aria-expanded="{{ $onOrders ? 'true' : 'false' }}"
                           class="nav-link {{ $onOrders ? 'active' : '' }}">
                            <i class="nav-icon bi bi-receipt"></i>
                            <p>
                                Orders
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('admin.orders.index') }}"
                                   class="nav-link {{ $onOrders && ! $activeStatus ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-circle"></i>
                                    <p>
                                        All Orders
                                        <span class="nav-badge badge text-bg-secondary">{{ number_format($orderTotalCount) }}</span>
                                    </p>
                                </a>
                            </li>

                            @foreach (\App\Enums\OrderStatus::selectable() as $case)
                                @php $count = $orderStatusCounts[$case->value] ?? 0; @endphp
                                <li class="nav-item">
                                    <a href="{{ route('admin.orders.index', ['order_status' => $case->value]) }}"
                                       class="nav-link {{ $activeStatus === $case->value ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-circle"></i>
                                        <p>
                                            {{ $case->label() }}
                                            @if ($count > 0)
                                                <span class="nav-badge badge text-bg-secondary">{{ number_format($count) }}</span>
                                            @endif
                                        </p>
                                    </a>
                                </li>
                            @endforeach

                            {{-- System-set, so it is not in selectable() — but it must be
                                 reachable, and it is the one an owner needs to see. --}}
                            @if ($needsReviewCount > 0)
                                <li class="nav-item">
                                    <a href="{{ route('admin.orders.index', ['order_status' => 'needs_review']) }}"
                                       class="nav-link {{ $activeStatus === 'needs_review' ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-exclamation-triangle text-warning"></i>
                                        <p>
                                            Needs Review
                                            <span class="nav-badge badge text-bg-warning">{{ number_format($needsReviewCount) }}</span>
                                        </p>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </li>

                    @php
                        $rest = [
                            ['admin.slides.index',      'admin.slides.*',       'bi-images',   'Banners'],
                            ['admin.products.index',    'admin.products.*',     'bi-box-seam', 'Products'],
                            ['admin.categories.index',  'admin.categories.*',   'bi-tags',     'Categories'],
                            ['admin.integrations.index','admin.integrations.*', 'bi-plug',     'Integrations'],
                            ['admin.policy.edit',       'admin.policy.*',       'bi-file-text', 'Return policy'],
                            ['admin.settings.edit',     'admin.settings.*',     'bi-gear',     'Settings'],
                        ];
                    @endphp

                    @foreach ($rest as [$route, $pattern, $icon, $label])
                        <li class="nav-item">
                            <a href="{{ route($route) }}"
                               class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}">
                                <i class="nav-icon bi {{ $icon }}"></i>
                                <p>{{ $label }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h1 class="h4 mb-0">@yield('heading', 'Admin')</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                            <li class="breadcrumb-item active" aria-current="page">@yield('title', 'Admin')</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <x-alerts />
                @yield('content')
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">{{ config('app.name') }}</div>
        <strong>{{ $storeName }}</strong> — admin
    </footer>
</div>

<script src="{{ \App\Support\Asset::url('js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ \App\Support\Asset::url('vendor/adminlte/adminlte.min.js') }}"></script>
<script src="{{ \App\Support\Asset::url('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
