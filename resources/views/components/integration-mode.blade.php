@props(['provider', 'mode'])

@php $isProduction = $mode === 'production'; @endphp

<form method="POST" action="{{ route('admin.integrations.mode', $provider) }}"
      class="d-inline-flex align-items-center gap-2"
      {{-- Both directions ask now. Going back to sandbox is not destructive, but
           it silently stops real payments being taken, which is worth a beat. --}}
      data-confirm="@if ($isProduction)Switch {{ ucfirst($provider) }} back to SANDBOX? Real payments stop being taken.@else Switch {{ ucfirst($provider) }} to PRODUCTION? Real money and real parcels from that point on.@endif">
    @csrf @method('PATCH')

    {{-- Submits the opposite of the current mode: one button, one decision. --}}
    <input type="hidden" name="mode" value="{{ $isProduction ? 'sandbox' : 'production' }}">

    <span class="badge text-bg-{{ $isProduction ? 'danger' : 'warning' }}">
        {{ $isProduction ? 'PRODUCTION' : 'Sandbox' }}
    </span>

    <button type="submit" class="btn btn-sm btn-outline-secondary">
        Switch to {{ $isProduction ? 'sandbox' : 'production' }}
    </button>
</form>
