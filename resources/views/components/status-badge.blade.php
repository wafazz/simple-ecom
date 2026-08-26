@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = method_exists($status, 'label') ? $status->label() : \Illuminate\Support\Str::headline($value);

    $variant = match ($value) {
        'paid', 'completed', 'delivered', 'booked' => 'success',
        'pending', 'pending_payment', 'pending_submit', 'submitting', 'submitted' => 'secondary',
        'processing', 'shipped', 'in_transit' => 'info',
        'failed', 'cancelled' => 'danger',
        'needs_review', 'needs_reconciliation' => 'warning',
        'refunded' => 'dark',
        default => 'secondary',
    };
@endphp

<span class="badge text-bg-{{ $variant }}">{{ $label }}</span>
