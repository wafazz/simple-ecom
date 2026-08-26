<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer minor units (sen) — Planning §12.1.
 *
 * Every amount in this system is an int. Floats are not used for money at any
 * point, including display: formatting divides only at the very last step, into
 * a string, and the result is never fed back into arithmetic.
 */
final class Money
{
    private function __construct() {}

    /** Sen -> "30.00". Display only — never parse this back for arithmetic. */
    public static function format(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Sen -> "RM30.00". */
    public static function display(int $minor, string $currency = 'RM'): string
    {
        return $currency.self::format($minor);
    }

    /**
     * "10.84" -> 1084. The EasyParcel boundary: the API returns a decimal
     * STRING, and this is the single point where it becomes sen. Planning §11.B.1.
     */
    public static function fromDecimalString(string $amount): int
    {
        $trimmed = trim($amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $trimmed)) {
            throw new InvalidArgumentException("Not a decimal amount: {$amount}");
        }

        $negative = str_starts_with($trimmed, '-');
        $trimmed = ltrim($trimmed, '-');

        [$whole, $fraction] = array_pad(explode('.', $trimmed, 2), 2, '0');

        // Round on the third decimal rather than truncating, so "10.999"
        // becomes 1100 and not 1099.
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0', STR_PAD_RIGHT);
        $thousandths = (int) $whole * 1000 + (int) $fraction;
        $minor = intdiv($thousandths + 5, 10);

        return $negative ? -$minor : $minor;
    }

    /** Sen -> "2,107,847.94". Grouped for dashboard figures. Display only. */
    public static function displayGrouped(int $minor, string $currency = 'RM'): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);

        // number_format on the INTEGER ringgit part — the fractional part is
        // padded separately, so no float is ever constructed.
        return $sign.$currency.' '.number_format(intdiv($abs, 100))
            .'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Sen -> "RM 2,107,848". Rounded to the ringgit, for headline tiles. */
    public static function displayWhole(int $minor, string $currency = 'RM'): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.$currency.' '.number_format(intdiv(abs($minor) + 50, 100));
    }

    /**
     * Percentage change between two amounts, as a float for display only.
     *
     * Returns null when there is no baseline to compare against — "up from
     * zero" is not a percentage, and rendering one would be a fabricated
     * number on a business dashboard.
     */
    public static function percentChange(int $previousMinor, int $currentMinor): ?float
    {
        if ($previousMinor === 0) {
            return null;
        }

        return (($currentMinor - $previousMinor) / $previousMinor) * 100;
    }

    /** Line total. Kept here so no controller ever writes `$price * $qty` inline. */
    public static function lineTotal(int $unitMinor, int $qty): int
    {
        if ($qty < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        return $unitMinor * $qty;
    }

    /** @param  array<int, int>  $amounts */
    public static function sum(array $amounts): int
    {
        return array_sum($amounts);
    }
}
