<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** No price attribute — price lives on the variant (Planning §7). */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'image_path', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** Additional views. The cover lives on `image_path`, not here. */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Every image for the gallery, cover first.
     *
     * @return array<int, string> URLs, empty when the product has no picture
     */
    public function galleryUrls(): array
    {
        $urls = [];

        if ($this->image_path) {
            $urls[] = asset('uploads/'.$this->image_path);
        }

        foreach ($this->images as $image) {
            $urls[] = $image->url();
        }

        return $urls;
    }

    /**
     * The one image used in listings, the cart and order screens.
     *
     * `image_path` answers this without touching the gallery — deliberately,
     * because a listing renders 12 cards and reading the relation here would
     * be 12 extra queries. The gallery is consulted ONLY when it is already
     * loaded, so a product whose pictures are all "extra views" still shows
     * one, and a listing that did not ask for the relation pays nothing.
     */
    public function coverUrl(): ?string
    {
        if ($this->image_path) {
            return asset('uploads/'.$this->image_path);
        }

        return $this->relationLoaded('images')
            ? $this->images->first()?->url()
            : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
