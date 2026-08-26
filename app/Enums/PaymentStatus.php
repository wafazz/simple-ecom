<?php

namespace App\Enums;

/** Spec §14 — deliberately separate from OrderStatus. Planning §10. */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    /** Set manually by the admin. No gateway refund call is made (Planning §3.2). */
    case Refunded = 'refunded';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
