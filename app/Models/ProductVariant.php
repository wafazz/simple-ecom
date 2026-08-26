<?php

namespace App\Models;

use App\Enums\VariantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'sku', 'price_minor', 'stock_qty', 'weight_g', 'status',
        'length_mm', 'width_mm', 'height_mm',
        'option1_name', 'option1_value', 'option2_name', 'option2_value',
    ];

    protected function casts(): array
    {
        return [
            // Keeps money a real int in PHP. A DECIMAL column would arrive as a
            // string and coerce to float on the first multiplication.
            'price_minor' => 'integer',
            'stock_qty' => 'integer',
            'weight_g' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'status' => VariantStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Purchase history. Its FK is restrictOnDelete, so a variant that has ever
     * been sold cannot be deleted — it is deactivated instead.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Atomic, race-free stock decrement — Planning §7.5.
     *
     * ONE guarded UPDATE. Returns true only when this call is the one that took
     * the stock. Two concurrent callers cannot both succeed, because the
     * `stock_qty >= $qty` predicate is evaluated by the database inside the
     * write, not by PHP beforehand.
     *
     * Never $variant->decrement() on a loaded model, and never SELECT then
     * UPDATE — both read first and reintroduce the race.
     */
    public static function decrementStockAtomically(int $variantId, int $qty): bool
    {
        return static::query()
            ->whereKey($variantId)
            ->where('stock_qty', '>=', $qty)
            ->decrement('stock_qty', $qty) === 1;
    }

    /** '' means "this axis is unused" — never NULL (Planning §7.1). */
    public function variationLabel(): string
    {
        return collect([$this->option1_value, $this->option2_value])
            ->filter(fn (string $v): bool => $v !== '')
            ->implode(' / ');
    }

    public function isPurchasable(): bool
    {
        return $this->status === VariantStatus::Active && $this->stock_qty > 0;
    }

    public function scopeActive($query)
    {
        return $query->where('status', VariantStatus::Active->value);
    }
}
