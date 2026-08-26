@extends('layouts.storefront')
@section('title', 'Page Not Found')
@section('content')
    <div class="text-center py-5">
        <p class="display-6 mb-2">404</p>
        <p class="text-muted">We could not find that page.</p>
        <a href="{{ route('home') }}" class="btn btn-shop mt-2">Back to the shop</a>
    </div>
@endsection
