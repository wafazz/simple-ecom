<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A home page banner. Artwork and wording are kept apart — see the migration. */
class Slide extends Model
{
    use HasFactory;

    /** Where to anchor the picture when a narrow screen crops it. */
    public const FOCAL = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

    protected $fillable = [
        'image_path', 'focal', 'eyebrow', 'headline', 'subtext',
        'cta_label', 'cta_url', 'cta2_label', 'cta2_url',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function imageUrl(): ?string
    {
        return filled($this->image_path) ? asset('uploads/'.$this->image_path) : null;
    }

    /** The CSS value for object-position. Never interpolated from raw input. */
    public function objectPosition(): string
    {
        return array_key_exists($this->focal, self::FOCAL) ? $this->focal.' center' : 'center center';
    }

    /** @return array<int, array{label: string, url: string}> */
    public function buttons(): array
    {
        return array_values(array_filter([
            filled($this->cta_label) && filled($this->cta_url)
                ? ['label' => $this->cta_label, 'url' => $this->cta_url] : null,
            filled($this->cta2_label) && filled($this->cta2_url)
                ? ['label' => $this->cta2_label, 'url' => $this->cta2_url] : null,
        ]));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInOrder($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
