<?php
/**
 * Is the ledger sound after the backfill?
 *
 * A double-entry ledger that does not balance is worse than no ledger, because
 * every report built on it looks authoritative and is wrong. These are the
 * checks an auditor would make first.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$results = [];
function check($n, $p, $d = '') { global $results; $results[] = [$n, $p, $d]; }

// ---- the fundamental identity -------------------------------------------
$totals = DB::table('journal_entry_lines')
    ->selectRaw('ROUND(SUM(debit),2) dr, ROUND(SUM(credit),2) cr')->first();
check('total debits equal total credits',
    abs($totals->dr - $totals->cr) < 0.01,
    number_format($totals->dr) . ' vs ' . number_format($totals->cr));

// Every individual entry must balance, not just the ledger as a whole.
$unbalanced = DB::table('journal_entry_lines')
    ->selectRaw('journal_entry_id, ROUND(SUM(debit),2) dr, ROUND(SUM(credit),2) cr')
    ->groupBy('journal_entry_id')
    ->havingRaw('ABS(SUM(debit) - SUM(credit)) > 0.01')
    ->count();
check('every journal entry balances on its own', $unbalanced === 0, $unbalanced . ' unbalanced');

// ---- completeness --------------------------------------------------------
// Derived, not frozen: new transactions post themselves as they are recorded,
// and a corrected one adds a reversal. A hardcoded total goes stale the first
// time someone takes a fee payment.
$postable = DB::table('expenses')->count()
    + DB::table('incomes')->count()
    + DB::table('fees')->where('paid_amount', '>', 0)->count();
// Correcting a transaction leaves three rows, not one: the original stays, a
// reversal cancels it, and the new figure is posted. That is the point of a
// ledger — history is added to, never rewritten.
$reversals = DB::table('journal_entries')->where('reference_type', 'like', '%_reversal')->count();
$entries = DB::table('journal_entries')->count();
check('an entry exists for every transaction, plus a reversal and repost for each correction',
    $entries === $postable + (2 * $reversals),
    $entries . ' entries for ' . $postable . ' transactions and ' . $reversals . ' corrections');

check('every entry is posted',
    DB::table('journal_entries')->where('is_posted', false)->count() === 0);

$lineless = DB::table('journal_entries as j')
    ->leftJoin('journal_entry_lines as l', 'l.journal_entry_id', '=', 'j.id')
    ->whereNull('l.id')->count();
check('no entry is missing its lines', $lineless === 0, $lineless . ' empty');

// ---- reconciliation to source -------------------------------------------
$expenseSource = round((float) DB::table('expenses')->sum('amount'), 2);
$expensePosted = round((float) DB::table('journal_entries')->where('reference_type', 'expense')->sum('total_debit'), 2);
check('posted expenditure equals the expense records',
    abs($expenseSource - $expensePosted) < 0.01,
    number_format($expensePosted) . ' vs ' . number_format($expenseSource));

$incomeSource = round((float) DB::table('incomes')->sum('amount'), 2);
$incomePosted = round((float) DB::table('journal_entries')->where('reference_type', 'income')->sum('total_debit'), 2);
check('posted income equals the income records',
    abs($incomeSource - $incomePosted) < 0.01,
    number_format($incomePosted) . ' vs ' . number_format($incomeSource));

$feeSource = round((float) DB::table('fees')->where('paid_amount', '>', 0)->sum('paid_amount'), 2);
$feePosted = round((float) DB::table('journal_entries')->where('reference_type', 'fee')->sum('total_debit'), 2)
    - round((float) DB::table('journal_entries')->where('reference_type', 'fee_reversal')->sum('total_debit'), 2);
check('posted fee receipts equal the fee records',
    abs($feeSource - $feePosted) < 0.01,
    number_format($feePosted) . ' vs ' . number_format($feeSource));

// ---- nothing posted twice ------------------------------------------------
$dupes = DB::table('journal_entries')
    ->selectRaw('reference_type, reference_id, COUNT(*) c')
    ->groupBy('reference_type', 'reference_id')->havingRaw('COUNT(*) > 1')->count();
// A corrected transaction legitimately carries its original posting, a
// reversal, and the repost — so duplicates are only wrong when they are not
// balanced by a reversal.
$unreversed = DB::table('journal_entries as j')
    ->selectRaw('j.reference_type, j.reference_id, COUNT(*) c')
    ->whereRaw("j.reference_type NOT LIKE '%_reversal'")
    ->groupBy('j.reference_type', 'j.reference_id')
    ->havingRaw('COUNT(*) > 1 + (
        SELECT COUNT(*) FROM journal_entries r
        WHERE r.reference_id = j.reference_id
          AND r.reference_type = CONCAT(j.reference_type, "_reversal")
    )')
    ->count();
check('no transaction is posted twice without a matching reversal',
    $unreversed === 0, $unreversed . ' unbalanced');

$dupeMappings = DB::table('transaction_mappings')
    ->selectRaw('transaction_type, transaction_id, COUNT(*) c')
    ->groupBy('transaction_type', 'transaction_id')->havingRaw('COUNT(*) > 1')->count();
check('no transaction is mapped twice', $dupeMappings === 0, $dupeMappings . ' duplicated');

// ---- entries land in the right year -------------------------------------
// The service used to stamp the *active* fiscal year regardless of date, which
// would have filed 167 of these under the wrong year with no period at all.
$wrongYear = DB::table('journal_entries as j')
    ->join('fiscal_years as fy', 'fy.id', '=', 'j.fiscal_year_id')
    ->whereRaw('j.entry_date < fy.start_date OR j.entry_date > fy.end_date')
    ->count();
check('every entry sits in the fiscal year containing its date', $wrongYear === 0, $wrongYear . ' misfiled');

$noPeriod = DB::table('journal_entries')->whereNull('accounting_period_id')->count();
check('every entry has an accounting period', $noPeriod === 0, $noPeriod . ' without');

$wrongPeriod = DB::table('journal_entries as j')
    ->join('accounting_periods as p', 'p.id', '=', 'j.accounting_period_id')
    ->whereRaw('j.entry_date < p.start_date OR j.entry_date > p.end_date')
    ->count();
check('every entry sits in the period containing its date', $wrongPeriod === 0, $wrongPeriod . ' misfiled');

// Both years should carry entries, since spending spans Oct 2025 to Jul 2026.
$byYear = DB::table('journal_entries as j')->join('fiscal_years as fy', 'fy.id', '=', 'j.fiscal_year_id')
    ->selectRaw('fy.name, COUNT(*) c')->groupBy('fy.name')->pluck('c', 'name');
check('entries are spread across both fiscal years', $byYear->count() === 2, $byYear->toJson());

// ---- postings land only on real, postable accounts ----------------------
$toHeading = DB::table('journal_entry_lines as l')
    ->join('chart_of_accounts as c', 'c.id', '=', 'l.account_id')
    ->where('c.account_category', 'heading')->count();
check('nothing posted to a summary account', $toHeading === 0, $toHeading . ' lines');

$orphanAccount = DB::table('journal_entry_lines')
    ->whereNotIn('account_id', DB::table('chart_of_accounts')->pluck('id'))->count();
check('every line points at a real account', $orphanAccount === 0, $orphanAccount . ' orphaned');

// ---- the trial balance ---------------------------------------------------
$trial = DB::table('journal_entry_lines as l')
    ->join('chart_of_accounts as c', 'c.id', '=', 'l.account_id')
    ->selectRaw('c.account_code, c.account_name, ROUND(SUM(l.debit),2) dr, ROUND(SUM(l.credit),2) cr')
    ->groupBy('c.account_code', 'c.account_name')->orderBy('c.account_code')->get();
check('the trial balance has accounts on it', $trial->count() > 5, $trial->count() . ' accounts');
check('the trial balance balances',
    abs($trial->sum('dr') - $trial->sum('cr')) < 0.01,
    number_format($trial->sum('dr')) . ' vs ' . number_format($trial->sum('cr')));

// ---- re-running must change nothing --------------------------------------
$before = DB::table('journal_entries')->count();
Illuminate\Support\Facades\Artisan::call('ledger:backfill', ['--commit' => true]);
$after = DB::table('journal_entries')->count();
check('re-running the backfill posts nothing new', $before === $after,
    $before . ' -> ' . $after);

$failed = 0;
foreach ($results as [$n, $p, $d]) {
    if (!$p) { $failed++; }
    printf("%s  %s%s\n", $p ? 'PASS' : 'FAIL', $n, (!$p && $d) ? "   [$d]" : '');
}
printf("\n%d/%d passed\n", count($results) - $failed, count($results));

if (!$failed) {
    echo "\nTrial balance:\n";
    foreach ($trial as $t) {
        printf("  %-5s %-36s Dr %13s  Cr %13s\n", $t->account_code,
            mb_substr($t->account_name, 0, 34), number_format($t->dr), number_format($t->cr));
    }
    printf("  %-42s   %13s     %13s\n", 'TOTAL', number_format($trial->sum('dr')), number_format($trial->sum('cr')));
}

exit($failed ? 1 : 0);
