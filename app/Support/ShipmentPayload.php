<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;

/**
 * Builds an EasyParcel `shipment/submit_orders` request from an order.
 *
 * Verified against source/includes/2026-06/_submitorder.md on 2026-08-27:
 * weight in KG, dimensions in CM, collection_date as YYYY-MM-DD, and `feature`
 * present as an object.
 *
 * Kept out of EasyParcelService deliberately: the service talks to the API, and
 * this decides what a shipment IS. Mixing them would put settings lookups and
 * unit conversions inside the HTTP client.
 */
final class ShipmentPayload
{
    /** Sender fields the API marks Required. Without every one, no booking. */
    private const REQUIRED_SENDER = [
        'pickup_name',
        'pickup_phone',
        'pickup_address_1',
        'pickup_postcode',
        'pickup_city',
        'pickup_country',
    ];

    private function __construct() {}

    /**
     * Everything still missing before this order could be booked.
     *
     * ⚠ The courier service is NOT checked here, deliberately. `orders`
     * carries what the CUSTOMER was quoted — since delivery moved to the
     * store's own weight table that is `weight-west` or `weight-east`, which
     * is not an EasyParcel service and would be rejected by the API. The real
     * service is picked by the admin when they book, and passed to for().
     *
     * Checked up front rather than discovered as a rejection from the courier,
     * because by then the request has already been made.
     *
     * @return array<int, string> human-readable, empty when ready
     */
    public static function missingFor(Order $order): array
    {
        $missing = [];

        foreach (self::REQUIRED_SENDER as $key) {
            if (blank(Setting::get($key))) {
                $missing[] = 'Store '.str_replace(['pickup_', '_'], ['', ' '], $key).' (Settings)';
            }
        }

        if (blank(Setting::get('pickup_state'))) {
            $missing[] = 'Store pickup state (Settings)';
        }

        foreach (['customer_name' => 'name', 'customer_phone' => 'phone',
            'address_line' => 'address', 'postcode' => 'postcode', 'city' => 'city'] as $field => $label) {
            if (blank($order->{$field})) {
                $missing[] = "Customer {$label}";
            }
        }

        if ($order->items->isEmpty()) {
            $missing[] = 'At least one order item';
        }

        return $missing;
    }

    public static function isReady(Order $order): bool
    {
        return self::missingFor($order) === [];
    }

    /**
     * @return array<string, mixed> the request body
     */
    /**
     * @param  string  $serviceId  an EasyParcel courier service, chosen by the
     *                             admin at booking time
     */
    public static function for(Order $order, string $serviceId): array
    {
        return [
            'shipment' => [[
                'reference' => $order->order_no,
                'service_id' => $serviceId,
                'collection_date' => now()
                    ->addDays(Setting::getInt('collection_lead_days', 1))
                    ->format('Y-m-d'),

                // Parcel totals: the summed weight of the lines, and the largest
                // single item's footprint. One parcel per order (assumption 6),
                // so nothing is being packed side by side.
                'weight' => Dimensions::gToKg(self::totalWeightG($order)),
                'length' => Dimensions::mmToCm(self::parcelDimension($order, 'length_mm')),
                'width' => Dimensions::mmToCm(self::parcelDimension($order, 'width_mm')),
                'height' => Dimensions::mmToCm(self::parcelDimension($order, 'height_mm')),

                'item' => self::items($order),
                'sender' => self::sender(),
                'receiver' => self::receiver($order),

                // Required object. Tracking notifications are the courier's to
                // send; nothing here is enabled without the store asking for it.
                'feature' => [
                    'sms_tracking' => false,
                    'email_tracking' => false,
                    'whatsapp_tracking' => false,
                ],
            ]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function items(Order $order): array
    {
        return $order->items->map(function ($item): array {
            $variant = $item->variant;

            return [
                'content' => mb_substr(trim($item->product_name.' '.$item->variation_label), 0, 100),
                'weight' => Dimensions::gToKg(self::variantWeightG($variant)),
                'length' => Dimensions::mmToCm(self::variantDimension($variant, 'length_mm')),
                'width' => Dimensions::mmToCm(self::variantDimension($variant, 'width_mm')),
                'height' => Dimensions::mmToCm(self::variantDimension($variant, 'height_mm')),
                'currency_code' => (string) Setting::get('currency', 'MYR'),
                // The declared value is the price actually paid per unit.
                'value' => round($item->unit_price_minor / 100, 2),
                'quantity' => $item->qty,
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    private static function sender(): array
    {
        return array_filter([
            'name' => Setting::get('pickup_name'),
            'company' => Setting::get('pickup_company'),
            'phone_number_country_code' => Setting::get('pickup_phone_country_code', 'MY'),
            'phone_number' => self::digits((string) Setting::get('pickup_phone')),
            'email' => Setting::get('pickup_email'),
            'address_1' => Setting::get('pickup_address_1'),
            'address_2' => Setting::get('pickup_address_2'),
            'postcode' => Setting::get('pickup_postcode'),
            'city' => Setting::get('pickup_city'),
            'subdivision_code' => Setting::get('pickup_state'),
            'country_code' => Setting::get('pickup_country', 'MY'),
        ], fn ($v): bool => $v !== null && $v !== '');
    }

    /** @return array<string, mixed> */
    private static function receiver(Order $order): array
    {
        return array_filter([
            'name' => $order->customer_name,
            // Malaysia-only shipping (assumption 4), so the dialling country
            // follows the delivery country rather than being collected.
            'phone_number_country_code' => $order->country ?: 'MY',
            'phone_number' => self::digits($order->customer_phone),
            'email' => $order->customer_email,
            'address_1' => $order->address_line,
            'postcode' => $order->postcode,
            'city' => $order->city,
            'subdivision_code' => $order->state,
            'country_code' => $order->country ?: 'MY',
        ], fn ($v): bool => $v !== null && $v !== '');
    }

    /** Couriers reject formatting; send digits only, without a leading zero. */
    private static function digits(string $phone): string
    {
        return ltrim(preg_replace('/\D+/', '', $phone) ?? '', '0');
    }

    /** Public: the booking screen quotes each order on this figure. */
    public static function totalWeightG(Order $order): int
    {
        $total = $order->items->sum(
            fn ($item): int => self::variantWeightG($item->variant) * $item->qty
        );

        return max((int) $total, Setting::getInt('default_weight_g', 500));
    }

    private static function variantWeightG(mixed $variant): int
    {
        return ($variant?->weight_g ?: 0) ?: Setting::getInt('default_weight_g', 500);
    }

    /** The largest single item's dimension — one box, not a stack. */
    private static function parcelDimension(Order $order, string $field): int
    {
        $largest = $order->items
            ->map(fn ($item): int => self::variantDimension($item->variant, $field))
            ->max();

        return (int) ($largest ?: self::defaultDimension($field));
    }

    private static function variantDimension(mixed $variant, string $field): int
    {
        return ($variant?->{$field} ?: 0) ?: self::defaultDimension($field);
    }

    private static function defaultDimension(string $field): int
    {
        return Setting::getInt('default_'.$field, match ($field) {
            'length_mm' => 250,
            'width_mm' => 180,
            default => 80,
        });
    }
}
