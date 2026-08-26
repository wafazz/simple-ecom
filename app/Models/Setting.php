<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** REQ-011. NON-SECRET ONLY — credentials live in .env (spec §16, §31). */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /** Portable across MySQL, MariaDB and the SQLite test driver. */
    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
