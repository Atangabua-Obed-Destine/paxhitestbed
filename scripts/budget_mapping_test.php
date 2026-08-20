<?php
/**
 * Does every franc reach the sheet, exactly once?
 *
 * The two failures this guards against are silent ones: money that maps
 * nowhere and quietly vanishes from the report, and money that maps twice and
 * quietly inflates it. Both look fine on screen.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use Illuminate\Support\Facades\DB;

$results = [];
function check($n, $p, $d = '') { global $results; $results[] = [$n, $p, $d]; }

// ---- every category that holds money must be mapped ---------------------
$unmappedExpense = DB::table('expense_categories as c')
    ->leftJoin('default_account_mappings as m', function ($j) {
        $j->on('m.category_id', '=', 'c.id')->where('m.mapping_type', 'expense_category');
    })
    ->whereNull('m.id')
    ->pluck('c.title')->all();
check('every expense category is mapped', empty($unmappedExpense), implode(', ', $unmappedExpense));

$unmappedIncome = DB::table('income_categories as c')
    ->leftJoin('default_account_mappings as m', function ($j) {
        $j->on('m.category_id', '=', 'c.id')->where('m.mapping_type', 'income_category');
    })
    ->whereNull('m.id')
    ->pluck('c.title')->all();
check('every income category is mapped', empty($unmappedIncome), implode(', ', $unmappedIncome));

$unmappedFee = DB::table('fees_categories as c')
    ->leftJoin('default_account_mappings as m', function ($j) {
        $j->on('m.category_id', '=', 'c.id')->where('m.mapping_type', 'fee_category');
    })
    ->whereNull('m.id')
    ->pluck('c.title')->all();
check('every fee category is mapped', empty($unmappedFee), implode(', ', $unmappedFee));

// ---- no mapping may dangle ----------------------------------------------
$badLine = DefaultAccountMapping::whereNotNull('budget_line_id')
    ->whereNotIn('budget_line_id', BudgetLine::pluck('id'))->count();
check('no mapping points at a missing budget line', $badLine === 0, (string) $badLine);

$badAccount = DefaultAccountMapping::where(function ($q) {
    $q->whereNotIn('debit_account_id', ChartOfAccount::pluck('id'))
      ->orWhereNotIn('credit_account_id', ChartOfAccount::pluck('id'));
})->count();
check('no mapping points at a missing account', $badAccount === 0, (string) $badAccount);

// ---- postings may only land on leaf accounts ----------------------------
$headingPosts = DefaultAccountMapping::whereIn('debit_account_id',
    ChartOfAccount::where('account_category', 'heading')->pluck('id'))->count();
check('nothing posts to a summary account', $headingPosts === 0, $headingPosts . ' mappings');

// ---- a mapping must never point at a header line ------------------------
$headerLines = DefaultAccountMapping::whereIn('budget_line_id',
    BudgetLine::where('is_header', true)->pluck('id'))->pluck('description')->all();
check('nothing maps to a group header', empty($headerLines), implode('; ', $headerLines));

// ---- the money test: every franc reaches exactly one line ---------------
$expenseTotal = (float) DB::table('expenses')->sum('amount');
$expenseMapped = (float) DB::table('expenses as e')
    ->join('default_account_mappings as m', function ($j) {
        $j->on('m.category_id', '=', 'e.category_id')->where('m.mapping_type', 'expense_category');
    })
    ->whereNotNull('m.budget_line_id')
    ->sum('e.amount');
check('all expenditure reaches a sheet line',
    abs($expenseTotal - $expenseMapped) < 0.01,
    number_format($expenseMapped) . ' of ' . number_format($expenseTotal));

$incomeTotal = (float) DB::table('incomes')->sum('amount');
$incomeMapped = (float) DB::table('incomes as i')
    ->join('default_account_mappings as m', function ($j) {
        $j->on('m.category_id', '=', 'i.category_id')->where('m.mapping_type', 'income_category');
    })
    ->whereNotNull('m.budget_line_id')
    ->sum('i.amount');
check('all recorded income reaches a sheet line',
    abs($incomeTotal - $incomeMapped) < 0.01,
    number_format($incomeMapped) . ' of ' . number_format($incomeTotal));

// One mapping per category per type — two would double the category's money.
$dupes = DB::table('default_account_mappings')
    ->selectRaw('mapping_type, category_id, count(*) c')
    ->groupBy('mapping_type', 'category_id')->having('c', '>', 1)->get();
check('no category is mapped twice', $dupes->isEmpty(), $dupes->count() . ' duplicated');

// ---- the double-count trap ----------------------------------------------
// Registration money must not arrive from both the fee module and an income
// category. Today only one of them carries a balance; assert it stays that way.
$regIncome = (float) DB::table('incomes as i')->join('income_categories as c', 'c.id', '=', 'i.category_id')
    ->where('c.title', 'Registration')->sum('i.amount');
$regFees = (float) DB::table('fees as f')->join('fees_categories as c', 'c.id', '=', 'f.category_id')
    ->where('c.is_admission', 1)->sum('f.paid_amount');
check('registration income arrives from one source only',
    $regIncome == 0 || $regFees == 0,
    'income=' . number_format($regIncome) . ' fees=' . number_format($regFees));

// ---- tuition must be attributable to a school ---------------------------
$tuitionNoFaculty = (float) DB::table('fees as f')
    ->join('fees_categories as c', 'c.id', '=', 'f.category_id')
    ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->join('programs as p', 'p.id', '=', 'se.program_id')
    ->whereNull('p.faculty_id')
    ->where('c.is_admission', 0)
    ->sum('f.paid_amount');
check('all tuition can be attributed to a school',
    $tuitionNoFaculty == 0.0,
    number_format($tuitionNoFaculty) . ' has no faculty');

// Each faculty collecting tuition needs a line to report it on.
$faculties = DB::table('fees as f')
    ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->join('programs as p', 'p.id', '=', 'se.program_id')
    ->join('faculties as fa', 'fa.id', '=', 'p.faculty_id')
    ->distinct()->pluck('fa.title');
$tuitionLines = BudgetLine::where('section', 'income')
    ->where('name', 'like', 'Tuition%')->count();
check('there is a tuition line for every collecting school',
    $tuitionLines >= $faculties->count(),
    $tuitionLines . ' lines for ' . $faculties->count() . ' schools');

$failed = 0;
foreach ($results as [$n, $p, $d]) {
    if (!$p) { $failed++; }
    printf("%s  %s%s\n", $p ? 'PASS' : 'FAIL', $n, (!$p && $d) ? "   [$d]" : '');
}
printf("\n%d/%d passed\n", count($results) - $failed, count($results));
exit($failed ? 1 : 0);
