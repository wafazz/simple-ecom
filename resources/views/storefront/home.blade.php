@extends('layouts.storefront')

@section('title', 'Home')

@section('content')
    <div class="p-4 p-md-5 mb-4 rounded bg-body-secondary">
        <h1 class="h3">{{ $storeName }}</h1>
        <p class="text-muted mb-0">Browse the catalogue, pick your size and colour, and check out as a guest.</p>
    </div>

    <h2 class="h5 mb-3">Categories</h2>

    @if ($categories->isEmpty())
        <p class="text-muted">No categories yet.</p>
    @else
        <div class="row g-3">
            @foreach ($categories as $category)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h6 mb-0">{{ $category->name }}</h3>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="text-muted small mt-4 mb-0">
        Product listing, cart and checkout arrive in Phases 5–6.
    </p>
@endsection
