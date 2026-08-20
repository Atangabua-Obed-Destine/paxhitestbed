<?php
/**
 * End-to-end test of the Income & Expenditure sheet.
 *
 * Creates a sheet, types figures into it, and checks the arithmetic the finance
 * office will rely on: headers total their children, sections total their
 * lines, the closing balance follows the paper's own formula, and the cash
 * reconciliation resolves the two items that make that balance not-cash.
 *
 * Everything is rolled back.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\BudgetSheetController;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$results = [];
function check($n, $p, $d = '') { global $results; $results[] = [$n, $p, $d]; }

$req = Request::create(url('/admin'), 'GET');
$req->setLaravelSession(app('session.store'));
app()->instance('request', $req);
view()->share('errors', new Illuminate\Support\ViewErrorBag);
Auth::guard('web')->login(User::query()->first());

$controller = app(BudgetSheetController::class);
$byCode = BudgetLine::pluck('id', 'code');

DB::beginTransaction();
try {
    // ---- create ---------------------------------------------------------
    $create = Request::create('/admin/budget-sheet/store', 'POST', [
        'title' => 'TEST 2025/2026',
        'start_date' => '2025-10-01',
        'end_date' => '2026-07-31',
        'opening_balance' => 1000000,
    ]);
    $create->setLaravelSession(app('session.store'));
    app()->instance('request', $create);
    $controller->store($create);

    $budget = Budget::where('title', 'TEST 2025/2026')->first();
    check('a sheet can be created', $budget !== null);
    check('it is marked institutional', $budget && $budget->is_institutional);
    check('opening balance stored', (float) $budget->opening_balance === 1000000.0);

    // ---- enter figures --------------------------------------------------
    // Includes an income line, which was impossible before: budget_allocations
    // could only ever reference an expense category.
    $amounts = [
        $byCode['610'] => 5000000,   // income — tuition business
        $byCode['611'] => 10000000,  // income — tuition health
        $byCode['401'] => 800000,    // expenditure — travelling
        $byCode['404'] => 1500000,   // expenditure — stationery
        $byCode['501'] => 300000,    // depreciation — non-cash
        $byCode['542'] => 400000,    // capital — real cash out
        $byCode['400'] => 999999,    // header — must be ignored
    ];

    $save = Request::create("/admin/budget-sheet/{$budget->id}/figures", 'POST', [
        'opening_balance' => 1000000,
        'amounts' => $amounts,
    ]);
    $save->setLaravelSession(app('session.store'));
    app()->instance('request', $save);
    $controller->saveFigures($save, $budget->id);

    check('an income line can now be budgeted',
        BudgetAllocation::where('budget_id', $budget->id)
            ->where('budget_line_id', $byCode['610'])->value('allocated_amount') == 5000000);

    check('a figure typed against a header is ignored',
        BudgetAllocation::where('budget_id', $budget->id)
            ->where('budget_line_id', $byCode['400'])->doesntExist(),
        'header allocation was stored');

    check('expenditure figures stored',
        BudgetAllocation::where('budget_id', $budget->id)
            ->where('budget_line_id', $byCode['401'])->value('allocated_amount') == 800000);

    // ---- the arithmetic --------------------------------------------------
    $view = $controller->show($req, $budget->id);
    $data = $view->getData();
    $t = $data['totals'];

    check('total income equals its lines', $t['budget']['income'] == 15000000.0,
        number_format($t['budget']['income']));
    check('total expenditure equals its lines', $t['budget']['expenditure'] == 2600000.0,
        number_format($t['budget']['expenditure']));
    check('capital is excluded from expenditure', $t['budget']['capital'] == 400000.0,
        number_format($t['budget']['capital']));

    // Opening + income − expenditure, exactly as the paper does it.
    check('closing balance follows the paper formula',
        $t['budget']['closing'] == 1000000 + 15000000 - 2600000,
        number_format($t['budget']['closing']));

    check('depreciation is picked up for the reconciliation',
        $t['budget']['depreciation'] == 300000.0, number_format($t['budget']['depreciation']));

    // Closing + depreciation back − capital out.
    check('cash reconciliation adds back depreciation and removes capital',
        $t['budget']['cash'] == $t['budget']['closing'] + 300000 - 400000,
        number_format($t['budget']['cash']));

    check('the cash position differs from the closing balance',
        $t['budget']['cash'] != $t['budget']['closing'],
        'they matched, so the reconciliation is doing nothing');

    // ---- headers roll up -------------------------------------------------
    $budgeted = $data['budgeted'];
    check('header 400 totals its children',
        ($budgeted[$byCode['400']] ?? 0) == 2300000.0,
        number_format($budgeted[$byCode['400']] ?? 0) . ' (401 + 404)');

    // ---- actuals appear beside the budget --------------------------------
    $actual = $data['actual'];
    check('actual tuition appears against the health line',
        ($actual[$byCode['611']] ?? 0) > 0,
        number_format($actual[$byCode['611']] ?? 0));
    check('actual salaries appear on 444',
        ($actual[$byCode['444']] ?? 0) > 0,
        number_format($actual[$byCode['444']] ?? 0));

    // ---- clearing a figure removes it ------------------------------------
    $clear = Request::create("/admin/budget-sheet/{$budget->id}/figures", 'POST', [
        'opening_balance' => 1000000,
        'amounts' => [$byCode['401'] => ''],
    ]);
    $clear->setLaravelSession(app('session.store'));
    app()->instance('request', $clear);
    $controller->saveFigures($clear, $budget->id);
    check('clearing a figure deletes the allocation',
        BudgetAllocation::where('budget_id', $budget->id)
            ->where('budget_line_id', $byCode['401'])->doesntExist());

    // ---- the screen renders ----------------------------------------------
    $html = $controller->show($req, $budget->id)->render();
    check('the sheet renders', strlen($html) > 20000, strlen($html) . ' bytes');
    check('it shows the reconciliation footer', str_contains($html, 'Expected cash'));
    check('it shows every section', str_contains($html, 'Capital Expenditure Accounts'));
    check('index renders', strlen($controller->index()->render()) > 10000);
} finally {
    DB::rollBack();
}

$failed = 0;
foreach ($results as [$n, $p, $d]) {
    if (!$p) { $failed++; }
    printf("%s  %s%s\n", $p ? 'PASS' : 'FAIL', $n, (!$p && $d) ? "   [$d]" : '');
}
printf("\n%d/%d passed  (rolled back)\n", count($results) - $failed, count($results));
exit($failed ? 1 : 0);
