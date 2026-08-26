<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Weight-based delivery pricing — REQ-006.
 *
 * The store charges from its OWN table, not from a courier quotation. Four
 * numbers, set by the admin: a first-kilo price and a per-additional-kilo
 * price, for each of two zones.
 *
 * That is deliberately not the same figure as the courier's bill. EasyParcel
 * charges what it charges when the shipment is booked; the difference is the
 * store's margin or loss on delivery, and the admin sees it on the order
 * screen. Keeping the two apart is what lets the customer see one steady
 * price for the same parcel every time.
 */
final class ShippingRate
{
    /**
     * Sabah, Sarawak and Labuan. Everything else on the list is Peninsular.
     *
     * Held here rather than in config because it is not a preference — it is
     * geography, and an admin editing it would only ever be a mistake.
     */
    public const EAST_STATES = ['MY-12', 'MY-13', 'MY-15'];

    public const ZONE_WEST = 'west';

    public const ZONE_EAST = 'east';

    /** Setting keys, in the order the settings form shows them. */
    public const KEYS = [
        'ship_west_first_minor',
        'ship_west_next_minor',
        'ship_east_first_minor',
        'ship_east_next_minor',
    ];

    private function __construct() {}

    public static function zoneFor(string $stateCode): string
    {
        return in_array($stateCode, self::EAST_STATES, true)
            ? self::ZONE_EAST
            : self::ZONE_WEST;
    }

    public static function zoneLabel(string $zone): string
    {
        return $zone === self::ZONE_EAST ? 'East Malaysia' : 'West Malaysia';
    }

    /**
     * Chargeable kilos, always rounded UP.
     *
     * A courier bills the next whole kilo, so charging the exact fraction
     * would mean absorbing the difference on every part-kilo parcel. An empty
     * or featherweight cart still pays for one kilo — there is no zero-kilo
     * parcel.
     */
    public static function billableKilos(int $weightG): int
    {
        return max(1, (int) ceil($weightG / 1000));
    }

    /** Delivery cost in sen for this weight going to this state. */
    public static function forState(string $stateCode, int $weightG): int
    {
        return self::forZone(self::zoneFor($stateCode), $weightG);
    }

    public static function forZone(string $zone, int $weightG): int
    {
        $first = self::firstKiloMinor($zone);
        $next = self::nextKiloMinor($zone);

        return $first + (self::billableKilos($weightG) - 1) * $next;
    }

    public static function firstKiloMinor(string $zone): int
    {
        return Setting::getInt("ship_{$zone}_first_minor", $zone === self::ZONE_EAST ? 1500 : 800);
    }

    public static function nextKiloMinor(string $zone): int
    {
        return Setting::getInt("ship_{$zone}_next_minor", $zone === self::ZONE_EAST ? 1200 : 300);
    }

    /**
     * The quote a customer is shown and an order is priced from.
     *
     * Returned as a ShippingQuote so the rest of the checkout — the order
     * record, the confirmation, the admin screens — keeps working against one
     * shape rather than growing a second kind of shipping price.
     */
    public static function quoteFor(string $stateCode, int $weightG): ShippingQuote
    {
        $zone = self::zoneFor($stateCode);
        $kilos = self::billableKilos($weightG);

        return new ShippingQuote(
            serviceId: 'weight-'.$zone,
            courierName: 'Standard Delivery',
            serviceName: self::zoneLabel($zone).' · '.$kilos.' kg',
            priceMinor: self::forZone($zone, $weightG),
            source: 'weight',
        );
    }
}
