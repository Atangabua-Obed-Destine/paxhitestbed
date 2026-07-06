<?php

/*
 * MTN & Orange Mobile Money (Cameroon) — gateway configuration.
 *
 * Sandbox (MTN):    https://sandbox.momodeveloper.mtn.com
 * Production (MTN): country-specific base URL provided after MTN Cameroon
 *                   merchant onboarding & KYC. Common: https://proxy.momoapi.mtn.com
 *
 * The applicant/student never sees these values; they configure "Pay with MoMo"
 * on the portal and this file drives the server-side API calls.
 */

return [

    // Which provider is currently enabled. Front-end shows only enabled providers.
    'providers' => [
        'mtn' => [
            'enabled'          => env('MTN_MOMO_ENABLED', false),
            'environment'      => env('MTN_MOMO_ENV', 'sandbox'), // sandbox | production
            'base_url'         => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'collections'      => [
                // Primary key from your MoMo Developer subscription (Collections product).
                'subscription_key' => env('MTN_MOMO_COLLECTIONS_SUBSCRIPTION_KEY'),
                // API User (UUID) & API Key — provisioned once per subscription. In
                // sandbox you generate them via the /v1_0/apiuser & /v1_0/apiuser/{X-Reference-Id}/apikey
                // endpoints. In production MTN provisions them for you.
                'api_user'         => env('MTN_MOMO_COLLECTIONS_API_USER'),
                'api_key'          => env('MTN_MOMO_COLLECTIONS_API_KEY'),
                // Callback host registered with MTN (must be HTTPS + publicly reachable).
                'callback_host'    => env('MTN_MOMO_CALLBACK_HOST'),
            ],
            'target_environment' => env('MTN_MOMO_TARGET_ENVIRONMENT', 'sandbox'), // sandbox | mtncameroon
        ],

        'orange' => [
            'enabled'         => env('ORANGE_MOMO_ENABLED', false),
            'environment'     => env('ORANGE_MOMO_ENV', 'sandbox'),
            'base_url'        => env('ORANGE_MOMO_BASE_URL', 'https://api.orange.com'),
            'client_id'       => env('ORANGE_MOMO_CLIENT_ID'),
            'client_secret'   => env('ORANGE_MOMO_CLIENT_SECRET'),
            'merchant_key'    => env('ORANGE_MOMO_MERCHANT_KEY'),
            'callback_host'   => env('ORANGE_MOMO_CALLBACK_HOST'),
        ],
    ],

    /**
     * Currency used for all MoMo requests. Sandbox accepts EUR; live Cameroon uses XAF.
     * If Setting.currency is not XAF, this value overrides it *only* for MoMo calls.
     */
    'currency' => env('MOMO_CURRENCY', 'XAF'),

    /**
     * How long to keep polling the MoMo API for a final status before giving up
     * and marking the transaction as "timeout" (still recoverable via callback).
     */
    'poll_timeout_seconds' => (int) env('MOMO_POLL_TIMEOUT', 90),
    'poll_interval_seconds' => (int) env('MOMO_POLL_INTERVAL', 3),

    /**
     * HTTP request timeout for individual MoMo API calls.
     */
    'http_timeout' => (int) env('MOMO_HTTP_TIMEOUT', 15),
];
