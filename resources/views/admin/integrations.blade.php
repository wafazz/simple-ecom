@extends('layouts.admin')
@section('title', 'Integrations')
@section('heading', 'Integrations')

@php
    $labels = [
        'toyyibpay.secret_key' => ['ToyyibPay secret key', 'toyyibpay_secret_key', 'From the ToyyibPay dashboard.'],
        'toyyibpay.category_code' => ['ToyyibPay category code', 'toyyibpay_category_code', 'The category bills are created under.'],
        'easyparcel.client_id' => ['EasyParcel client ID', 'easyparcel_client_id', 'From your EasyParcel developer application.'],
        'easyparcel.client_secret' => ['EasyParcel client secret', 'easyparcel_client_secret', 'Kept secret; never shown again once saved.'],
    ];
@endphp

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('admin.integrations.credentials') }}" autocomplete="off">
            @csrf @method('PUT')

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">Credentials</h2></div>
                <div class="card-body">
                    <div class="alert alert-secondary py-2 small mb-3">
                        Saved values are <strong>encrypted</strong> and are never displayed again —
                        only the last four characters. Leave a field blank to keep what is already
                        stored. A value set here overrides the one in the server's
                        <code>.env</code> file.
                    </div>

                    @foreach ($labels as $key => [$label, $field, $help])
                        @php $meta = $credentials[$key]; @endphp
                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span>{{ $label }}</span>
                                <span>
                                    @if ($meta['source'] === 'admin')
                                        <span class="badge text-bg-success">Set here</span>
                                    @elseif ($meta['source'] === 'env')
                                        <span class="badge text-bg-info">From .env</span>
                                    @else
                                        <span class="badge text-bg-secondary">Not set</span>
                                    @endif
                                </span>
                            </label>

                            <div class="input-group">
                                <input type="{{ $meta['write_only'] ? 'password' : 'text' }}"
                                       name="{{ $field }}" id="{{ $field }}"
                                       autocomplete="new-password" spellcheck="false"
                                       placeholder="{{ $meta['hint'] ? 'Currently '.$meta['hint'].' — leave blank to keep' : 'Not set' }}"
                                       class="form-control @error($field) is-invalid @enderror">
                                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text">{{ $help }}</div>
                        </div>
                    @endforeach

                    <button type="submit" class="btn btn-shop">Save credentials</button>
                </div>
            </div>
        </form>

        @php $adminSet = collect($credentials)->filter(fn ($m) => $m['source'] === 'admin'); @endphp
        @if ($adminSet->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">Stored here</h2></div>
                <div class="card-body">
                    <p class="text-muted small">
                        Clearing removes the stored value so the server's <code>.env</code> setting
                        applies again. Blanking a field above does not do this — the form cannot show
                        you the current value, so blank has to mean “leave it alone”.
                    </p>
                    <ul class="list-group list-group-flush">
                        @foreach ($adminSet as $key => $meta)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>
                                    {{ $labels[$key][0] }}
                                    <code class="ms-1">{{ $meta['hint'] }}</code>
                                </span>
                                <form method="POST" action="{{ route('admin.integrations.credentials.clear', $key) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Clear</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">ToyyibPay</h2></div>
            <div class="card-body">
                <p class="mb-2">
                    <span class="badge text-bg-{{ $toyyibpayConfigured ? 'success' : 'secondary' }}">
                        {{ $toyyibpayConfigured ? 'Configured' : 'Not configured' }}
                    </span>
                    @if ($sandbox['toyyibpay'])
                        <span class="badge text-bg-warning">Sandbox</span>
                    @endif
                </p>
                <p class="text-muted small mb-0">
                    Payment verification refuses to settle an order on a response it cannot read.
                    If payments stay pending, check the log before assuming a fault.
                </p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">EasyParcel</h2></div>
            <div class="card-body">
                <p class="mb-2">
                    <span class="badge text-bg-{{ $configured ? 'success' : 'secondary' }}">
                        {{ $configured ? 'Configured' : 'Not configured' }}
                    </span>
                    <span class="badge text-bg-{{ $connected ? 'success' : 'warning' }}">
                        {{ $connected ? 'Connected' : 'Not connected' }}
                    </span>
                    @if ($sandbox['easyparcel'])
                        <span class="badge text-bg-warning">Sandbox</span>
                    @endif
                </p>

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
                    <p class="text-muted small">
                        Authorise once; the connection then renews itself.
                    </p>
                    <form method="POST" action="{{ route('admin.integrations.connect') }}">
                        @csrf
                        <button class="btn btn-shop btn-sm" @disabled(! $configured)>Connect EasyParcel</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="alert alert-secondary">
            <strong>When rates are unavailable</strong> — not connected, refresh failed, API error or
            timeout — checkout falls back to the flat shipping rate from Settings and records the
            order as <code>flat</code>. Losing a sale to courier downtime is not acceptable.
        </div>
    </div>
</div>
@endsection
