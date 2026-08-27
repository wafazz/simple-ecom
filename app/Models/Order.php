<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Totals and statuses are ABSENT from $fillable on purpose. They are set
     * explicitly in code from DB values, never from request input — that is the
     * structural half of the price-tampering control (spec §17, Planning §14).
     */
    protected $fillable = [
        'order_no',
        'customer_name', 'customer_email', 'customer_phone',
        'address_line', 'city', 'state', 'postcode', 'country',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'shipping_fee_minor' => 'integer',
            'grand_total_minor' => 'integer',
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Can a courier still be booked for this order? REQ-013.
     *
     * False once an AWB exists — that is the request "if order already
     * assigned AWB, disable book courier", and it is also the money guard:
     * a booked shipment has already been paid for out of the store's courier
     * credit, and `UNIQUE(shipments.order_id)` would reject a second one
     * anyway. This just stops the admin being offered the click.
     */
    public function canBookShipment(): bool
    {
        if ($this->payment_status !== PaymentStatus::Paid) {
            return false;
        }

        if (! $this->order_status->allowsShipmentBooking()) {
            return false;
        }

        $shipment = $this->shipment;

        // No shipment row yet, or a previous attempt that provably charged
        // nothing (failed / never submitted). `needs_reconciliation` is
        // deliberately NOT retryable — see ShipmentStatus.
        return $shipment === null || $shipment->status->isRetryable();
    }

    /** Has the courier issued an airway bill for this order? */
    public function hasAwb(): bool
    {
        return filled($this->shipment?->awb_no);
    }

    /** Is there a label an admin can actually print? */
    public function hasLabel(): bool
    {
        return $this->shipment?->labelUrl() !== null;
    }

    /**
     * May an admin key an AWB in by hand for this order?
     *
     * Deliberately NOT the same window as canBookShipment(). Typing an AWB
     * spends nothing, so it stays available once the parcel is already out —
     * an admin who moved the order to In Delivery before recording the number
     * must still be able to record it, and to fix a typo afterwards.
     *
     * The hard rule is the last one: a shipment that was really booked through
     * EasyParcel has been paid for out of the store's credit, and hand-editing
     * its courier or AWB would leave the row disagreeing with what the carrier
     * actually holds. Manual entry may only create a row, replace one it
     * created itself, or replace one that provably charged nothing.
     */
    public function canEnterAwbManually(): bool
    {
        if ($this->payment_status !== PaymentStatus::Paid) {
            return false;
        }

        if (! in_array($this->order_status, [
            OrderStatus::NewOrder,
            OrderStatus::Processing,
            OrderStatus::InDelivery,
        ], true)) {
            return false;
        }

        $shipment = $this->shipment;

        return $shipment === null
            || $shipment->isManual()
            || $shipment->status->isRetryable();
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    /**
     * Idempotent paid transition — Planning §11.A.5.
     *
     * Returns true only for the FIRST caller. A duplicate ToyyibPay callback
     * gets false and must be treated as a no-op returning 200, not an error.
     * The stock decrement hangs off this returning true.
     */
    public static function markPaidAtomically(int $orderId): bool
    {
        return static::query()
            ->whereKey($orderId)
            ->where('payment_status', PaymentStatus::Pending->value)
            ->update([
                'payment_status' => PaymentStatus::Paid->value,
                // Payment received => a new order awaiting fulfilment. The
                // admin moves it to Processing when they start picking.
                'order_status' => OrderStatus::NewOrder->value,
                'updated_at' => now(),
            ]) === 1;
    }

    public function usedFlatShippingRate(): bool
    {
        return $this->shipping_rate_source === 'flat';
    }
}
