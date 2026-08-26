@extends('layouts.admin')
@section('title', 'Integrations')
@section('heading', 'Integrations')

@section('content')
    <div class="card" style="max-width: 40rem">
        <div class="card-body">
            <h2 class="h6">EasyParcel</h2>
            <p class="text-muted small">Courier rates at checkout. Authorise once — the connection then renews itself.</p>

            <table class="table table-sm">
                <tbody>
                <tr>
                    <th style="width: 12rem">Credentials</th>
                    <td>
                        <span class="badge text-bg-{{ $configured ? 'success' : 'secondary' }}">
                            {{ $configured ? 'Configured' : 'Not configured' }}
                        </span>
                        @unless ($configured)
                            <div class="text-muted small mt-1">
                                Set <code>EASYPARCEL_CLIENT_ID</code> and <code>EASYPARCEL_CLIENT_SECRET</code> in <code>.env</code>.
                            </div>
                        @endunless
                    </td>
                </tr>
                <tr>
                    <th>Connection</th>
                    <td>
                        <span class="badge text-bg-{{ $connected ? 'success' : 'warning' }}">
                            {{ $connected ? 'Connected' : 'Not connected' }}
                        </span>
                    </td>
                </tr>
                @if ($connected)
                    <tr>
                        <th>Connected on</th>
                        <td>{{ $connectedAt?->toDayDateTimeString() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Access token expires</th>
                        <td>{{ $expiresAt?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @endif
                </tbody>
            </table>

            <p class="text-muted small">
                Credential values and tokens are never shown here. Tokens are stored encrypted.
            </p>

            @if ($connected)
                <form method="POST" action="{{ route('admin.integrations.disconnect') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">Disconnect</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.integrations.connect') }}">
                    @csrf
                    <button class="btn btn-shop btn-sm" @disabled(! $configured)>Connect EasyParcel</button>
                </form>
            @endif
        </div>
    </div>

    <div class="alert alert-secondary mt-3" style="max-width: 40rem">
        <strong>When rates are unavailable</strong> — not connected, token refresh failed, API error or timeout —
        checkout falls back to the flat shipping rate from Settings and records the order as
        <code>flat</code>. Losing a sale to courier downtime is not acceptable.
    </div>
@endsection
