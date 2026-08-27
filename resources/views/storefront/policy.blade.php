@extends('layouts.storefront')

@section('title', $heading)
@section('meta_description', $heading.' — '.$storeName)

@section('content')
    <div class="policy">
        <h1>{{ $heading }}</h1>

        @if ($updatedAt)
            <p class="policy__updated">Last updated {{ $updatedAt->format('j F Y') }}</p>
        @endif

        <x-prose :text="$body" />

        <div class="policy__foot">
            <p class="mb-2">Something not covered here?</p>
            @if ($storeEmail)
                <a href="mailto:{{ $storeEmail }}" class="btn btn-shop">Email us</a>
            @endif
            <a href="{{ route('order-status.show') }}" class="btn btn-quiet">Track your order</a>
        </div>
    </div>
@endsection
