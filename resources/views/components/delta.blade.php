@props(['change'])

@php
    // null means there was no baseline to compare against — a dash, not a
    // percentage. "Up from zero" is not a meaningful figure.
    $flat = $change === null || abs($change) < 0.05;
    $down = ! $flat && $change < 0;
    $variant = $flat ? 'flat' : ($down ? 'down' : 'up');
@endphp

<span class="delta delta--{{ $variant }}">
    @if ($flat)
        —
    @else
        {{ $down ? '↓' : '↑' }} {{ number_format(abs($change), 1) }}%
    @endif
</span>
