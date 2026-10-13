<?php

/**
 * The role edit screen, which could not be opened at all.
 *
 * Two faults met on one page:
 *
 *   - It asked "is this one of the role's permissions?" by walking every
 *     assigned permission for every permission on the page. With 781 of each
 *     that is 610,000 iterations, and the whitespace each one emitted built a
 *     42MB page. On a 128MB memory limit PHP died returning it, so the screen
 *     was a 500 with "Allowed memory size exhausted" and no useful trace.
 *   - A role id that does not exist returned null from find(), and the view
 *     read ->id straight off it. A stale link gave a 500 where 404 is the
 *     honest answer.
 *
 * So size is asserted, not only correctness: a page that renders the right
 * checkboxes is still broken if it cannot be delivered.
 *
 * Nothing is written.
 *
 *   php scripts/role_edit_page_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

/**
 * What PHP is usually given on shared hosting, and what this page has to fit
 * inside. The page was 42MB; the limit was 128MB; it died on the output buffer.
 */
const TYPICAL_MEMORY_LIMIT_MB = 128;

/** Comfortably under anything a browser or a proxy will object to. */
const MAX_REASONABLE_PAGE_MB = 3;

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    echo "no Super Admin to render as\n";
    exit(0);
}

Auth::guard('web')->login($admin);

function rolePage(int $id): array
{
    global $kernel;

    $request = Request::create('/admin/setting/role/' . $id . '/edit', 'GET');
    $request->setLaravelSession(app('session.store'));

    $response = $kernel->handle($request);

    return ['status' => $response->getStatusCode(), 'html' => $response->getContent()];
}

/** The permission ids actually ticked in the rendered HTML. */
function tickedIds(string $html): array
{
    preg_match_all('/id="checkbox-(\d+)"[^>]*checked>/', $html, $matches);

    $ids = array_map('intval', $matches[1]);
    sort($ids);

    return $ids;
}

$permissionCount = Permission::count();

// The role holding the most permissions: the worst case, and the one that
// actually fell over.
$heaviest = Role::withCount('permissions')->orderByDesc('permissions_count')->first();

if (!$heaviest || $permissionCount === 0) {
    echo "no roles or permissions to render\n";
    exit(0);
}

echo $permissionCount . " permissions; heaviest role is "
    . $heaviest->name . ' with ' . $heaviest->permissions_count . "\n";

// ---------------------------------------------------------------------------
section('The page is small enough to be delivered');
// ---------------------------------------------------------------------------
$page = rolePage($heaviest->id);
$megabytes = strlen($page['html']) / 1048576;

check('the heaviest role\'s page renders at all',
    $page['status'] === 200,
    'status ' . $page['status']);

check('and is a sane size',
    $megabytes < MAX_REASONABLE_PAGE_MB,
    sprintf('%.2f MB — it was 40MB, which is what exhausted PHP\'s memory', $megabytes));

check('it would fit well inside a 128MB memory limit',
    $megabytes < TYPICAL_MEMORY_LIMIT_MB / 8,
    sprintf('%.2f MB; the buffer alone has to be allocated twice over to be returned', $megabytes));

// The page is rendered by a loop over every permission; if that ever became
// quadratic again the size would climb with the role's permission count rather
// than staying flat. Compared against a role holding far fewer.
$lightest = Role::withCount('permissions')->orderBy('permissions_count')->first();

if (!$lightest || $lightest->id === $heaviest->id
    || $lightest->permissions_count === $heaviest->permissions_count) {
    skip('no second role with a different number of permissions to compare');
} else {
    $lightPage = rolePage($lightest->id);
    $lightMb = strlen($lightPage['html']) / 1048576;
    $ratio = $lightMb > 0 ? $megabytes / $lightMb : 0;

    check('page size does not grow with how many permissions the role holds',
        $ratio < 1.5,
        sprintf('%s (%d perms) is %.2f MB but %s (%d perms) is %.2f MB — %.1fx. '
            . 'Size tracking the role\'s own count is the signature of the nested loop.',
            $lightest->name, $lightest->permissions_count, $lightMb,
            $heaviest->name, $heaviest->permissions_count, $megabytes, $ratio));
}

// ---------------------------------------------------------------------------
section('It ticks exactly the right boxes');
// ---------------------------------------------------------------------------
// Size is easy to get right by rendering nothing, so what is on the page is
// checked against the role's real permissions, as sets rather than counts.
foreach ([$heaviest, $lightest] as $role) {
    if (!$role) {
        continue;
    }

    $html = rolePage($role->id)['html'];
    $ticked = tickedIds($html);
    $actual = $role->permissions->pluck('id')->sort()->values()->all();

    check('"' . $role->name . '" has exactly its own permissions ticked',
        $ticked === $actual,
        count($ticked) . ' ticked vs ' . count($actual) . ' held');

    check('and every permission in the system is offered on the page',
        substr_count($html, 'type="checkbox"') >= $permissionCount,
        substr_count($html, 'type="checkbox"') . ' checkboxes for ' . $permissionCount . ' permissions');
}

// ---------------------------------------------------------------------------
section('A role that does not exist is a 404, not a crash');
// ---------------------------------------------------------------------------
$missing = (int) Role::max('id') + 1000;

check('an unknown role id gives 404',
    rolePage($missing)['status'] === 404,
    'find() returned null and the view read ->id off it, which is a 500 and an '
        . 'error log entry for what is really just a stale link');

check('and does not render a half-built page',
    strlen(rolePage($missing)['html']) < 1048576,
    'the error page should be an error page');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
