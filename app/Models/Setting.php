<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** REQ-011. NON-SECRET ONLY — credentials live in .env (spec §16, §31). */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * The whole table as key => value, cached.
     *
     * Deliberately NOT named all() or value(): both exist on Eloquent\Model
     * with different contracts, and shadowing them would either break the
     * signature or silently divert query-builder forwarding.
     *
     * @return array<string, string|null>
     */
    public static function cached(): array
    {
        return Cache::remember(
            'settings',
            (int) config('shop.settings_cache_ttl', 60),
            fn (): array => static::query()->pluck('value', 'key')->all()
        );
    }

    /** Falls back to config/shop.php when the key has never been set. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::cached()[$key] ?? $default ?? config("shop.{$key}");
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = static::get($key);

        return $value === null ? $default : (int) $value;
    }

    /** Portable across MySQL, MariaDB and the SQLite test driver. */
    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget('settings');
    }
}
