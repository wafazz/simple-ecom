@props(['provider'])

@php $result = session('test_result'); @endphp

@if ($result && ($result['provider'] ?? null) === $provider)
    <div class="alert alert-{{ $result['ok'] ? 'success' : 'danger' }} py-2 small mt-3">
        <strong>{{ $result['ok'] ? 'Connection OK' : 'Connection failed' }}</strong> —
        {{ $result['summary'] }}

        @if (! empty($result['endpoint']))
            <div class="mt-1"><code>{{ $result['endpoint'] }}</code></div>
        @endif

        @if (! empty($result['detail']))
            <div class="mt-1">{{ $result['detail'] }}</div>
        @endif

        @if (! empty($result['fields']))
            {{-- Field NAMES only. The response can carry customer data, so no
                 values are shown. These names are what payment verification
                 needs in order to recognise a settled bill. --}}
            <div class="mt-2">
                Response fields returned:
                @foreach ($result['fields'] as $field)
                    <code class="me-1">{{ $field }}</code>
                @endforeach
            </div>
        @endif

        @if (! empty($result['note']))
            <div class="mt-2 text-muted">{{ $result['note'] }}</div>
        @endif
    </div>
@endif
