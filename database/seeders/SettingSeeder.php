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

            // Charged when the rate API is unreachable, so a courier outage
            // never costs a sale (Planning §11.B.6, OQ-04).
            'flat_shipping_fee_minor' => '1000',

            'low_stock_threshold' => '5',
        ];

        foreach ($defaults as $key => $value) {
            Setting::put($key, $value);
        }
    }
}
