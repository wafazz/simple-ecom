<?php

namespace App\Enums;

/**
 * Fulfilment status — the client's operational vocabulary.
 *
 * Stored as VARCHAR; the enum lives in PHP only, so the schema is unchanged by
 * a rename. Deliberately separate from PaymentStatus (spec §14).
 */
enum OrderStatus: string
{
    /** Placed, not paid for. Nothing is owed to the customer yet. */
    case Pending = 'pending';

    /** Payment confirmed, not yet picked. Set by the gateway, not by hand. */
    case NewOrder = 'new_order';

    case Processing = 'processing';
    case InDelivery = 'in_delivery';
    case Completed = 'completed';

    /** Goods came back. Refunding the money is a separate payment action. */
    case Returned = 'returned';

    case Cancelled = 'cancelled';

    /**
     * Paid, but the stock decrement could not be satisfied — two customers
     * reached the gateway for the last unit. Money is never silently kept
     * against an unfulfillable line (Planning §7.5).
     *
     * SYSTEM-SET ONLY. Absent from selectable() so an admin cannot put an order
     * into it by hand; they resolve it by choosing a real status.
     */
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::NewOrder => 'New Order',
            self::Processing => 'Processing',
            self::InDelivery => 'In Delivery',
            self::Completed => 'Completed',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
            self::NeedsReview => 'Needs Review',
        };
    }

    /**
     * The statuses an admin may choose. NeedsReview is excluded: it is a
     * conclusion the system reaches, not a decision the admin makes.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => $case !== self::NeedsReview,
        ));
    }

    /** Still moving through fulfilment. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled, self::Returned], true);
    }

    /** Counts as revenue. Never-paid, cancelled and returned orders do not. */
    public function countsAsSale(): bool
    {
        return ! in_array($this, [self::Pending, self::Cancelled, self::Returned], true);
    }
}
