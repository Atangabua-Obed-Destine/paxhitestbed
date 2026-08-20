<?php

/*
 * This file is part of the PHPFlasher package.
 * (c) Younes KHOUBZA <younes.khoubza@gmail.com>
 */

// Where the browser should look for locally served assets.
//
// The path has to survive both deployments: this project is served from a
// subdirectory in development (/paxhitestbed) and from the document root in
// production, so a bare "/vendor/..." is right in one and a 404 in the other.
// Deriving it from APP_URL covers both without a per-environment edit.
//
// They live under dashboard/ rather than vendor/ on purpose: the root .htaccess
// already maps dashboard/ into public/, whereas a vendor/ URL would collide with
// Composer's own vendor directory at the project root — which must never be
// reachable over the web.
$flasherBase = rtrim((string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH), '/');

return array(
    /*
    |---------------------------------------------------------------------------
    | Default PHPFlasher library
    |---------------------------------------------------------------------------
    | This option controls the default library that will be used by PHPFlasher
    | to display notifications in your Laravel application. PHPFlasher supports
    | several libraries, including "flasher", "toastr", "noty", "notyf",
    | "sweetalert" and "pnotify".
    |
    | The "flasher" library is used by default. If you want to use a different
    | library, you will need to install it using composer. For example, to use
    | the "toastr" library, run the following command:
    |     composer require php-flasher/flasher-toastr-laravel
    |
    | Here is a list of the supported libraries and the corresponding composer
    | commands to install them:
    |
    | "toastr"     : composer require php-flasher/flasher-toastr-laravel
    | "noty"       : composer require php-flasher/flasher-noty-laravel
    | "notyf"      : composer require php-flasher/flasher-notyf-laravel
    | "sweetalert" : composer require php-flasher/flasher-sweetalert-laravel
    | "pnotify"    : composer require php-flasher/flasher-pnotify-laravel
    */
    'default' => 'toastr',
    'themes' => [
        'toastr' => [
            'options' => [
                'closeButton' => true,
                'progressBar' => true,
                'positionClass' => 'toast-bottom-right',
            ],
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Main PHPFlasher javascript file
    |---------------------------------------------------------------------------
    | This option specifies the location of the main javascript file that is
    | required by PHPFlasher to display notifications in your Laravel application.
    |
    | By default, PHPFlasher uses a CDN to serve the latest version of the library.
    | However, you can also choose to download the library locally or install it
    | using npm.
    |
    | To use the local version of the library, run the following command:
    |     php artisan flasher:install
    |
    | This will copy the necessary assets to your application's public folder.
    | You can then specify the local path to the javascript file in the 'local'
    | field of this option.
    */
    'root_script' => array(
        'cdn' => 'https://cdn.jsdelivr.net/npm/@flasher/flasher@1.3.2/dist/flasher.min.js',
        'local' => $flasherBase . '/dashboard/flasher/flasher.min.js',
    ),

    /*
    |---------------------------------------------------------------------------
    | PHPFlasher Stylesheet
    |---------------------------------------------------------------------------
    | This option specifies the location of the stylesheet file that is
    | required by PHPFlasher to style the notifications in your Laravel application.
    |
    | By default, PHPFlasher uses a CDN to serve the latest version of the stylesheet.
    | However, you can also choose to download the stylesheet locally or include it
    | from your assets.
    |
    | To use the local version of the stylesheet, make sure you have the necessary
    | assets in your application's public folder. Then specify the local path to
    | the stylesheet file in the 'local' field of this option.
    */
    'styles' => array(
        'cdn' => 'https://cdn.jsdelivr.net/npm/@flasher/flasher@1.3.2/dist/flasher.min.css',
        'local' => $flasherBase . '/dashboard/flasher/flasher.min.css',
    ),

    /*
    |---------------------------------------------------------------------------
    | Whether to use CDN for PHPFlasher assets or not
    |---------------------------------------------------------------------------
    | This option controls whether PHPFlasher should use CDN links or local assets
    | for its javascript and CSS files. By default, PHPFlasher uses CDN links
    | to serve the latest version of the library. However, you can also choose
    | to use local assets by setting this option to 'false'.
    |
    | If you decide to use local assets, don't forget to publish the necessary
    | files to your application's public folder by running the following command:
    |     php artisan flasher:install
    |
    | This will copy the necessary assets to your application's public folder.
    */
    /*
     * Served from this application, not a CDN.
     *
     * Flasher is what confirms an action succeeded — including reversing a
     * payment, which moves money. A CDN the browser blocks, or one that is
     * simply unreachable, would leave that action silent, and silent is the
     * wrong failure for anything financial. It also stops browsers logging a
     * tracking-prevention warning on every admin page.
     *
     * The files live in public/dashboard/flasher (see $flasherBase above for
     * why not public/vendor). `php artisan flasher:install` writes them to
     * public/vendor/flasher, so after upgrading the package copy them across.
     */
    'use_cdn' => false,

    /*
    |---------------------------------------------------------------------------
    | Translate PHPFlasher messages
    |---------------------------------------------------------------------------
    | This option controls whether PHPFlasher should pass its messages to the Laravel's
    | translation service for localization.
    |
    | By default, this option is set to 'true', which means that PHPFlasher will
    | attempt to translate its messages using the translation service.
    |
    | If you don't want PHPFlasher to use the Laravel's translation service, you can
    | set this option to 'false'. In this case, PHPFlasher will use the messages
    | as-is, without attempting to translate them.
    */
    'auto_translate' => true,

    /*
    |---------------------------------------------------------------------------
    | Inject PHPFlasher in Response
    |---------------------------------------------------------------------------
    | This option controls whether PHPFlasher should automatically inject its
    | javascript and CSS files into the HTML response of your Laravel application.
    |
    | By default, this option is set to 'true', which means that PHPFlasher will
    | listen to the response of your application and automatically insert its
    | scripts and stylesheets into the HTML before the closing `</body>` tag.
    |
    | If you don't want PHPFlasher to automatically inject its scripts and stylesheets
    | into the response, you can set this option to 'false'. In this case, you will
    | need to manually include the necessary files in your application's layout.
    */
    'auto_render' => true,

    'flash_bag' => array(
        /*
        |-----------------------------------------------------------------------
        | Enable flash bag
        |-----------------------------------------------------------------------
        | This option controls whether PHPFlasher should automatically convert
        | Laravel's flash messages to PHPFlasher notifications. This feature is
        | useful when you want to migrate from a legacy system or another
        | library that uses similar conventions for flash messages.
        |
        | When this option is set to 'true', PHPFlasher will check for flash
        | messages in the session and convert them to notifications using the
        | mapping specified in the 'mapping' option. When this option is set
        | to 'false', PHPFlasher will ignore flash messages in the session.
        */
        'enabled' => true,

        /*
        |-----------------------------------------------------------------------
        | Flash bag type mapping
        |-----------------------------------------------------------------------
        | This option allows you to map or convert session keys to PHPFlasher
        | notification types. On the left side are the PHPFlasher types.
        | On the right side are the Laravel session keys that you want to
        | convert to PHPFlasher types.
        |
        | For example, if you want to convert Laravel's 'danger' flash
        | messages to PHPFlasher's 'error' notifications, you can add
        | the following entry to the mapping:
        |     'error' => ['danger'],
        */
        'mapping' => array(
            'success' => array('success'),
            'error' => array('error', 'danger'),
            'warning' => array('warning', 'alarm'),
            'info' => array('info', 'notice', 'alert'),
        ),
    ),

    /*
    |---------------------------------------------------------------------------
    | Global Filter Criteria
    |---------------------------------------------------------------------------
    | This option allows you to filter the notifications that are displayed
    | in your Laravel application. By default, all notifications are displayed,
    | but you can use this option to limit the number of notifications or
    | filter them by type.
    |
    | For example, to limit the number of notifications to 5, you can set
    | the 'limit' field to 5:
    |     'limit' => 5,
    |
    | To filter the notifications by type, you can specify an array of
    | types that you want to display. For example, to only display
    | error notifications, you can set the 'types' field to ['error']:
    |     'types' => ['error'],
    |
    | You can also combine multiple criteria by specifying multiple fields.
    | For example, to display up to 5 error notifications, you can set
    | the 'limit' and 'types' fields like this:
    |     'limit' => 5,
    |     'types' => ['error'],
    */
    'filter_criteria' => array(
        'limit' => 5, // Limit the number of notifications to display
    ),
);
