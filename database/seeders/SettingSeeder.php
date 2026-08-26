<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** REQ-011. NON-SECRET values only — credentials live in .env (spec §16, §31). */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'store_name' => 'Basic Custom E-Commerce',
            'store_email' => 'hello@basic-ecom.test',
            'store_phone' => '01X-XXXXXXX',
            'currency' => 'MYR',

            // Pickup origin for EasyParcel quotations. ISO 3166-2 subdivision
            // code, NOT a free-text state name. Placeholder — a wrong origin
            // means a wrong rate on every single order (OQ-02).
            'pickup_postcode' => '50000',
            'pickup_state' => 'MY-14',
            'pickup_country' => 'MY',

            // Backstop so a quotation can never be requested at zero weight
            // when a variant has no weight recorded (OQ-01).
            'default_weight_g' => '500',

            // Sender details — required by shipment booking (REQ-013).
            // Placeholders: a booking cannot be assembled until these are real.
            'pickup_name' => 'Basic Custom E-Commerce',
            'pickup_company' => '',
            'pickup_phone' => '01X-XXXXXXX',
            'pickup_phone_country_code' => 'MY',
            'pickup_email' => 'hello@basic-ecom.test',
            'pickup_address_1' => '',
            'pickup_address_2' => '',
            'pickup_city' => '',

            'default_length_mm' => '250',
            'default_width_mm' => '180',
            'default_height_mm' => '80',
            'collection_lead_days' => '1',

            // Charged when the rate API is unreachable, so a courier outage
            // never costs a sale (Planning §11.B.6, OQ-04).
            'flat_shipping_fee_minor' => '1000',

            // Weight-based delivery (REQ-006). Placeholder figures — the store
            // sets its real ones under Settings before going live.
            'ship_west_first_minor' => '800',
            'ship_west_next_minor' => '300',
            'ship_east_first_minor' => '1500',
            'ship_east_next_minor' => '1200',

            'low_stock_threshold' => '5',
        ];

        foreach ($defaults as $key => $value) {
            Setting::put($key, $value);
        }
    }
}
