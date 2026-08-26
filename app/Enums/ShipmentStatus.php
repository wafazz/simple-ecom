<?php

namespace App\Enums;

/** REQ-013. Planning §11.B.5.4. */
enum ShipmentStatus: string
{
    /** Written BEFORE any API call, so a paid booking can never go unrecorded. */
    case PendingSubmit = 'pending_submit';

    case Submitting = 'submitting';
    case Submitted = 'submitted';
    case Paid = 'paid';
    case Booked = 'booked';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';

    /**
     * We do not know whether money left the wallet — a `pay` call timed out or
     * returned an unreadable body. NEVER resolved automatically and NEVER
     * auto-retried: retrying a payment that may have succeeded is how a store
     * pays twice. An admin clears it against the EasyParcel dashboard.
     */
    case NeedsReconciliation = 'needs_reconciliation';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingSubmit => 'Pending Submit',
            self::Submitting => 'Submitting',
            self::Submitted => 'Submitted',
            self::Paid => 'Paid',
            self::Booked => 'Booked',
            self::InTransit => 'In Transit',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::NeedsReconciliation => 'Needs Reconciliation',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Rows an admin must act on before the money position is known. */
    public function needsAttention(): bool
    {
        return in_array($this, [self::NeedsReconciliation, self::Failed], true);
    }

    /** Safe to retry a booking? Only when nothing can have been charged. */
    public function isRetryable(): bool
    {
        return in_array($this, [self::PendingSubmit, self::Failed], true);
    }
}
