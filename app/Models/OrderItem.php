<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchase-time snapshot. Immutable after creation — a later catalogue edit
 * or price change must never rewrite a historical order (Planning §9.3).
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_variant_id',
        'product_name', 'variation_label', 'sku',
        'nameset_name', 'nameset_number', 'nameset_price_minor',
        'unit_price_minor', 'qty', 'line_total_minor',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'nameset_price_minor' => 'integer',
            'qty' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    /** Was a name and number printed on this line? */
    public function hasNameset(): bool
    {
        return filled($this->nameset_name) || filled($this->nameset_number);
    }

    /** "AZLAN 10" — what the printer needs, in one string. */
    public function namesetLabel(): string
    {
        return trim(($this->nameset_name ?? '').' '.($this->nameset_number ?? ''));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
