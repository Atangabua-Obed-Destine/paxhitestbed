<?php
/**
 * The annual sheet and the budget register are one system.
 *
 * Checks the three properties that make that safe: the departmental editors
 * refuse a sheet, delegation to a sub-budget shows on the parent line, and an
 * annual budget never distorts the departmental figures beside it.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Budget, BudgetAllocation, BudgetLine, ExpenseCategory};
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$req = Request::create(url('/admin'), 'GET');
$req->setLaravelSession(app('session.store'));
app()->instance('request', $req);
view()->share('errors', new Illuminate\Support\ViewErrorBag);
Auth::guard('web')->login(User::query()->first());

$results = [];
function check($n, $p, $d = '') { global $results; $results[] = [$n, $p, $d]; }

$sheetC = app(App\Http\Controllers\Admin\BudgetSheetController::class);
$budgetC = app(App\Http\Controllers\Admin\BudgetController::class);
$dashC = app(App\Http\Controllers\Admin\BudgetDashboardController::class);

DB::beginTransaction();
try {
    $stationery = BudgetLine::where('code', '404')->first();
    $category = ExpenseCategory::where('title', 'STATIONERY')->first();

    // --- an annual sheet with a figure on 404 --------------------------
    $annual = Budget::create([
        'title' => 'REGISTER TEST', 'type' => 'annual', 'is_institutional' => true,
        'start_date' => '2025-10-01', 'end_date' => '2026-07-31',
        'total_amount' => 0, 'status' => 'active', 'fiscal_year' => '2025',
    ]);
    BudgetAllocation::create([
        'budget_id' => $annual->id, 'budget_line_id' => $stationery->id,
        'title' => 'Stationery', 'allocated_amount' => 2000000,
    ]);

    // --- a sub-budget delegated out of it ------------------------------
    $child = Budget::create([
        'title' => 'REGISTRY STATIONERY', 'type' => 'departmental', 'is_institutional' => false,
        'parent_id' => $annual->id,
        'start_date' => '2025-10-01', 'end_date' => '2026-07-31',
        'total_amount' => 500000, 'status' => 'active', 'fiscal_year' => '2025',
    ]);
    BudgetAllocation::create([
        'budget_id' => $child->id, 'expense_category_id' => $category->id,
        'title' => 'Stationery', 'allocated_amount' => 500000,
    ]);

    check('a sub-budget records its parent', $child->fresh()->parent_id === $annual->id);
    check('the parent lists it as a child', $annual->fresh()->children->count() === 1);

    // The delegated figure is derived, never typed twice.
    $data = $sheetC->show($req, $annual->id)->getData();
    check('the sheet knows how much of the line is delegated',
        ($data['delegated'][$stationery->id] ?? 0) == 500000.0,
        number_format($data['delegated'][$stationery->id] ?? 0));

    $html = $sheetC->show($req, $annual->id)->render();
    check('the sheet shows it on the line', str_contains($html, 'of which'));
    check('and does not warn while it is within budget', !str_contains($html, 'more delegated than budgeted'));

    // Over-delegation must be visible.
    $child->update(['total_amount' => 3000000]);
    BudgetAllocation::where('budget_id', $child->id)->update(['allocated_amount' => 3000000]);
    $over = $sheetC->show($req, $annual->id)->render();
    check('delegating more than the line holds is flagged',
        str_contains($over, 'more delegated than budgeted'));

    // --- the register shows both, distinctly ---------------------------
    $register = $budgetC->index($req)->render();
    check('the register lists the annual sheet', str_contains($register, 'REGISTER TEST'));
    check('the register lists the sub-budget', str_contains($register, 'REGISTRY STATIONERY'));
    check('the annual one is labelled distinctly', str_contains($register, 'Annual (Institutional)'));

    // --- the detail page points at the sheet, not the departmental form -
    $detail = $budgetC->show($annual->id)->render();
    check('the annual detail page offers the sheet', str_contains($detail, 'Open the sheet'));
    check('it does not offer the departmental editor',
        !str_contains($detail, 'budget/' . $annual->id . '/edit'));
    check('it lists the delegated sub-budgets', str_contains($detail, 'REGISTRY STATIONERY'));

    $childDetail = $budgetC->show($child->id)->render();
    check('the sub-budget names what it belongs to', str_contains($childDetail, 'REGISTER TEST'));

    // --- the annual budget must not distort departmental figures -------
    //
    // Measured by removing the annual budget from the picture and putting it
    // back: the departmental KPIs must be identical either way. Comparing
    // before and after the *sub-budget* would prove nothing, since a
    // departmental budget belongs in those figures.
    $withAnnual = $dashC->index()->getData()['kpis'];
    $annual->update(['status' => 'cancelled']);
    $withoutAnnual = $dashC->index()->getData()['kpis'];
    $annual->update(['status' => 'active']);

    check('departmental utilisation ignores the annual budget',
        $withAnnual['utilization_rate'] === $withoutAnnual['utilization_rate'],
        $withAnnual['utilization_rate'] . ' vs ' . $withoutAnnual['utilization_rate']);
    check('departmental totals ignore the annual budget',
        $withAnnual['total_budget'] === $withoutAnnual['total_budget'],
        number_format($withAnnual['total_budget']) . ' vs ' . number_format($withoutAnnual['total_budget']));
    check('the departmental count ignores it too',
        $withAnnual['active_budgets_count'] === $withoutAnnual['active_budgets_count'],
        $withAnnual['active_budgets_count'] . ' vs ' . $withoutAnnual['active_budgets_count']);
    check('the sub-budget itself does count as departmental',
        $withAnnual['active_budgets_count'] >= 1, (string) $withAnnual['active_budgets_count']);

    $dash = $dashC->index();
    check('the dashboard carries the annual budget separately',
        $dash->getData()['annualBudget'] !== null);
    check('the annual card renders', str_contains($dash->render(), 'Annual budget'));
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
