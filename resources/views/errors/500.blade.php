@extends('layouts.storefront')
@section('title', 'Something Went Wrong')
@section('content')
    {{-- Spec §23 — a safe, understandable message. The detail goes to the log,
         never to the customer. --}}
    <div class="text-center py-5">
        <p class="display-6 mb-2">Sorry</p>
        <p class="text-muted">Something went wrong on our side. Nothing was charged.</p>
        <a href="{{ route('home') }}" class="btn btn-shop mt-2">Back to the shop</a>
    </div>
@endsection
