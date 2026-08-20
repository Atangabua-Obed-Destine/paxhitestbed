<?php
/**
 * The Budget Lines screen: budget structure is configurable, and safely so.
 *
 * The lines used to come only from a seeder, so changing the sheet needed a
 * developer. What matters here is not that editing works but that it cannot
 * quietly destroy money: a line something references must never be deletable,
 * because the sheet↔ledger reconciliation depends on those references resolving.
 *
 * Every mutation runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/budget_line_admin_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BudgetLine;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

$admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?: User::first();
Auth::guard('web')->login($admin);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

function call(string $uri, string $method = 'GET', array $params = []): array
{
    global $kernel;

    // Post a real CSRF token rather than disabling the middleware: the point is
    // to exercise the same stack a browser hits, guards included.
    $session = app('session.store');
    if ($method !== 'GET') {
        $params['_token'] = $session->token();
    }

    $request = Illuminate\Http\Request::create($uri, $method, $params);
    $request->setLaravelSession($session);
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), $response->getContent(), $response];
}

echo "\n" . str_repeat('=', 62) . "\n";
echo "Budget Lines management screen\n";
echo str_repeat('=', 62) . "\n";

// ---------------------------------------------------------------------------
echo "\nThe screen exists and shows the structure\n";
// ---------------------------------------------------------------------------

[$code, $body] = call('/admin/budget-line');
check('screen loads', $code === 200, "HTTP $code");

$total = BudgetLine::count();
check("every line is listed ($total)", substr_count($body, 'bl-code') >= $total, substr_count($body, 'bl-code') . ' rendered');

foreach (['Income', 'Expenditure', 'Capital'] as $section) {
    check("the $section section is shown", strpos($body, $section) !== false);
}

check('lines show the chart account they post to', strpos($body, 'bl-account') !== false);
check('diocesan and local lines are told apart', strpos($body, 'bl-flag-local') !== false && strpos($body, 'bl-flag-standard') !== false);
check('the screen explains what a budget line is', strpos($body, 'not ledger accounts') !== false || strpos($body, 'What these are') !== false);

// ---------------------------------------------------------------------------
echo "\nPermissions exist and gate the screen\n";
// ---------------------------------------------------------------------------

$perms = ['budget-line-view', 'budget-line-create', 'budget-line-edit', 'budget-line-delete'];
check('all four permissions exist', Permission::whereIn('name', $perms)->count() === 4);

// ---------------------------------------------------------------------------
echo "\nAdding a line\n";
// ---------------------------------------------------------------------------

DB::beginTransaction();
$before = BudgetLine::count();
[$code] = call('/admin/budget-line/store', 'POST', [
    'code' => 'ZZ1',
    'name' => 'Test line',
    'section' => 'expenditure',
    'sort_order' => 900,
]);
$new = BudgetLine::where('code', 'ZZ1')->first();
check('a new line is created', $new !== null, "HTTP $code");
check('it is marked as a local addition', $new && $new->is_local === true);
check('it appears on the sheet by default', $new && $new->status === true);
check('the count went up by one', BudgetLine::count() === $before + 1);

// A line nothing references may be deleted.
if ($new) {
    [$code] = call('/admin/budget-line/' . $new->id . '/delete', 'POST');
    check('an unused line can be deleted', BudgetLine::where('code', 'ZZ1')->doesntExist(), "HTTP $code");
}
DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nDuplicate codes and bad input are refused\n";
// ---------------------------------------------------------------------------

DB::beginTransaction();
$existing = BudgetLine::first();
$before = BudgetLine::count();
try {
    call('/admin/budget-line/store', 'POST', [
        'code' => $existing->code,
        'name' => 'Duplicate code',
        'section' => 'expenditure',
    ]);
} catch (\Throwable $e) {
    // A validation failure is what we want; how it surfaces does not matter.
}
check('a duplicate code is refused', BudgetLine::count() === $before);

try {
    call('/admin/budget-line/store', 'POST', ['code' => 'ZZ2', 'name' => 'X', 'section' => 'nonsense']);
} catch (\Throwable $e) {
}
check('an unknown section is refused', BudgetLine::where('code', 'ZZ2')->doesntExist());
DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nLines that money references cannot be deleted\n";
// ---------------------------------------------------------------------------

// This is the guard that matters: 66 lines carry budgeted figures and 24 are
// reached by category mappings. Deleting one orphans money and breaks the
// reconciliation between the sheet and the ledger.
$referenced = DB::table('budget_allocations')->whereNotNull('budget_line_id')->value('budget_line_id');
$line = BudgetLine::find($referenced);
check('a line carrying budgeted figures was found to test with', $line !== null);

if ($line) {
    DB::beginTransaction();
    [$code] = call('/admin/budget-line/' . $line->id . '/delete', 'POST');
    check('deleting it is refused', BudgetLine::whereKey($line->id)->exists(), "HTTP $code");
    DB::rollBack();
}

$mapped = DB::table('default_account_mappings')->whereNotNull('budget_line_id')->value('budget_line_id');
if ($mapped) {
    DB::beginTransaction();
    call('/admin/budget-line/' . $mapped . '/delete', 'POST');
    check('a line a category maps to is protected too', BudgetLine::whereKey($mapped)->exists());
    DB::rollBack();
}

// ---------------------------------------------------------------------------
echo "\nRetiring is the safe alternative to deleting\n";
// ---------------------------------------------------------------------------

if ($line) {
    DB::beginTransaction();
    call('/admin/budget-line/' . $line->id . '/toggle', 'POST');
    $line->refresh();
    check('a line can be retired', $line->status === false);
    check('retiring does not remove it', BudgetLine::whereKey($line->id)->exists());
    check('a retired line drops off the sheet', !BudgetLine::sheet()->contains('id', $line->id));

    call('/admin/budget-line/' . $line->id . '/toggle', 'POST');
    $line->refresh();
    check('and it can be put back', $line->status === true);
    DB::rollBack();
}

// ---------------------------------------------------------------------------
echo "\nEditing keeps the structure sound\n";
// ---------------------------------------------------------------------------

DB::beginTransaction();
$edit = BudgetLine::where('is_local', true)->where('is_header', false)->first();
if ($edit) {
    $originalName = $edit->name;
    call('/admin/budget-line/' . $edit->id . '/update', 'POST', [
        'code' => $edit->code,
        'name' => 'Renamed for the test',
        'section' => $edit->section,
        'sort_order' => $edit->sort_order,
        'parent_id' => $edit->parent_id,
    ]);
    $edit->refresh();
    check('a line can be renamed', $edit->name === 'Renamed for the test', $edit->name);
    check('renaming does not change where it came from', $edit->is_local === true);
}

// A line cannot be made its own parent.
$loop = BudgetLine::where('is_header', false)->first();
call('/admin/budget-line/' . $loop->id . '/update', 'POST', [
    'code' => $loop->code,
    'name' => $loop->name,
    'section' => $loop->section,
    'parent_id' => $loop->id,
]);
$loop->refresh();
check('a line cannot be its own parent', (int) $loop->parent_id !== (int) $loop->id);
DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nNothing was disturbed\n";
// ---------------------------------------------------------------------------

check('line count is unchanged', BudgetLine::count() === $total, BudgetLine::count() . ' vs ' . $total);
check('no line was left retired', BudgetLine::where('status', false)->count() === 0);

$recon = app(App\Services\BudgetReconciliationService::class)->reconcile();
check('the sheet still reconciles with the ledger', $recon['agrees'] === true);

echo "\n" . str_repeat('-', 62) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
