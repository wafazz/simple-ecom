@extends('layouts.admin')
@section('title', 'Integrations')
@section('heading', 'Integrations')

@php
    $fields = [
        'toyyibpay' => [
            'toyyibpay.secret_key' => ['Secret key', 'toyyibpay_secret_key', 'From the ToyyibPay dashboard.'],
            'toyyibpay.category_code' => ['Category code', 'toyyibpay_category_code', 'The category bills are created under.'],
        ],
        'easyparcel' => [
            'easyparcel.client_id' => ['Client ID', 'easyparcel_client_id', 'From your EasyParcel developer application.'],
            'easyparcel.client_secret' => ['Client secret', 'easyparcel_client_secret', 'Never shown again once saved.'],
        ],
    ];
@endphp

@section('content')
<div class="alert alert-secondary py-2 small">
    Saved values are <strong>encrypted</strong> and never displayed again — only the last four
    characters, so you can tell which value is stored. Leave a field blank to keep what is
    already there. A value set here overrides the server's <code>.env</code> file.
</div>

<div class="row g-3">

    {{-- ── ToyyibPay ─────────────────────────────────────────────── --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="card-title h6 mb-0"><i class="bi bi-credit-card me-1"></i> ToyyibPay</h2>
                <span>
                    <span class="badge text-bg-{{ $toyyibpayConfigured ? 'success' : 'secondary' }}">
                        {{ $toyyibpayConfigured ? 'Configured' : 'Not configured' }}
                    </span>
                    @if ($sandbox['toyyibpay'])
                        <span class="badge text-bg-warning">Sandbox</span>
                    @endif
                </span>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('admin.integrations.credentials', 'toyyibpay') }}" autocomplete="off">
                    @csrf @method('PUT')

                    @foreach ($fields['toyyibpay'] as $key => [$label, $field, $help])
                        @php $meta = $credentials[$key]; @endphp
                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label d-flex justify-content-between align-items-center gap-2">
                                <span>{{ $label }}</span>
                                @if ($meta['source'] === 'admin')
                                    <span class="badge text-bg-success">Set here</span>
                                @elseif ($meta['source'] === 'env')
                                    <span class="badge text-bg-info">From .env</span>
                                @else
                                    <span class="badge text-bg-secondary">Not set</span>
                                @endif
                            </label>
                            <input type="{{ $meta['write_only'] ? 'password' : 'text' }}"
                                   name="{{ $field }}" id="{{ $field }}"
                                   autocomplete="new-password" spellcheck="false"
                                   placeholder="{{ $meta['hint'] ? 'Currently '.$meta['hint'].' — leave blank to keep' : 'Not set' }}"
                                   class="form-control @error($field) is-invalid @enderror">
                            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">{{ $help }}</div>

                            @if ($meta['source'] === 'admin')
                                <div class="mt-1">
                                    <button form="clear-{{ $field }}" class="btn btn-link btn-sm p-0 text-danger">
                                        Clear and use .env
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <button type="submit" class="btn btn-shop">Save ToyyibPay credentials</button>
                </form>

                <hr>
                <p class="text-muted small mb-0">
                    Payment verification refuses to settle an order on a response it cannot read.
                    If payments stay pending, check the log before assuming a fault.
                </p>
            </div>
        </div>
    </div>

    {{-- ── EasyParcel ────────────────────────────────────────────── --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="card-title h6 mb-0"><i class="bi bi-truck me-1"></i> EasyParcel</h2>
                <span>
                    <span class="badge text-bg-{{ $configured ? 'success' : 'secondary' }}">
                        {{ $configured ? 'Configured' : 'Not configured' }}
                    </span>
                    <span class="badge text-bg-{{ $connected ? 'success' : 'warning' }}">
                        {{ $connected ? 'Connected' : 'Not connected' }}
                    </span>
                    @if ($sandbox['easyparcel'])
                        <span class="badge text-bg-warning">Sandbox</span>
                    @endif
                </span>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('admin.integrations.credentials', 'easyparcel') }}" autocomplete="off">
                    @csrf @method('PUT')

                    @foreach ($fields['easyparcel'] as $key => [$label, $field, $help])
                        @php $meta = $credentials[$key]; @endphp
                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label d-flex justify-content-between align-items-center gap-2">
                                <span>{{ $label }}</span>
                                @if ($meta['source'] === 'admin')
                                    <span class="badge text-bg-success">Set here</span>
                                @elseif ($meta['source'] === 'env')
                                    <span class="badge text-bg-info">From .env</span>
                                @else
                                    <span class="badge text-bg-secondary">Not set</span>
                                @endif
                            </label>
                            <input type="{{ $meta['write_only'] ? 'password' : 'text' }}"
                                   name="{{ $field }}" id="{{ $field }}"
                                   autocomplete="new-password" spellcheck="false"
                                   placeholder="{{ $meta['hint'] ? 'Currently '.$meta['hint'].' — leave blank to keep' : 'Not set' }}"
                                   class="form-control @error($field) is-invalid @enderror">
                            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">{{ $help }}</div>

                            @if ($meta['source'] === 'admin')
                                <div class="mt-1">
                                    <button form="clear-{{ $field }}" class="btn btn-link btn-sm p-0 text-danger">
                                        Clear and use .env
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <button type="submit" class="btn btn-shop">Save EasyParcel credentials</button>
                </form>

                <hr>

                @if ($connected)
                    <table class="table table-sm mb-3">
                        <tbody>
                        <tr><th style="width: 10rem">Connected</th><td>{{ $connectedAt?->toDayDateTimeString() ?? '—' }}</td></tr>
                        <tr><th>Token expires</th><td>{{ $expiresAt?->diffForHumans() ?? '—' }}</td></tr>
                        </tbody>
                    </table>
                    <form method="POST" action="{{ route('admin.integrations.disconnect') }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm">Disconnect</button>
                    </form>
                @else
                    <p class="text-muted small">Authorise once; the connection then renews itself.</p>
                    <form method="POST" action="{{ route('admin.integrations.connect') }}">
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" @disabled(! $configured)>Connect EasyParcel</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="alert alert-secondary mt-3">
    <strong>When rates are unavailable</strong> — not connected, refresh failed, API error or
    timeout — checkout falls back to the flat shipping rate from Settings and records the order
    as <code>flat</code>. Losing a sale to courier downtime is not acceptable.
</div>

{{-- Clear forms live outside the credential forms: a <form> cannot be nested,
     and each button targets its own by id. --}}
@foreach ($credentials as $key => $meta)
    @if ($meta['source'] === 'admin')
        @php $field = str_replace('.', '_', $key); @endphp
        <form id="clear-{{ $field }}" method="POST"
              action="{{ route('admin.integrations.credentials.clear', $key) }}" class="d-none">
            @csrf @method('DELETE')
        </form>
    @endif
@endforeach
@endsection
