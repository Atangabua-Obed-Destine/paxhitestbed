<?php

/*
 * Where the browser should look for the toastr adapter's assets.
 *
 * config/flasher.php already derives a base path from APP_URL so the main
 * flasher script resolves under both deployments — this project is served from
 * a subdirectory in development (/paxhitestbed) and from the document root in
 * production. That fix never reached the TOASTR ADAPTER, whose paths are
 * hardcoded in the package as "/vendor/flasher/..." and are read from
 * config('toastr') rather than from config('flasher'):
 *
 *   vendor/php-flasher/flasher-toastr/ToastrPlugin.php
 *   vendor/php-flasher/flasher-laravel/Support/ServiceProvider.php:212
 *
 * The consequence was silent and total. flasher.min.js loaded correctly from
 * the prefixed path, then asked for /vendor/flasher/jquery.min.js and
 * /vendor/flasher/flasher-toastr.min.js, which 404 under a subdirectory
 * install. With its handler missing, flasher.render() had nothing to render
 * with — so EVERY toast in the admin failed to appear, on every screen, with
 * no error anyone would notice. The notification itself was always there: it
 * is in the page payload as JSON on each redirect.
 *
 * Silent is the wrong failure for a confirmation. "Did that save?" has to be
 * answerable from the screen.
 *
 * The assets are served from dashboard/ rather than vendor/ for the reason
 * config/flasher.php gives: the root .htaccess already maps dashboard/ into
 * public/, whereas a vendor/ URL would collide with Composer's own vendor
 * directory at the project root, which must never be reachable over the web.
 * The files are the ones `php artisan flasher:install` wrote to
 * public/vendor/flasher — copy them across after upgrading the package.
 */

$flasherBase = rtrim((string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH), '/');

return [
    'scripts' => [
        'cdn' => [
            'https://cdn.jsdelivr.net/npm/@flasher/flasher-toastr@1.3.2/dist/flasher-toastr.min.js',
        ],
        /*
         * jQuery is deliberately NOT listed.
         *
         * The package ships it because a bare page may not have it. This one
         * does: footer_script loads jQuery long before flasher injects, just
         * above </body>. Loading a second copy would replace window.$ and
         * orphan every plugin registered on the first — DataTables, Select2,
         * minicolors, the lot — which is a far worse fault than the one being
         * fixed.
         */
        'local' => [
            $flasherBase . '/dashboard/flasher/flasher-toastr.min.js',
        ],
    ],

    'styles' => [
        'cdn' => [
            'https://cdn.jsdelivr.net/npm/@flasher/flasher-toastr@1.3.2/dist/flasher-toastr.min.css',
        ],
        'local' => [
            $flasherBase . '/dashboard/flasher/flasher-toastr.min.css',
        ],
    ],

    /*
     * Bottom right, so a confirmation never covers the page header or the
     * action the person just used.
     */
    'options' => [
        'closeButton' => true,
        'progressBar' => true,
        'positionClass' => 'toast-bottom-right',
    ],
];
