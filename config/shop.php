<?php

/**
 * Store defaults. These are FALLBACKS — the live values are admin-editable in
 * the `settings` table (REQ-011). Nothing secret belongs here; API credentials
 * live in .env and are surfaced through config/services.php (spec §16, §31).
 */
return [
    'currency' => 'MYR',
    'currency_symbol' => 'RM',

    'store_name' => 'Basic Custom E-Commerce',
    'store_email' => null,
    'store_phone' => null,

    // EasyParcel quotation origin. ISO 3166-2 subdivision code, not a free-text
    // state name. A wrong origin means a wrong rate on every order (OQ-02).
    'pickup_postcode' => '50000',
    'pickup_state' => 'MY-14',
    'pickup_country' => 'MY',

    // Backstop so a quotation is never requested at zero weight (OQ-01).
    'default_weight_g' => 500,

    // Charged when the rate API is unreachable (Planning §11.B.6, OQ-04).
    'flat_shipping_fee_minor' => 1000,

    'low_stock_threshold' => 5,

    // Seconds to cache the settings table. Short enough that an admin edit
    // shows up promptly, long enough to keep it off every page render.
    'settings_cache_ttl' => 60,
];
