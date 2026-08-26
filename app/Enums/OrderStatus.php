<?php

namespace App\Enums;

/** Spec §14. Planning §10. Stored as VARCHAR — the enum lives in PHP only. */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Paid, but the stock decrement could not be satisfied — two customers
     * reached the gateway for the last unit. Money is never silently kept
     * against an unfulfillable line; the admin resolves it (Planning §7.5).
     */
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::Paid => 'Paid',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NeedsReview => 'Needs Review',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
