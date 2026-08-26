<?php

namespace App\Support;

/**
 * Millimetres in, centimetres out.
 *
 * EasyParcel wants `double(8,2)` centimetres. Storing centimetres as a float
 * invites the same rounding problems money has, so dimensions live as integer
 * millimetres and convert once, here, at the request boundary.
 */
final class Dimensions
{
    private function __construct() {}

    /** 205 mm -> 20.5 cm */
    public static function mmToCm(int $mm): float
    {
        return round($mm / 10, 2);
    }

    /** Grams -> kilograms, the unit EasyParcel's `weight` field expects. */
    public static function gToKg(int $grams): float
    {
        return round($grams / 1000, 2);
    }
}
