<?php

namespace App\Enums;

/**
 * The couriers the store hands parcels to by hand, while the EasyParcel
 * integration is on hold.
 *
 * A fixed list rather than a free-text field: the courier decides how an AWB
 * is read and chased later, and "J&T", "JT", "J&T Express" typed on three
 * different days are three different couriers to every report that groups by
 * it.
 *
 * The LABEL is what lands in `shipments.courier_name`, because that column is
 * also written by EasyParcel with the carrier's own name and is rendered
 * directly on the order, the AWB sheet and the fulfilment list. Storing the
 * label keeps one column meaning one thing. `provider = 'manual'` is what says
 * a row was keyed in by hand — see Shipment::isManual().
 */
enum Courier: string
{
    case NinjaVan = 'ninjavan';
    case PosLaju = 'poslaju';
    case JTExpress = 'jt_express';
    case SPXExpress = 'spx_express';

    public function label(): string
    {
        return match ($this) {
            self::NinjaVan => 'NinjaVan',
            self::PosLaju => 'PosLaju',
            self::JTExpress => 'J&T Express',
            self::SPXExpress => 'SPX Express',
        };
    }

    /**
     * Where a customer or an admin follows this parcel.
     *
     * The AWB is rawurlencode()d, not interpolated raw. Three of these four put
     * it in the PATH, where an AWB containing a slash — which the validation
     * rule allows — would otherwise invent an extra path segment and produce a
     * tracking link that quietly 404s.
     *
     * ⚠ These are the carriers' public tracking pages, supplied by the store
     * owner. Nothing here verifies them, and a carrier that reorganises its
     * site will break the link rather than raise anything.
     */
    public function trackingUrl(string $awbNo): string
    {
        $awb = rawurlencode($awbNo);

        return match ($this) {
            self::NinjaVan => "https://www.ninjavan.co/en-my/tracking?id={$awb}",
            self::PosLaju => "https://tracking.pos.com.my/tracking/{$awb}",
            self::JTExpress => "https://jtexpress.my/tracking/{$awb}",
            // As supplied: a bare query string with no parameter name.
            self::SPXExpress => "https://spx.com.my/track?{$awb}",
        };
    }

    /**
     * Recover the case from a stored label, so an existing manual shipment
     * re-selects its own courier when the form is reopened.
     *
     * Returns null for anything EasyParcel wrote — those carrier names are not
     * in this list and must not be coerced into it.
     */
    public static function tryFromLabel(?string $label): ?self
    {
        if ($label === null) {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        return null;
    }

    /** @return array<string, string> value => label, for a <select>. */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $c): array => ['v' => $c->value, 'l' => $c->label()], self::cases()),
            'l',
            'v'
        );
    }
}
