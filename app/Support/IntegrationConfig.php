<?php

namespace App\Support;

use App\Models\SecureSetting;
use Illuminate\Database\QueryException;

/**
 * Resolves an integration credential: the admin-set value if there is one,
 * otherwise the `.env` value behind config().
 *
 * DELIBERATELY NOT CACHED. Caching decrypted secrets would write them to
 * storage/framework/cache in plaintext, which is exactly what encrypting the
 * column exists to prevent. One indexed lookup per service construction is a
 * price worth paying for that.
 */
final class IntegrationConfig
{
    /** Keys the admin may set. Anything else is config-only. */
    public const EDITABLE = [
        'toyyibpay.secret_key',
        'toyyibpay.category_code',
        'easyparcel.client_id',
        'easyparcel.client_secret',
    ];

    /** Values that must never be echoed back to a form. */
    public const WRITE_ONLY = [
        'toyyibpay.secret_key',
        'easyparcel.client_secret',
    ];

    /** Providers that have their own credential form. */
    public const PROVIDERS = ['toyyibpay', 'easyparcel'];

    private function __construct() {}

    /**
     * The editable keys belonging to one provider.
     *
     * @return array<int, string>
     */
    public static function editableFor(string $provider): array
    {
        return array_values(array_filter(
            self::EDITABLE,
            fn (string $key): bool => str_starts_with($key, $provider.'.')
        ));
    }

    public static function get(string $key): ?string
    {
        $stored = self::stored($key);

        return $stored !== null && $stored !== ''
            ? $stored
            : config("services.{$key}");
    }

    public static function put(string $key, ?string $value): void
    {
        if (! in_array($key, self::EDITABLE, true)) {
            throw new \InvalidArgumentException("Not an editable credential: {$key}");
        }

        SecureSetting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function forget(string $key): void
    {
        SecureSetting::query()->where('key', $key)->delete();
    }

    public static function isSetByAdmin(string $key): bool
    {
        $stored = self::stored($key);

        return $stored !== null && $stored !== '';
    }

    /** Where the value in effect came from, for the admin screen. */
    public static function source(string $key): string
    {
        if (self::isSetByAdmin($key)) {
            return 'admin';
        }

        return filled(config("services.{$key}")) ? 'env' : 'none';
    }

    /** A hint that identifies a value without disclosing it. */
    public static function hint(string $key): ?string
    {
        $value = self::get($key);

        if (! filled($value)) {
            return null;
        }

        return strlen($value) <= 4
            ? str_repeat('•', strlen($value))
            : str_repeat('•', 8).substr($value, -4);
    }

    private static function stored(string $key): ?string
    {
        try {
            return SecureSetting::query()->where('key', $key)->first()?->value;
        } catch (QueryException) {
            // The table may not exist yet during install or a fresh migrate.
            return null;
        }
    }
}
