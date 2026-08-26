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

    /*
     * ISO 3166-2:MY subdivision codes — the vocabulary the EasyParcel Open API
     * requires (Planning §11.B.1: "the code MY-07 represents the state of
     * Penang"). Free-text state names are NOT accepted by the quotation API,
     * which is why the checkout form is a select and not a text input.
     */
    'states' => [
        'MY-01' => 'Johor',
        'MY-02' => 'Kedah',
        'MY-03' => 'Kelantan',
        'MY-04' => 'Melaka',
        'MY-05' => 'Negeri Sembilan',
        'MY-06' => 'Pahang',
        'MY-07' => 'Pulau Pinang',
        'MY-08' => 'Perak',
        'MY-09' => 'Perlis',
        'MY-10' => 'Selangor',
        'MY-11' => 'Terengganu',
        'MY-12' => 'Sabah',
        'MY-13' => 'Sarawak',
        'MY-14' => 'W.P. Kuala Lumpur',
        'MY-15' => 'W.P. Labuan',
        'MY-16' => 'W.P. Putrajaya',
    ],

    // Seconds to cache the settings table. Short enough that an admin edit
    // shows up promptly, long enough to keep it off every page render.
    'settings_cache_ttl' => 60,
];
