@extends('layouts.storefront')
@section('title', 'Page Expired')
@section('content')
    <div class="text-center py-5">
        <p class="display-6 mb-2">419</p>
        <p class="text-muted">This page sat idle too long and the form expired. Please try again.</p>
        <a href="{{ url()->previous() }}" class="btn btn-shop mt-2">Go back</a>
    </div>
@endsection
