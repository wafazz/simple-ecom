@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = method_exists($status, 'label') ? $status->label() : \Illuminate\Support\Str::headline($value);

    $variant = match ($value) {
        'paid', 'completed', 'delivered', 'booked' => 'success',
        // A new order is the one that needs someone to act on it.
        'new_order' => 'primary',
        'pending', 'pending_submit', 'submitting', 'submitted' => 'secondary',
        'processing', 'in_delivery', 'in_transit' => 'info',
        'failed', 'cancelled' => 'danger',
        'needs_review', 'needs_reconciliation' => 'warning',
        'returned', 'refunded' => 'dark',
        default => 'secondary',
    };
@endphp

<span class="badge text-bg-{{ $variant }}">{{ $label }}</span>
