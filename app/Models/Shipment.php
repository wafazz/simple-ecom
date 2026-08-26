<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** REQ-013 — Planning §11.B.5. */
class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'provider', 'provider_shipment_ref',
        'awb_no', 'tracking_no', 'tracking_url', 'label_url',
        'courier_name', 'service_id', 'cost_minor', 'status',
        'raw_response', 'booked_at', 'last_tracked_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_minor' => 'integer',
            'status' => ShipmentStatus::class,
            'raw_response' => 'array',
            'booked_at' => 'datetime',
            'last_tracked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Guarded state transition — Planning §11.B.5.3.
     *
     * Returns true only for the caller that actually moved the row. This is
     * what stops two concurrent "Book shipment" clicks from both reaching the
     * EasyParcel API and spending credit twice; UNIQUE(order_id) is the second
     * line of defence behind it.
     */
    public static function transitionAtomically(int $shipmentId, ShipmentStatus $from, ShipmentStatus $to): bool
    {
        return static::query()
            ->whereKey($shipmentId)
            ->where('status', $from->value)
            ->update(['status' => $to->value, 'updated_at' => now()]) === 1;
    }

    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('status', [
            ShipmentStatus::NeedsReconciliation->value,
            ShipmentStatus::Failed->value,
        ]);
    }
}
