<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

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
                'order_status' => OrderStatus::Processing->value,
                'updated_at' => now(),
            ]) === 1;
    }

    public function usedFlatShippingRate(): bool
    {
        return $this->shipping_rate_source === 'flat';
    }
}
