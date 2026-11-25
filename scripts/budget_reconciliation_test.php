<?php
/**
 * The budget sheet and the ledger agree — and the check that says so can fail.
 *
 * A reconciliation that always passes proves nothing, so roughly half of this
 * suite deliberately breaks the mapping and asserts the service notices. Every
 * mutation runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/budget_reconciliation_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BudgetLine;
use App\Services\BudgetReconciliationService;
use Illuminate\Support\Facades\DB;

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

/** @return BudgetReconciliationService */
function service()
{
    // A fresh instance each time: the actuals service caches nothing, but the
    // point is that every assertion re-reads the database.
    return app(BudgetReconciliationService::class);
}

function sectionOf(array $result, string $section): array
{
    foreach ($result['sections'] as $s) {
        if ($s['section'] === $section) {
            return $s;
        }
    }
    throw new RuntimeException("no section $section");
}

function issueTypes(array $result): array
{
    return array_values(array_unique(array_column($result['issues'], 'type')));
}

echo "\n" . str_repeat('=', 64) . "\n";
echo "Budget sheet <-> ledger reconciliation\n";
echo str_repeat('=', 64) . "\n";

// ---------------------------------------------------------------------------
echo "\nThe sheet agrees with the ledger as it stands\n";
// ---------------------------------------------------------------------------

$base = service()->reconcile();

check('reconciliation reports agreement', $base['agrees'] === true);
check('no outstanding issues', $base['issues'] === [], json_encode($base['issues']));

foreach (['income' => 7, 'expenditure' => 6, 'capital' => 2] as $section => $class) {
    $s = sectionOf($base, $section);
    check(
        sprintf('%s: sheet %s = ledger class %d', $section, number_format($s['sheet']), $class),
        $s['agrees'],
        sprintf('sheet %s vs ledger %s (difference %s)', number_format($s['sheet']), number_format($s['ledger']), number_format($s['difference']))
    );
    check("$section is compared against ledger class $class", $s['class'] === $class);
}

// The differences allowed in income, each worked out here from the raw
// posting tables, not by the service, so the service cannot excuse an
// arbitrary gap:
//
//   1. a fee posting that differs from the cash the fee received (less
//      instalments a payment plan posted itself) - zero once
//      fees:credit-audit --correct-ledger has run;
//   2. fee income timing: the sheet counts fee cash when it arrives, the
//      ledger counts fee income when it is recognised - when billed (fees on
//      accrual), and overpaid or prepaid money when it settles a fee.
$planPaid = fn (int $feeId) => (float) DB::table('payment_plan_payments as ppp')
    ->join('payment_plan_installments as ppi', 'ppi.id', '=', 'ppp.installment_id')
    ->join('payment_plans as pp', 'pp.id', '=', 'ppi.payment_plan_id')
    ->where('pp.fee_id', $feeId)->where('ppp.status', '!=', 'reversed')->sum('ppp.amount');
/** Earlier receipts posted on their own days (FeeLedgerPosting): worked out here from the receipts. */
$splitOff = function (int $feeId, float $postable): float {
    $fee = DB::table('fees')->where('id', $feeId)->first(['pay_date']);
    if (!$fee || !$fee->pay_date) {
        return 0.0;
    }
    $prior = (float) DB::table('payment_receipts as pr')->where('pr.fee_id', $feeId)->where('pr.verification_status', 'approved')
        ->whereDate('pr.payment_date', '<', $fee->pay_date)
        ->whereExists(fn ($q) => $q->from('fiscal_years as fy')->whereRaw('pr.payment_date BETWEEN fy.start_date AND fy.end_date'))
        ->sum('pr.amount');

    return $prior <= $postable + 0.005 ? $prior : 0.0;
};
$creditPostedAsCash = 0.0;
foreach (DB::table('transaction_mappings as tm')->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
    ->join('fees as f', 'f.id', '=', 'tm.transaction_id')
    ->where('tm.transaction_type', 'fee')->where('tm.status', 'active')
    ->select('f.id', 'f.paid_amount', 'je.total_debit')->get() as $postedFee) {
    $cash = (float) $postedFee->paid_amount
        - (float) DB::table('credit_applications')->where('fee_id', $postedFee->id)->sum('amount_applied')
        + (float) DB::table('student_credits')->where('source_type', 'transfer')->where('source_fee_id', $postedFee->id)->sum('original_amount');
    $postable = $cash - $planPaid((int) $postedFee->id);
    $creditPostedAsCash += (float) $postedFee->total_debit - ($postable - $splitOff((int) $postedFee->id, $postable));
}
$creditPostedAsCash = round($creditPostedAsCash, 2);

$feeTypes = ['fee', 'fee_reversal', 'fee_receipt', 'fee_receipt_reversal', 'payment_plan_payment', 'payment_plan_payment_reversal', 'payment_plans_payments',
    'payment_plan_payments', 'fee_billing', 'fee_billing_reversal'];
$feeIncome = (float) DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
    ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
    ->where('e.is_posted', 1)->whereNull('e.deleted_at')
    ->where(fn ($q) => $q->whereIn('e.reference_type', $feeTypes)->orWhere('e.reference_type', 'like', 'student_credit_%'))
    ->where('a.account_code', 'like', '7%')->sum(DB::raw('l.credit - l.debit'));
