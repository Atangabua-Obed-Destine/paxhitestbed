<?php

/**
 * The ledger health screen.
 *
 * The command does the same work, but a command is no use to an accountant on
 * shared hosting, which is the whole reason this screen exists. The one thing
 * that must never be true of it is that it gives a different answer from the
 * command about the same books — so that is what most of this checks.
 *
 * Read-only: the screen only renders, and the closing section proves the
 * database is as it was.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\LedgerHealthController;
use App\Services\Accounting\LedgerHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) {
        $pass++;

        return;
    }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

function ok(string $what, bool $condition): void
{
    check($what, true, $condition);
}

Auth::guard('web')->login(App\User::first());
View::share('errors', new Illuminate\Support\ViewErrorBag());

$opening = [
    'entries' => DB::table('journal_entries')->count(),
    'mappings' => DB::table('transaction_mappings')->count(),
];

$health = app(LedgerHealth::class);
$controller = app(LedgerHealthController::class);

echo "=== 1. the screen renders, in whatever state the books are in ===\n";

$view = $controller->index(new Request(), $health);
$html = $view->render();
$data = $view->getData();

ok('it renders', strlen($html) > 1000);
check('every check is accounted for', count($health->checks()),
    count($data['findings']) + count($data['passing']));

echo "\n=== 2. it says exactly what the command says ===\n";

$fromService = $health->checks();

check('the same number of checks', count($fromService), count($data['checks']));
check('the same verdict', $health->worstState($fromService), $data['worst']);
check('the same number of concerns', $health->concerns($fromService), $data['concerns']);

// Key by key, so a screen that quietly reordered or dropped one is caught.
check('the same checks, in the same order',
    array_column($fromService, 'key'), array_column($data['checks'], 'key'));

$byKey = array_column($fromService, null, 'key');

foreach ($data['checks'] as $shown) {
    if (($byKey[$shown['key']]['count'] ?? null) !== $shown['count']) {
        check('count matches for ' . $shown['key'], $byKey[$shown['key']]['count'] ?? null, $shown['count']);
    }
}

$pass++; // the loop above only records mismatches

echo "\n=== 3. the findings the books actually have are on the page ===\n";

foreach ($data['findings'] as $finding) {
    ok('the page names "' . $finding['label'] . '"', str_contains($html, $finding['label']));
}

// The figures, not just the words.
$unposted = $byKey['source_unposted'] ?? null;

if ($unposted && $unposted['count'] > 0) {
    ok('the unposted total appears on the page',
        str_contains($html, number_format($unposted['value'], 2)));
    ok('and the offending rows are listed',
        str_contains($html, (string) ($unposted['rows'][0]->id ?? 'none')));
}

echo "\n=== 4. the group filter narrows without lying ===\n";

foreach (['Mappings', 'Entries', 'Agreement', 'Placement'] as $group) {
    $filtered = $controller->index(new Request(['group' => $group]), $health);
    $shown = $filtered->getData()['checks'];

    $expected = array_values(array_filter($fromService, fn ($c) => $c['group'] === $group));

    check(sprintf('%-10s shows only its own checks', $group),
        array_column($expected, 'key'), array_column($shown, 'key'));

    ok($group . ' renders', strlen($filtered->render()) > 1000);
}

// A group nobody has falls back to everything rather than to an empty page.
$nonsense = $controller->index(new Request(['group' => 'nonsense']), $health);
check('an unknown group shows everything rather than nothing',
    count($fromService), count($nonsense->getData()['checks']));

echo "\n=== 5. it is behind a permission ===\n";

$middleware = $controller->getMiddleware();
$names = array_map(fn ($m) => is_string($m['middleware']) ? $m['middleware'] : '', $middleware);

ok('the controller requires a permission',
    collect($names)->contains(fn ($n) => str_starts_with($n, 'permission:')));
ok('and it is one an existing installation already has',
    collect($names)->contains('permission:general-ledger-view'));

check('that permission exists in this database', true,
    DB::table('permissions')->where('name', 'general-ledger-view')->exists());

echo "\n=== 6. the route is reachable and named ===\n";

$route = collect(app('router')->getRoutes())->first(
    fn ($r) => $r->getName() === 'admin.accounting.ledger-health');

ok('the route is registered', $route !== null);
check('at the expected path', 'admin/accounting/ledger-health', $route?->uri());
ok('as a GET', in_array('GET', $route?->methods() ?? [], true));

// Declared before the journal-entries resource, or "ledger-health" would be
// read as a journal entry id.
$routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri())->values()->all();
$healthAt = array_search('admin/accounting/ledger-health', $routes, true);
$showAt = array_search('admin/journal-entries/{journal_entry}', $routes, true);

if ($healthAt !== false && $showAt !== false) {
    ok('and declared before journal-entries/{id}', $healthAt < $showAt);
}

echo "\n=== 7. the sidebar offers it ===\n";

$sidebar = view('admin.layouts.inc.sidebar')->render();

ok('the sidebar links to it', str_contains($sidebar, 'ledger-health'));
ok('under a readable name', str_contains($sidebar, 'Ledger health'));

echo "\n=== nothing was left behind ===\n";

$closing = [
    'entries' => DB::table('journal_entries')->count(),
    'mappings' => DB::table('transaction_mappings')->count(),
];

foreach ($opening as $key => $value) {
    check(sprintf('%-9s is still %s', $key, $value), $value, $closing[$key]);
}

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
