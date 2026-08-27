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

    /** `provider` value marking a hand-keyed shipment. */
    public const PROVIDER_MANUAL = 'manual';

    protected $fillable = [
        'order_id', 'provider', 'provider_shipment_ref',
        'awb_no', 'tracking_no', 'tracking_url', 'label_url', 'label_path',
        'courier_name', 'service_id', 'service_name', 'cost_minor', 'status',
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

    /**
     * What to show an admin for "courier service".
     *
     * Prefers the carrier and the service label; falls back to the raw
     * EasyParcel id, which is ugly but true. Never invents a name.
     */
    public function courierLabel(): string
    {
        $parts = array_filter([$this->courier_name, $this->service_name]);

        if ($parts !== []) {
            // service_name is often already "J&T — Standard"; don't repeat the
            // carrier when the label already opens with it.
            if (count($parts) === 2 && str_starts_with((string) $this->service_name, (string) $this->courier_name)) {
                return (string) $this->service_name;
            }

            return implode(' — ', $parts);
        }

        return (string) ($this->service_id ?? '—');
    }

    /** Keyed in by an admin rather than booked through a courier API. */
    public function isManual(): bool
    {
        return $this->provider === self::PROVIDER_MANUAL;
    }

    /**
     * Where to send an admin who wants to see the label, whichever way the
     * shipment was fulfilled.
     *
     * An uploaded label is NOT a public file — it resolves to an authenticated
     * route that streams it, so callers can treat both kinds the same and no
     * caller has to remember which is which.
     */
    public function labelUrl(): ?string
    {
        if (filled($this->label_path)) {
            return route('admin.orders.awb.label', $this->order_id);
        }

        return filled($this->label_url) ? $this->label_url : null;
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