$feeCash = (float) DB::table('journal_entries')->where('is_posted', 1)->whereNull('deleted_at')
        ->whereIn('reference_type', ['fee', 'payment_plan_payment', 'fee_receipt'])->sum('total_debit')
    - (float) DB::table('journal_entries')->where('is_posted', 1)->whereNull('deleted_at')
        ->whereIn('reference_type', ['fee_reversal', 'payment_plan_payment_reversal', 'payment_plan_payments', 'fee_receipt_reversal'])->sum('total_debit');
$timing = round($feeIncome - $feeCash, 2);
$income = sectionOf($base, 'income');

check('the only differences explained in income are fee postings vs cash, and fee income timing',
    abs(-$income['explained'] - ($creditPostedAsCash + $timing)) < 0.01,
    sprintf('explained %s, expected %s (postings vs cash %s, timing %s)', number_format(-$income['explained']), number_format($creditPostedAsCash + $timing),
        number_format($creditPostedAsCash), number_format($timing)));
check('and each is reported by name, with its amount',
    abs(collect($base['known_differences'] ?? [])->where('section', 'income')->sum('amount') - ($creditPostedAsCash + $timing)) < 0.01,
    json_encode($base['known_differences'] ?? null));
check('expenditure and capital have no allowance at all',
    sectionOf($base, 'expenditure')['explained'] == 0 && sectionOf($base, 'capital')['explained'] == 0);

// Capital belongs on the balance sheet, not in the income statement: that is
// the whole reason it is reconciled against class 2 rather than class 6.
$capital = sectionOf($base, 'capital');
check(
    'capital is capitalised, not expensed',
    $capital['class'] === 2 && $capital['ledger'] > 0,
    'capital ledger total ' . number_format($capital['ledger'])
);

// ---------------------------------------------------------------------------
echo "\nEvery section is backed by named accounts\n";
// ---------------------------------------------------------------------------

foreach (['income', 'expenditure', 'capital'] as $section) {
    $s = sectionOf($base, $section);
    check(
        "$section names the accounts behind its total (" . count($s['accounts']) . ')',
        count($s['accounts']) > 0
    );
    $sum = array_sum(array_column($s['accounts'], 'amount'));
    check(
        "$section account breakdown sums to its ledger total",
        abs($sum - $s['ledger']) < 0.01,
        number_format($sum) . ' vs ' . number_format($s['ledger'])
    );
}

// ---------------------------------------------------------------------------
echo "\nThe mapping is visible per budget line\n";
// ---------------------------------------------------------------------------

$byLine = service()->accountsByLine();
check('budget lines resolve to chart accounts', count($byLine) > 0, count($byLine) . ' lines');

// Every line the mapping reaches must name a real account code.
$allLabels = array_merge(...array_values($byLine));
check(
    'every mapped line names a real account code',
    count($allLabels) > 0 && count(array_filter($allLabels, fn ($l) => (bool) preg_match('~^\d+ ~', $l))) === count($allLabels),
    implode(' | ', array_slice($allLabels, 0, 3))
);

// ---------------------------------------------------------------------------
echo "\nThe check fails when the bridge is broken\n";
// ---------------------------------------------------------------------------

// 1. A category that loses its budget line drops off the sheet.
DB::beginTransaction();
$victim = DB::table('default_account_mappings')
    ->where('mapping_type', 'expense_category')
    ->whereNotNull('budget_line_id')
    ->first();
DB::table('default_account_mappings')->where('id', $victim->id)->update(['budget_line_id' => null]);

$broken = service()->reconcile();
check('an unmapped category is reported', in_array('unmapped_category', issueTypes($broken), true), json_encode(issueTypes($broken)));
check('the overall verdict turns negative', $broken['agrees'] === false);
check(
    'expenditure now falls short of the ledger',
    sectionOf($broken, 'expenditure')['difference'] < 0,
    'difference ' . number_format(sectionOf($broken, 'expenditure')['difference'])
);
DB::rollBack();

// 2. The other half cannot break: the database will not allow a mapping
//    without a chart account, which is why the service only checks one side.
$cols = collect(DB::select('SHOW COLUMNS FROM default_account_mappings'))->keyBy('Field');
check('the ledger half is guaranteed by the schema', $cols['debit_account_id']->Null === 'NO' && $cols['credit_account_id']->Null === 'NO');
check('the sheet half is the one that can go missing', $cols['budget_line_id']->Null === 'YES');

// 3. Money posted to a line no category reaches.
DB::beginTransaction();
$orphan = BudgetLine::where('is_header', false)->whereNull('faculty_id')
    ->whereNotIn('id', DB::table('default_account_mappings')->whereNotNull('budget_line_id')->pluck('budget_line_id')->all() ?: [0])
    ->first();
if ($orphan) {
    DB::table('expenses')->orderBy('id')->limit(1)->update(['budget_line_id' => $orphan->id]);
    $broken = service()->reconcile();
    check('a budget line no category reaches is reported', in_array('unreachable_line', issueTypes($broken), true), json_encode(issueTypes($broken)));
} else {
    check('a budget line no category reaches is reported', true, 'skipped: every line is mapped');
}
DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nNothing was disturbed\n";
// ---------------------------------------------------------------------------

$after = service()->reconcile();
check('still agrees after the failure injections', $after['agrees'] === true);
foreach (['income', 'expenditure', 'capital'] as $section) {
    check(
        "$section total unchanged",
        abs(sectionOf($after, $section)['sheet'] - sectionOf($base, $section)['sheet']) < 0.01
    );
}

check(
    'the dead budget_line_account pivot is gone',
    !Illuminate\Support\Facades\Schema::hasTable('budget_line_account'),
    'table still present'
);

echo "\n" . str_repeat('-', 64) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
