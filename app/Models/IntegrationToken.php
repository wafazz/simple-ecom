<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * REQ-006 — Planning §11.B.3.
 *
 * The only model holding credentials. Never render these to a view, never log
 * them, never return them from a controller.
 */
class IntegrationToken extends Model
{
    protected $fillable = [
        'provider', 'access_token', 'refresh_token', 'expires_at', 'connected_at',
    ];

    /** Both tokens are hidden so a stray toArray()/toJson() cannot leak them. */
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            // AES-256-GCM (config/app.php). Authenticated, so a tampered row
            // fails the tag check instead of decrypting to attacker-influenced
            // bytes that then get sent as an Authorization header.
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }
}
