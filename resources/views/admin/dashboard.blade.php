@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="row g-3">
        @foreach ([
            ['Total Orders', $totalOrders, 'secondary'],
            ['Pending Orders', $pendingOrders, 'warning'],
            ['Paid Orders', $paidOrders, 'success'],
        ] as [$label, $value, $variant])
            <div class="col-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-muted small mb-1">{{ $label }}</p>
                        <p class="h3 mb-0 text-{{ $variant }}">{{ number_format($value) }}</p>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Total Sales</p>
                    <p class="h3 mb-0"><x-money :minor="$totalSalesMinor" /></p>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted small mt-4 mb-0">
        Total sales counts settled payments only. Catalogue, orders, shipments and
        settings arrive in Phases 5–9.
    </p>
@endsection
