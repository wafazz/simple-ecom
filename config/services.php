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

    /*
     * Outgoing mail, over SMTP.
     *
     * Any provider works — a transactional service like Mailgun or Postmark,
     * or the shop's own mailbox on its hosting. SMTP is deliberate rather than
     * a provider's HTTP API: the API drivers each need their own package, and
     * pulling one in drags the Symfony tree to a release requiring PHP 8.4
     * while this project pins the platform to 8.3 to match the server.
     *
     * Port matters more than it looks. Many hosts block 587 outbound, 2525 is
     * the usual unblocked alternative, and 465 speaks TLS from the first byte
     * rather than upgrading — so it is chosen, not assumed.
     */
    'mail' => [
        'smtp_host' => env('MAIL_SMTP_HOST'),
        'smtp_port' => env('MAIL_SMTP_PORT', 587),
        'smtp_username' => env('MAIL_SMTP_USERNAME'),
        'smtp_password' => env('MAIL_SMTP_PASSWORD'),
    ],

    /*
     * Where a settled order is posted.
     *
     * This VPS blocks outbound SMTP — all of it, not just 587, so the port
     * choice above cannot help and nothing this server sends leaves the box.
     * The order details are therefore POSTed over 443 to an endpoint on
     * another host, which does whatever it does with them. Nothing on this
     * side knows or cares that the far end happens to send an email; this is a
     * payload handed over, not a mail transport.
     *
     * With no URL set nothing is posted and orders complete exactly as now.
     */
    'order_relay' => [
        'url' => env('ORDER_RELAY_URL'),

        // Optional shared secret, sent as X-Relay-Token and repeated in the
        // body. The endpoint decides whether to require it.
        'token' => env('ORDER_RELAY_TOKEN'),

        // The post happens inside the ToyyibPay callback, which the gateway is
        // waiting on. Bounded, not generous.
        'connect_timeout' => 5,
        'timeout' => 15,
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

        // CONFIRMED against the official API reference 2026-08-27: the sample
        // getBillTransactions response returns "billpaymentAmount": "10.00",
        // i.e. decimal ringgit. (createBill's billAmount is the other way
        // round — "the amount is in cent. e.g. 100 = RM1" — which is why the
        // outbound path passes grand_total_minor straight through.)
        // Kept configurable as a safety valve; a wrong value causes an amount
        // MISMATCH that refuses to settle rather than accepting a wrong figure.
        'amount_format' => env('TOYYIBPAY_AMOUNT_FORMAT', 'decimal'),
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

        // OQ-13: the published request shape names `weight` but not its unit.
        // kg is the EasyParcel convention; switchable once a live call confirms.
        'weight_unit' => env('EASYPARCEL_WEIGHT_UNIT', 'kg'),
    ],

];
