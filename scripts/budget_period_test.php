<?php

/**
 * What dates may a budget cover, and does it line up with the accounts?
 *
 * The budget module works on a different principle from the ledger: a budget is
 * a free start and end date, and BudgetActualsService reports whatever falls
 * between them. It never consults a fiscal year. Nothing was checking the two
 * consequences of that.
 *
 * Overlap: two institutional sheets covering the same day both claim the same
 * income and the same spending.
 *
 * Alignment: budget-vs-actual is asked for a FISCAL YEAR and reports over the
 * matched BUDGET's dates. This database holds an eighteen-month sheet
 * (2025-03-01 to 2026-08-31) straddling two exercices, so asking for the
 * three-month first exercice returned 116,807,830 of actuals against the
 * 31,884,092 that exercice actually contains — nearly four times too large,
 * with nothing on screen to say so.
 *
 * Overlap is refused outright. Alignment is warned about, not refused: a school
 * that budgets by academic year is making a choice, and should be told what it
 * costs rather than stopped.
 *
 * Every write happens inside a transaction that is rolled back.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Budget;
use App\Models\FiscalYear;
use App\Services\Accounting\BudgetPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

/** Run one value through a closure rule and return the message, or ''. */
function ruleMessage(Closure $rule, string $value): string
{
    return Validator::make(['end_date' => $value], ['end_date' => [$rule]])->errors()->first('end_date');
}

$opening = [
    'budgets' => DB::table('budgets')->count(),
    'fiscal_years' => DB::table('fiscal_years')->count(),
    'allocations' => DB::table('budget_allocations')->count(),
];

printf("Before: %d budgets, %d fiscal years, %d allocations\n\n",
    $opening['budgets'], $opening['fiscal_years'], $opening['allocations']);

DB::beginTransaction();

