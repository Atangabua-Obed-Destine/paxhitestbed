<?php
/**
 * Making the student portal installable.
 *
 * Nothing here writes to the database.
 *
 * One limit, stated rather than hidden: this suite cannot prove that a browser
 * REGISTERS the worker. Headless Chrome's virtual clock does not drive the
 * service worker thread, so register() neither resolves nor rejects there. What
 * is checked instead is everything the browser would be given - the manifest,
 * the worker's own source, its headers and its scope - and the real-device test
 * in the plan covers the rest.
 *
 * Run: php scripts/student_pwa_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

function check(string $what, bool $ok, string $why = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  " . $what . "\n";
    } else {
        $failed++;
        echo "  FAIL  " . $what . ($why ? "\n        " . $why : '') . "\n";
    }
}

function skip(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  " . $what . "\n";
}

app('session.store')->start();

$fetch = function (string $url) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$manifestResponse = $fetch('/student/manifest.webmanifest');
$manifest = json_decode($manifestResponse->getContent(), true) ?: [];

$workerResponse = $fetch('/student/sw.js');
$worker = $workerResponse->getContent();

$offlineResponse = $fetch('/student/offline');
$offline = $offlineResponse->getContent();

$login = $fetch('/student/login')->getContent();

// ---------------------------------------------------------------------------
section('The manifest a phone would read');
// ---------------------------------------------------------------------------
check('it is served', $manifestResponse->getStatusCode() === 200,
    'status ' . $manifestResponse->getStatusCode());

check('as a manifest, not as plain JSON',
    str_contains((string) $manifestResponse->headers->get('Content-Type'), 'manifest+json'),
    $manifestResponse->headers->get('Content-Type'));

check('it names the school rather than the framework',
    ($manifest['name'] ?? '') !== '' && ($manifest['name'] ?? '') !== 'Laravel',
    var_export($manifest['name'] ?? null, true));

check('it opens full screen',
    ($manifest['display'] ?? '') === 'standalone',
    var_export($manifest['display'] ?? null, true));

// A manifest whose start_url falls outside its own scope silently refuses to
// install, and this app runs under a sub-path, so the two are built from
// APP_URL rather than hardcoded.
$scope = $manifest['scope'] ?? '';
$start = $manifest['start_url'] ?? '';

check('its start page sits inside its own scope',
    $scope !== '' && $start !== '' && str_starts_with($start, $scope),
    'scope ' . $scope . ', start ' . $start);

check('and the scope is the student portal, not the whole site',
    str_ends_with(rtrim($scope, '/'), '/student'),
    'scope ' . $scope . ' — a wider scope would let the worker see admin pages');

check('it offers the icon sizes a phone asks for',
    collect($manifest['icons'] ?? [])->pluck('sizes')->contains('192x192')
        && collect($manifest['icons'] ?? [])->pluck('sizes')->contains('512x512'),
    json_encode(collect($manifest['icons'] ?? [])->pluck('sizes')));

check('including a maskable one',
    collect($manifest['icons'] ?? [])->contains(fn ($i) => ($i['purpose'] ?? '') === 'maskable'),
    'without it Android crops the logo into its circle');

foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png'] as $icon) {
    check('the file ' . $icon . ' actually exists',
        file_exists(public_path('pwa/' . $icon)),
        'the manifest would point at nothing');
}

// ---------------------------------------------------------------------------
section('The worker cannot reach anything but the students');
// ---------------------------------------------------------------------------
// This is the check that matters most. An admin screen served from a stale
// cache would be far worse than no PWA at all.
check('the worker is served from under the student prefix',
    str_contains(route('student.pwa.sw'), '/student/'),
    route('student.pwa.sw') . ' — a worker at the site root would claim every page');

$allowed = (string) $workerResponse->headers->get('Service-Worker-Allowed');

check('and says so in its scope header',
    str_ends_with(rtrim($allowed, '/'), '/student'),
    'Service-Worker-Allowed: ' . $allowed);

check('an admin page is outside that scope',
    !str_starts_with(url('admin/dashboard'), rtrim($scope, '/') . '/'),
    url('admin/dashboard') . ' falls inside ' . $scope);

check('a portal page is inside it',
    str_starts_with(url('student/dashboard'), rtrim($scope, '/') . '/'),
    url('student/dashboard') . ' falls outside ' . $scope);

// ---------------------------------------------------------------------------
section('The worker itself is served correctly');
// ---------------------------------------------------------------------------
check('it is JavaScript',
    str_contains((string) $workerResponse->headers->get('Content-Type'), 'javascript'),
    $workerResponse->headers->get('Content-Type'));

check('and is never itself cached',
    str_contains((string) $workerResponse->headers->get('Cache-Control'), 'no-store'),
    $workerResponse->headers->get('Cache-Control')
        . ' — a cached worker could not be replaced by the kill switch');

check('it carries the deploy version',
    str_contains($worker, 'const VERSION = "' . config('pwa.version') . '"'),
    'without it, old caches are never cleared and the portal has no fingerprinted assets');

// ---------------------------------------------------------------------------
section('Nothing authenticated is ever cached');
// ---------------------------------------------------------------------------
// The portal puts a live CSRF token in the head of every page and gates students
// on the platform fee, the first instalment and the programme they are viewing.
// All of that breaks the moment a page is served from a cache.
check('pages always go to the network',
    str_contains($worker, "request.mode === 'navigate'")
        && str_contains($worker, 'fetch(request).catch('),
    'navigations must be network-first with only the offline page as a fallback');

check('a page response is never put into a cache',
    !preg_match('/navigate[\s\S]{0,400}cache\.put/', $worker),
    'caching authenticated HTML would freeze a CSRF token and bypass the fee gates');

check('writes are left alone entirely',
    str_contains($worker, "request.method !== 'GET'"),
    'a cached POST would be a disaster');

check('another origin is left alone',
    str_contains($worker, 'url.origin !== self.location.origin'));

foreach ([
    '/payment/' => 'the payment and status-polling endpoints',
    '/fees' => 'the fees screens',
    '/progression/' => 'the progression check',
    '/switch-program' => 'the programme switcher',
    '/login' => 'the login page',
] as $path => $what) {
    check('never touches ' . $what,
        str_contains($worker, "'" . $path . "'"),
        'a cached answer here is a money or identity bug');
}

check('only static files are cached',
    str_contains($worker, 'isStaticAsset') && str_contains($worker, 'dashboard'),
    'the cache is for the theme and the branding, nothing else');

// ---------------------------------------------------------------------------
section('The kill switch');
// ---------------------------------------------------------------------------
// A worker lives on the student's phone, not on the server, so a bad one cannot
// be fixed by deploying.
config(['pwa.enabled' => false]);
$disabled = $fetch('/student/sw.js')->getContent();
config(['pwa.enabled' => true]);

check('with PWA_ENABLED=false the worker unregisters itself',
    str_contains($disabled, 'self.registration.unregister()'),
    'one env change must undo this on every phone, without a deploy');

check('and throws away its caches',
    str_contains($disabled, 'caches.delete'));

check('and ships no caching code at all',
    !str_contains($disabled, 'isStaticAsset') && !str_contains($disabled, 'cache.put'),
    'the switch is a Blade branch, so a disabled worker is a few lines that undo themselves');

check('which makes it far smaller than the real one',
    strlen($disabled) < strlen($worker) / 2,
    strlen($disabled) . ' bytes against ' . strlen($worker));

// ---------------------------------------------------------------------------
section('The offline page');
// ---------------------------------------------------------------------------
check('it is served', $offlineResponse->getStatusCode() === 200);

check('it names the school',
    str_contains($offline, (string) (App\Models\Setting::where('status', '1')->value('title') ?: 'x')));

check('it says what needs a connection',
    str_contains($offline, __('What needs a connection')),
    'a blank "you are offline" page tells a student nothing');

check('it carries nothing personal',
    !str_contains($offline, 'csrf-token') && !str_contains($offline, 'student_id'),
    'this is the one page that IS cached, so it must be the same for everybody');

check('and it recovers by itself when the signal returns',
    str_contains($offline, "addEventListener('online'"));

// ---------------------------------------------------------------------------
section('The install card on the login page');
// ---------------------------------------------------------------------------
check('the login page links the manifest',
    str_contains($login, 'rel="manifest"'));

check('and carries an apple touch icon',
    str_contains($login, 'apple-touch-icon'),
    'iPhone uses this for the home screen, not the manifest icons');

check('Android is offered a real install button',
    str_contains($login, 'beforeinstallprompt') && str_contains($login, 'deferred.prompt()'));

check('iPhone is shown how instead',
    str_contains($login, 'Add to Home Screen'),
    'Apple exposes no install API, so instructions are the only honest option');

check('and iPhone is detected rather than guessed at',
    str_contains($login, 'iPad|iPhone|iPod'));

check('somebody who already installed it is not pestered',
    str_contains($login, 'display-mode: standalone'));

check('and dismissing it is remembered',
    str_contains($login, 'localStorage') && str_contains($login, 'pax-install-dismissed'),
    'a card that returns every visit is an advert');

check('the worker is registered from the login page',
    str_contains($login, 'serviceWorker.register'));

// ---------------------------------------------------------------------------
section('What this suite cannot prove');
// ---------------------------------------------------------------------------
skip('that a browser registers the worker — headless Chrome\'s virtual clock does not run the worker thread');
skip('that iOS shows the card — only a real iPhone can confirm that');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
