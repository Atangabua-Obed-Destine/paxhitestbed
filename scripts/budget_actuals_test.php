<?php
/**
 * Does the actuals resolver account for every franc, exactly once?
 *
 * The resolver is the single place that decides where real money lands on the
 * sheet, so these checks are the guarantee behind every figure a report shows.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BudgetLine;
use App\Services\BudgetActualsService;
use Illuminate\Support\Facades\DB;

$results = [];
function check($n, $p, $d = '') { global $results; $results[] = [$n, $p, $d]; }

$service = new BudgetActualsService();
$actuals = $service->forPeriod();
$lines = $actuals['lines'];
$unallocated = $actuals['unallocated'];

$byCode = BudgetLine::pluck('id', 'code');
$amount = fn (string $code) => $lines[$byCode[$code] ?? 0] ?? 0.0;

// ---- nothing is lost -----------------------------------------------------
$expenseSource = (float) DB::table('expenses')->sum('amount');
$incomeSource = (float) DB::table('incomes')->sum('amount');
$feeSource = (float) DB::table('fees')->sum('paid_amount');
$resolved = array_sum($lines) + array_sum($unallocated);

check('every franc is accounted for',
    abs(($expenseSource + $incomeSource + $feeSource) - $resolved) < 0.01,
    number_format($resolved) . ' resolved of ' . number_format($expenseSource + $incomeSource + $feeSource));

check('nothing is unallocated', empty($unallocated), json_encode($unallocated));

// ---- expenditure ---------------------------------------------------------
check('expenditure total matches the source',
    abs($service->sectionTotal($lines, 'expenditure') + $service->sectionTotal($lines, 'capital') - $expenseSource) < 0.01,
    number_format($service->sectionTotal($lines, 'expenditure') + $service->sectionTotal($lines, 'capital'))
        . ' vs ' . number_format($expenseSource));

// ---- income: fees plus recorded income, each counted once ----------------
check('income total matches fees plus recorded income',
    abs($service->sectionTotal($lines, 'income') - ($incomeSource + $feeSource)) < 0.01,
    number_format($service->sectionTotal($lines, 'income')) . ' vs ' . number_format($incomeSource + $feeSource));

// ---- the faculty split ---------------------------------------------------
$tuitionByLine = $amount('610') + $amount('611') + $amount('612') + $amount('613');
$tuitionSource = (float) DB::table('fees as f')
    ->join('fees_categories as c', 'c.id', '=', 'f.category_id')
    ->where('c.is_admission', 0)->where('c.is_resit', 0)
    ->sum('f.paid_amount');
check('tuition lines sum to total tuition',
    abs($tuitionByLine - $tuitionSource) < 0.01,
    number_format($tuitionByLine) . ' vs ' . number_format($tuitionSource));

// Each school's line must equal what that school actually collected.
foreach (BudgetLine::whereNotNull('faculty_id')->get() as $line) {
    $expected = (float) DB::table('fees as f')
        ->join('fees_categories as c', 'c.id', '=', 'f.category_id')
        ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
        ->join('programs as p', 'p.id', '=', 'se.program_id')
        ->where('p.faculty_id', $line->faculty_id)
        ->where('c.is_admission', 0)->where('c.is_resit', 0)
        ->sum('f.paid_amount');

    check("line {$line->code} equals its school's tuition",
        abs(($lines[$line->id] ?? 0) - $expected) < 0.01,
        number_format($lines[$line->id] ?? 0) . ' vs ' . number_format($expected));
}

// The bug this replaced: everything landing on 610.
check('tuition is no longer all on line 610',
    $amount('611') > 0 && $amount('612') > 0 && $amount('613') > 0,
    '611=' . number_format($amount('611')) . ' 612=' . number_format($amount('612')));

// ---- resit is separate, not folded into tuition --------------------------
$resitSource = (float) DB::table('fees as f')
    ->join('fees_categories as c', 'c.id', '=', 'f.category_id')
    ->where('c.is_resit', 1)->sum('f.paid_amount');
check('resit fees report on their own line',
    abs($amount('614') - $resitSource) < 0.01,
    number_format($amount('614')) . ' vs ' . number_format($resitSource));

// ---- headers total their children, without double counting ---------------
$withHeaders = $service->withHeaderTotals($lines);
foreach (BudgetLine::where('is_header', true)->get() as $header) {
    $childSum = 0.0;
    foreach (BudgetLine::where('parent_id', $header->id)->pluck('id') as $childId) {
        $childSum += $lines[$childId] ?? 0;
    }
    check("header {$header->code} equals the sum of its children",
        abs(($withHeaders[$header->id] ?? 0) - $childSum) < 0.01,
        number_format($withHeaders[$header->id] ?? 0) . ' vs ' . number_format($childSum));
}

check('section totals exclude headers, so nothing counts twice',
    abs($service->sectionTotal($withHeaders, 'expenditure') - $service->sectionTotal($lines, 'expenditure')) < 0.01);

// ---- date filtering ------------------------------------------------------
$narrow = $service->forPeriod('2025-10-01', '2025-10-31');
check('a date range narrows the result',
    array_sum($narrow['lines']) > 0 && array_sum($narrow['lines']) < array_sum($lines),
    number_format(array_sum($narrow['lines'])) . ' in October alone');

$none = $service->forPeriod('2000-01-01', '2000-01-31');
check('a period with no activity returns nothing',
    array_sum($none['lines']) == 0.0 && empty($none['unallocated']));

// ---- unmapped money must surface, not vanish -----------------------------
DB::beginTransaction();
try {
    $orphanCategory = App\Models\ExpenseCategory::create(['title' => 'ZZ TEST ORPHAN', 'status' => 1]);
    App\Models\Expense::create([
        'category_id' => $orphanCategory->id, 'title' => 'orphan', 'amount' => 12345, 'date' => date('Y-m-d'),
    ]);
    $withOrphan = $service->forPeriod();
    check('spending with no mapping is reported as unallocated, not dropped',
        ($withOrphan['unallocated']['expenses'] ?? 0) == 12345.0,
        json_encode($withOrphan['unallocated']));
} finally {
    DB::rollBack();
}

$failed = 0;
foreach ($results as [$n, $p, $d]) {
    if (!$p) { $failed++; }
    printf("%s  %s%s\n", $p ? 'PASS' : 'FAIL', $n, (!$p && $d) ? "   [$d]" : '');
}
printf("\n%d/%d passed\n", count($results) - $failed, count($results));
exit($failed ? 1 : 0);
