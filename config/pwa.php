<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The kill switch
    |--------------------------------------------------------------------------
    |
    | With this false, the service worker route serves a worker that unregisters
    | itself and deletes its caches. That matters: a service worker lives on the
    | student's phone, not on the server, so a bad one cannot be fixed by
    | deploying. Setting PWA_ENABLED=false undoes it everywhere, on the next
    | visit, without a deploy.
    |
    */

    'enabled' => env('PWA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cache version
    |--------------------------------------------------------------------------
    |
    | Every cache the worker keeps is named with this. Change it on a deploy and
    | the worker's activate step deletes everything older. The portal's CSS and
    | JS URLs carry no fingerprint, so this string is the only thing standing
    | between a student and a month-old stylesheet.
    |
    */

    'version' => env('PWA_VERSION', '1'),

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    |
    | theme_color paints the Android status bar; background_color is the splash
    | screen while the app starts.
    |
    */

    'theme_color' => env('PWA_THEME_COLOR', '#04a9f5'),

    'background_color' => env('PWA_BACKGROUND_COLOR', '#ffffff'),

    'short_name' => env('PWA_SHORT_NAME', null),

];
