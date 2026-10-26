<?php

/*
|--------------------------------------------------------------------------
| Fapshi — mobile money collection for the platform access fee
|--------------------------------------------------------------------------
|
| https://docs.fapshi.com/en
|
| Credentials live in .env and are written there by the Mobile Money config
| screen, the same as MTN and Orange: a database dump should never carry a
| payment key. Each Fapshi *service* has its own apiuser/apikey, and sandbox
| and live credentials are not interchangeable.
|
| The live apikey is shown once in Fapshi's dashboard. If it is lost a new one
| can be generated, which immediately revokes the old.
|
*/

return [

    'enabled' => env('FAPSHI_ENABLED', false),

    // sandbox | live. Kept separate from base_url so the environment can be
    // reported and reasoned about without parsing a URL.
    'environment' => env('FAPSHI_ENV', 'sandbox'),

    /*
     * The host, which FOLLOWS the environment unless something explicitly
     * overrides it.
     *
     * These two used to be independent, and that is a trap with real money at
     * the end of it: set FAPSHI_ENV=live, leave the URL alone, and every screen
     * and every stored transaction says "live" while the requests still go to
     * sandbox. Payments appear to work and no money ever arrives. It happened
     * the first time these settings were saved.
     *
     * So the environment decides, and FAPSHI_BASE_URL is only consulted when it
     * is set to something that is not one of the two known hosts — which is the
     * only case where an override means anything.
     */
    'base_url' => (function () {
        $default = env('FAPSHI_ENV', 'sandbox') === 'live'
            ? 'https://live.fapshi.com'
            : 'https://sandbox.fapshi.com';

        $configured = trim((string) env('FAPSHI_BASE_URL', ''));

        if ($configured === '') {
            return $default;
        }

        $known = ['https://live.fapshi.com', 'https://sandbox.fapshi.com'];

        return in_array(rtrim($configured, '/'), $known, true) ? $default : $configured;
    })(),

    'api_user' => env('FAPSHI_API_USER'),
    'api_key'  => env('FAPSHI_API_KEY'),

    /*
     * Fapshi's webhook carries no signature. The only thing distinguishing a
     * real notification from anybody's POST is this secret, echoed back in an
     * x-wh-secret header — and it is set in Fapshi's dashboard, which does not
     * show whether one is already configured.
     *
     * Because that is thin, the webhook here is never trusted on its own: it is
     * treated as a nudge to re-query payment-status, and the authoritative
     * answer comes from that call. See FapshiController::webhook().
     */
    'webhook_secret' => env('FAPSHI_WEBHOOK_SECRET'),

    'http_timeout' => (int) env('FAPSHI_HTTP_TIMEOUT', 20),

    /*
     * Fapshi's own limits, stated here rather than scattered through the code.
     *
     * A payment link expires 24 hours after it is created and cannot be used
     * afterwards, so a student holding an older link is given a fresh one.
     *
     * payment-status allows 6 requests per minute for any one transId and
     * answers 429 beyond that, which is why reconciliation is paced and why
     * webhooks are preferred over polling.
     */
    'link_lifetime_hours' => 24,
    'status_polls_per_minute' => 6,

    // Fapshi rejects anything under 100 XAF, and requires a whole number.
    'minimum_amount' => 100,

];