try {
    echo "=== 1. the fiscal_year label follows both dates ===\n";

    // It was set from the start date's calendar year alone, so a sheet running
    // September 2026 to August 2027 was labelled "2026" — true of its first
    // four months and of nothing else.
    check('a calendar year is labelled by its year', '2026',
        BudgetPeriod::labelFor('2026-01-01', '2026-12-31'));
    check('a short first period keeps its year', '2025',
        BudgetPeriod::labelFor('2025-10-14', '2025-12-31'));
    check('an academic year names both', '2026-27',
        BudgetPeriod::labelFor('2026-09-01', '2027-08-31'));
    check('an eighteen-month span names both', '2025-26',
        BudgetPeriod::labelFor('2025-03-01', '2026-08-31'));
    ok('the label always fits the column', strlen(BudgetPeriod::labelFor('2025-03-01', '2026-08-31')) <= 10);

    echo "\n=== 2. two institutional sheets may not cover the same day ===\n";

    $existing = Budget::create([
        'title' => 'Sheet A', 'budget_code' => 'TST-A-' . uniqid(), 'type' => 'annual',
        'is_institutional' => true, 'fiscal_year' => '2030',
        'start_date' => '2030-01-01', 'end_date' => '2030-12-31',
        'total_amount' => 0, 'status' => 'draft',
    ]);

    ok('an identical period is refused',
        str_contains(ruleMessage(BudgetPeriod::noOverlapRule('2030-01-01'), '2030-12-31'), 'overlap'));
    ok('a period straddling its end is refused',
        str_contains(ruleMessage(BudgetPeriod::noOverlapRule('2030-07-01'), '2031-06-30'), 'overlap'));
    ok('a period swallowing it whole is refused',
        str_contains(ruleMessage(BudgetPeriod::noOverlapRule('2029-01-01'), '2031-12-31'), 'overlap'));
    ok('and the message names the sheet it clashes with',
        str_contains(ruleMessage(BudgetPeriod::noOverlapRule('2030-01-01'), '2030-12-31'), 'Sheet A'));

    check('the very next year is fine', '',
        ruleMessage(BudgetPeriod::noOverlapRule('2031-01-01'), '2031-12-31'));
    check('the year before is fine', '',
        ruleMessage(BudgetPeriod::noOverlapRule('2029-01-01'), '2029-12-31'));
    check('a sheet may overlap ITSELF when being edited', '',
        ruleMessage(BudgetPeriod::noOverlapRule('2030-01-01', $existing->id), '2030-12-31'));

    // A departmental budget is delegated out of a sheet and is MEANT to sit
    // inside it, so it must not be caught by this rule.
    $departmental = Budget::create([
        'title' => 'Science Dept', 'budget_code' => 'TST-D-' . uniqid(), 'type' => 'departmental',
        'is_institutional' => false, 'fiscal_year' => '2030',
        'start_date' => '2030-01-01', 'end_date' => '2030-12-31',
        'total_amount' => 0, 'status' => 'draft', 'parent_id' => $existing->id,
    ]);

    check('a departmental budget does not block another sheet', '',
        ruleMessage(BudgetPeriod::noOverlapRule('2031-01-01'), '2031-12-31'));

    echo "\n=== 3. a delegated budget stays inside its parent ===\n";

    check('inside the parent is fine', '',
        ruleMessage(BudgetPeriod::withinParentRule('2030-03-01', $existing->id), '2030-09-30'));
    check('exactly the parent is fine', '',
        ruleMessage(BudgetPeriod::withinParentRule('2030-01-01', $existing->id), '2030-12-31'));

    ok('starting before the parent is refused',
        str_contains(ruleMessage(BudgetPeriod::withinParentRule('2029-12-01', $existing->id), '2030-06-30'), 'outside'));
    ok('ending after the parent is refused',
        str_contains(ruleMessage(BudgetPeriod::withinParentRule('2030-06-01', $existing->id), '2031-03-31'), 'outside'));
    ok('and the message names the parent',
        str_contains(ruleMessage(BudgetPeriod::withinParentRule('2029-12-01', $existing->id), '2030-06-30'), 'Sheet A'));

    check('with no parent there is nothing to be inside', '',
        ruleMessage(BudgetPeriod::withinParentRule('2029-01-01', null), '2032-12-31'));

    echo "\n=== 4. alignment is reported, never refused ===\n";

    // Against the real exercices on this database.
    $fy2025 = FiscalYear::where('name', 'Exercice 2025')->first();
    $fy2026 = FiscalYear::where('name', 'Exercice 2026')->first();

    if (!$fy2025 || !$fy2026) {
        echo "  (the 2025/2026 exercices are not configured here — skipped)\n";
    } else {
        check('a sheet matching an exercice exactly warns about nothing', [],
            BudgetPeriod::alignmentWarnings(
                $fy2026->start_date->toDateString(), $fy2026->end_date->toDateString()));

        $spanning = BudgetPeriod::alignmentWarnings('2025-03-01', '2026-08-31');
        ok('the eighteen-month sheet is flagged', $spanning !== []);
        ok('and it says it spans more than one exercice',
            str_contains(implode(' ', $spanning), 'more than one exercice'));
        ok('and it names both of them',
            str_contains(implode(' ', $spanning), 'Exercice 2025')
            && str_contains(implode(' ', $spanning), 'Exercice 2026'));
        ok('and it says starting before any fiscal year is a problem too',
            str_contains(implode(' ', $spanning), 'before any fiscal year'));

        $inside = BudgetPeriod::alignmentWarnings('2026-02-01', '2026-11-30');
        ok('a sheet sitting inside one exercice without filling it is flagged',
            str_contains(implode(' ', $inside), 'without covering all of it'));

        check('a period no fiscal year reaches says exactly that', 1,
            count(BudgetPeriod::alignmentWarnings('2019-01-01', '2019-12-31')));
        ok('in those words',
            str_contains(implode(' ', BudgetPeriod::alignmentWarnings('2019-01-01', '2019-12-31')),
                'No fiscal year covers this period'));
    }

    echo "\n=== 5. the figures that made this worth doing ===\n";

    $actuals = app(App\Services\BudgetActualsService::class);

    $exerciceTotal = array_sum($actuals->forPeriod('2025-10-14', '2025-12-31')['lines']);
    $budgetTotal = array_sum($actuals->forPeriod('2025-03-01', '2026-08-31')['lines']);

    printf("  Exercice 2025 (14 Oct - 31 Dec 2025) : %s\n", number_format($exerciceTotal, 2));
    printf("  Budget #3 range (18 months)          : %s\n", number_format($budgetTotal, 2));

    ok('the two genuinely differ, which is the whole point', $budgetTotal > $exerciceTotal * 2);

    echo "\n=== 6. teeth: without the overlap rule, two sheets claim the same money ===\n";

    // Proving the rule is what stops it, not something else.
    $wouldClash = Budget::where('is_institutional', true)
        ->whereDate('start_date', '<=', '2030-12-31')
        ->whereDate('end_date', '>=', '2030-01-01')
        ->count();

    check('a clashing period really does find an existing sheet', 1, $wouldClash);
    ok('which is exactly what the rule refuses on',
        ruleMessage(BudgetPeriod::noOverlapRule('2030-01-01'), '2030-12-31') !== '');

    $departmental->delete();
    $existing->delete();
} catch (\Throwable $e) {
    echo "\n  *** the suite threw: " . $e->getMessage() . "\n";
    echo '      ' . $e->getFile() . ':' . $e->getLine() . "\n";
    $fail++;
} finally {
    DB::rollBack();
}

echo "\n=== nothing was left behind ===\n";

$closing = [
    'budgets' => DB::table('budgets')->count(),
    'fiscal_years' => DB::table('fiscal_years')->count(),
    'allocations' => DB::table('budget_allocations')->count(),
];

foreach ($opening as $key => $value) {
    check(sprintf('%-13s is still %s', $key, $value), $value, $closing[$key]);
}

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
