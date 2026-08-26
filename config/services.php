<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // REQ-005 — ToyyibPay. Planning §11.A.2.
    // env() is read here and nowhere else: after config:cache it returns null.
    'toyyibpay' => [
        'secret_key' => env('TOYYIBPAY_SECRET_KEY'),
        'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
        'sandbox' => (bool) env('TOYYIBPAY_SANDBOX', true),
        'base_url' => env('TOYYIBPAY_SANDBOX', true)
            ? 'https://dev.toyyibpay.com'
            : 'https://toyyibpay.com',
        'connect_timeout' => 5,
        'timeout' => 10,
    ],

    // REQ-006 / REQ-013 — EasyParcel Open API 2026-06. Planning §11.B.
    // OAuth 2.0, not a flat key (see OQ-03). Access/refresh tokens are NOT here:
    // they rotate at runtime and live encrypted in integration_tokens.
    'easyparcel' => [
        'client_id' => env('EASYPARCEL_CLIENT_ID'),
        'client_secret' => env('EASYPARCEL_CLIENT_SECRET'),
        'sandbox' => (bool) env('EASYPARCEL_SANDBOX', true),
        'base_url' => 'https://api.easyparcel.com/open_api/2026-06',
        'oauth_url' => 'https://api.easyparcel.com/oauth',
        'connect_timeout' => 5,
        'timeout' => 10,
    ],

];
