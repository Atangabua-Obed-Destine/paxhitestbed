<?php

/**
 * What happens after a save.
 *
 * Two faults, both silent, both of which made a successful save look like
 * nothing had happened:
 *
 *   1. The toastr adapter's assets were served from "/vendor/flasher/...",
 *      hardcoded in the package and read from config('flasher_toastr') rather
 *      than config('flasher'), where the subdirectory fix had been applied.
 *      Under this install they 404, so flasher.render() had no handler and
 *      EVERY toast in the admin failed to appear — the notification was in the
 *      page payload the whole time.
 *
 *   2. update() returned redirect()->back(), landing on the edit form the save
 *      came from, which looked exactly as it had before.
 *
 * Driven through the real HTTP kernel rather than by calling the controller,
 * because both faults live in the response: the redirect target and what the
 * injected payload says.
 *
 * Writes are rolled back, and the closing section proves it with absolute
 * counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) { $pass++; return; }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

function ok(string $what, $condition): void { check($what, true, (bool) $condition); }

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$user = App\User::first();
$session = $app['session']->driver();

$go = function (Request $request) use ($kernel, $session, $user) {
    $request->setLaravelSession($session);
    Auth::guard('web')->setUser($user);

    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
};

$opening = [
    'incomes' => DB::table('incomes')->count(),
    'expenses' => DB::table('expenses')->count(),
    'entries' => DB::table('journal_entries')->count(),
];

echo "=== 1. the toast assets resolve under this install ===" . PHP_EOL;

$appPath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

foreach (['scripts', 'styles'] as $kind) {
    $assets = (array) config('flasher_toastr.' . $kind . '.local', []);

    ok('the adapter declares its ' . $kind, $assets !== []);

    foreach ($assets as $url) {
        // The fault: a package default of /vendor/flasher/... that 404s here.
        ok('  ' . $url . ' is not the package default',
            !str_starts_with($url, '/vendor/flasher/'));

        if ($appPath !== '') {
            ok('  and carries this install\'s base path', str_starts_with($url, $appPath . '/'));
        }

        // The root .htaccess maps everything into public/.
        $file = $base . '/public' . substr($url, strlen($appPath));

        ok('  and the file exists on disk', is_file($file));
    }
}

// jQuery must NOT be among them: the admin already loads it before flasher
// injects, and a second copy replaces window.$ and orphans every plugin
// registered on the first.
$scripts = (array) config('flasher_toastr.scripts.local', []);

check('no second jQuery is loaded', 0,
    count(array_filter($scripts, fn ($s) => str_contains($s, 'jquery'))));

echo PHP_EOL . "=== 2. an edit confirms itself, and goes where the record is ===" . PHP_EOL;

DB::beginTransaction();

foreach (['income' => App\Models\Income::class, 'expense' => App\Models\Expense::class] as $module => $model) {
    $row = $model::orderBy('id', 'desc')->first();

    if (!$row) { continue; }

    $editUrl = '/admin/account/' . $module . '/' . $row->id . '/edit';

    $go(Request::create($editUrl, 'GET'));

    $put = Request::create('/admin/account/' . $module . '/' . $row->id, 'PUT', [
        '_token' => $session->token(),
        'category' => $row->category_id,
        'title' => $row->title,
        'amount' => $row->amount,
        'date' => \Carbon\Carbon::parse($row->date)->format('Y-m-d'),
        'payment_method' => $row->payment_method,
    ]);
    $put->headers->set('referer', url($editUrl));

    $saved = $go($put);

    check($module . ' save redirects', 302, $saved->getStatusCode());

    $where = parse_url((string) $saved->headers->get('Location'), PHP_URL_PATH);

    check($module . ' lands on the list, not the form',
        '/admin/account/' . $module, $where);
    ok($module . ' does not land back on the edit form', !str_contains((string) $where, '/edit'));

    // Follow it, as a browser would, and read what the page was handed.
    $after = $go(Request::create($where, 'GET'));
    $html = $after->getContent();

    check($module . ' the list renders', 200, $after->getStatusCode());

    // The notification itself, in the payload flasher injects.
    ok($module . ' carries a success notification',
        str_contains($html, '"type":"success"'));
    ok($module . ' with the message the controller set',
        str_contains($html, __('msg_updated_successfully')));

    // And the handler that renders it is reachable, which is the half that
    // was broken: the message was always there, nothing could draw it.
    ok($module . ' names the toastr handler', str_contains($html, '"handler":"toastr"'));
    ok($module . ' points at an asset that exists',
        str_contains($html, str_replace('/', '\/', $scripts[0])));
}

DB::rollBack();

echo PHP_EOL . "=== nothing was left behind ===" . PHP_EOL;

check(sprintf('incomes  is still %d', $opening['incomes']), $opening['incomes'],
    DB::table('incomes')->count());
check(sprintf('expenses is still %d', $opening['expenses']), $opening['expenses'],
    DB::table('expenses')->count());
check(sprintf('entries  is still %d', $opening['entries']), $opening['entries'],
    DB::table('journal_entries')->count());

printf(PHP_EOL . "%d passed, %d failed" . PHP_EOL . PHP_EOL, $pass, $fail);

exit($fail === 0 ? 0 : 1);
