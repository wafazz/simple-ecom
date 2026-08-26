<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * REQ-010 / REQ-011 — integration credentials, encrypted at rest.
 *
 * Never render a value to a view, never log one, never cache one. The admin UI
 * writes values and reads only whether one is present (spec §16).
 */
class SecureSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** So a stray toArray()/toJson() cannot leak a secret. */
    protected $hidden = ['value'];

    protected function casts(): array
    {
        return [
            // AES-256-GCM (config/app.php). Authenticated, so a tampered row
            // fails the tag check rather than yielding attacker-chosen bytes.
            'value' => 'encrypted',
        ];
    }
}
