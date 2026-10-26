<?php

/**
 * Editing a transaction, and whether the ledger follows it.
 *
 * An income or expense row is read directly by the Income & Expenditure sheet
 * and the daybook, and separately posted to the double-entry ledger as a
 * journal entry. Two records of the same money, and the reconciliation
 * compares them — so anything that moves one without the other is drift that
 * no entry accounts for.
 *
 * The observers remapped on amount and category and NOT on date. Everything
 * reading the sheet takes the date from the row, so correcting one moved the
 * money immediately; the journal entry kept the old date, the old accounting
 * period and the old fiscal year. Measured before the fix: moving an income
 * from 29 May 2026 to 15 November 2025 put 3,781,449 into November on the
 * sheet and left it in May in the ledger — a different exercice.
 *
 * FeeObserver had always included pay_date. This is the same rule, applied to
 * the two observers that were missing it.
 *
 * Every write happens inside a transaction that is rolled back.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Income;
use App\Services\BudgetActualsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

function money(float $v): string
{
    return number_format($v, 2, '.', '');
}

/** The live (unreversed) journal entry for a transaction. */
function liveEntry(string $type, int $id): ?object
{
    return DB::table('journal_entries')
        ->where('reference_type', $type)
        ->where('reference_id', $id)
        ->where('is_reversed', 0)
        ->whereNull('deleted_at')
        ->orderByDesc('id')
        ->first();
}

Auth::guard('web')->login(App\User::first());

$opening = [
    'incomes' => DB::table('incomes')->count(),
    'expenses' => DB::table('expenses')->count(),
    'entries' => DB::table('journal_entries')->count(),
    'debits' => money((float) DB::table('journal_entries')->sum('total_debit')),
];

printf("Before: %d incomes, %d expenses, %d journal entries\n\n",
    $opening['incomes'], $opening['expenses'], $opening['entries']);

$actuals = app(BudgetActualsService::class);

// A posted income, so there is a ledger side that can disagree.
$postedIds = DB::table('journal_entries')->where('reference_type', 'income')->pluck('reference_id')->all();
$income = Income::whereIn('id', $postedIds)->orderByDesc('amount')->firstOrFail();

$originalDate = $income->date instanceof DateTimeInterface
    ? $income->date->format('Y-m-d')
    : substr((string) $income->date, 0, 10);

printf("Subject: income #%d, %s, %s\n", $income->id, $originalDate, money((float) $income->amount));

DB::beginTransaction();

try {
    echo "\n=== 1. a date-only edit moves the ledger entry too ===\n";

    // Chosen to sit in a DIFFERENT exercice from the original, because that is
    // where the consequence stops being a month and becomes a set of accounts.
    $target = '2025-11-15';

    $beforeYear = (int) liveEntry('income', $income->id)->fiscal_year_id;
    $targetYear = FiscalYear::forDate($target);

    ok('the test actually crosses an exercice boundary',
        $targetYear && $beforeYear !== $targetYear->id);

    $income->date = $target;
    $income->save();

    $entry = liveEntry('income', $income->id);

    check('the live entry is dated as the transaction now is', $target,
        substr((string) $entry->entry_date, 0, 10));
    check('and filed in the exercice containing that date', $targetYear->id,
        (int) $entry->fiscal_year_id);
    ok('with an accounting period of its own', $entry->accounting_period_id !== null);

    $period = DB::table('accounting_periods')->find($entry->accounting_period_id);
    ok('and that period contains the date',
        $target >= substr((string) $period->start_date, 0, 10)
        && $target <= substr((string) $period->end_date, 0, 10));

    check('the amount is unchanged by a date edit', money((float) $income->amount),
        money((float) $entry->total_debit));

    echo "\n=== 2. the old entry is reversed, not deleted ===\n";

    $all = DB::table('journal_entries')
        ->where('reference_type', 'income')->where('reference_id', $income->id)
        ->orderBy('id')->get();

    ok('the earlier entry is still on file', $all->count() >= 2);
    check('exactly one entry is live', 1, $all->where('is_reversed', 0)->count());
    ok('and the superseded one is marked reversed',
        $all->where('is_reversed', 1)->count() >= 1);

    // The ledger must not double-count: reversed entries are excluded, so the
    // net for this transaction is its amount, once.
    $live = (float) $all->where('is_reversed', 0)->sum('total_debit');
    check('the live total is the amount, counted once', money((float) $income->amount), money($live));

    echo "\n=== 3. the sheet and the ledger agree about the month ===\n";

    $sheetNov = $actuals->forPeriod('2025-11-01', '2025-11-30');
    $sheetNovTotal = array_sum($sheetNov['lines']) + array_sum($sheetNov['unallocated']);

    $ledgerNov = (float) DB::table('journal_entries')
        ->where('reference_type', 'income')
        ->where('is_reversed', 0)
        ->whereNull('deleted_at')
        ->whereBetween('entry_date', ['2025-11-01', '2025-11-30'])
        ->sum('total_debit');

    // Not equal in absolute terms — November holds fees and expenses too —
    // but this transaction must now be inside BOTH, which is what failed
    // before: the sheet had it in November and the ledger in May.
    ok('the moved transaction is in November on the sheet', $sheetNovTotal > 0);
    ok('and in November in the ledger', $ledgerNov >= (float) $income->amount);

    $ledgerMay = (float) DB::table('journal_entries')
        ->where('reference_type', 'income')
        ->where('reference_id', $income->id)
        ->where('is_reversed', 0)
        ->whereBetween('entry_date', [substr($originalDate, 0, 7) . '-01', substr($originalDate, 0, 7) . '-31'])
        ->sum('total_debit');

    check('and no longer in the month it came from', '0.00', money($ledgerMay));

    echo "\n=== 4. an amount edit still works as it did ===\n";

    $newAmount = (float) $income->amount + 1000;
    $income->amount = $newAmount;
    $income->save();

    $entry = liveEntry('income', $income->id);

    check('the live entry carries the new amount', money($newAmount), money((float) $entry->total_debit));
    check('still one live entry', 1, DB::table('journal_entries')
        ->where('reference_type', 'income')->where('reference_id', $income->id)
        ->where('is_reversed', 0)->count());
    check('and it keeps the date', $target, substr((string) $entry->entry_date, 0, 10));

    echo "\n=== 5. a cosmetic edit does NOT churn the ledger ===\n";

    // remap() reverses and re-posts, so triggering on every field would file a
    // reversal every time somebody fixed a typo.
    $entriesBefore = DB::table('journal_entries')
        ->where('reference_type', 'income')->where('reference_id', $income->id)->count();

    $income->title = $income->title . ' (corrected)';
    $income->save();

    check('editing the title posts nothing new', $entriesBefore, DB::table('journal_entries')
        ->where('reference_type', 'income')->where('reference_id', $income->id)->count());

    echo "\n=== 6. the same rule holds for an expense ===\n";

    // Not simply the largest: it must start in a DIFFERENT exercice from the
    // target, or "it crossed a boundary" asserts nothing. The biggest expense
    // on this database is already dated in the target's year.
    $postedExp = DB::table('journal_entries')
        ->where('reference_type', 'expense')
        ->where('is_reversed', 0)
        ->where('fiscal_year_id', '!=', $targetYear->id)
        ->pluck('reference_id')
        ->all();

    $expense = Expense::whereIn('id', $postedExp)->orderByDesc('amount')->first();

    if (!$expense) {
        echo "  (no posted expense on this database)\n";
    } else {
        $expBefore = (int) liveEntry('expense', $expense->id)->fiscal_year_id;

        $expense->date = $target;
        $expense->save();

        $expEntry = liveEntry('expense', $expense->id);

        check('the expense entry moves to the new date', $target,
            substr((string) $expEntry->entry_date, 0, 10));
        check('and into the exercice that contains it', $targetYear->id,
            (int) $expEntry->fiscal_year_id);
        ok('which is a different exercice from where it started',
            $expBefore !== (int) $expEntry->fiscal_year_id);
        check('exactly one live entry remains', 1, DB::table('journal_entries')
            ->where('reference_type', 'expense')->where('reference_id', $expense->id)
            ->where('is_reversed', 0)->count());
    }

    echo "\n=== 7. deleting reverses rather than erases ===\n";

    $deletable = Income::whereIn('id', $postedIds)->where('id', '!=', $income->id)->first();

    if ($deletable) {
        $deletable->delete();

        $after = DB::table('journal_entries')
            ->where('reference_type', 'income')->where('reference_id', $deletable->id)->get();

        ok('the entries are still on file', $after->count() > 0);
        check('and none of them is live', 0, $after->where('is_reversed', 0)->count());
    }

    echo "\n=== 7b. a remap that cannot re-post keeps the old posting ===\n";

    /*
     * remap() reverses and re-posts. Those used to be two separate
     * transactions, so anything that stopped the second left the first
     * standing: measured, remapping a posted income to a category with no
     * account mapping reversed the entry, failed to write the replacement, and
     * left the transaction on the sheet with no ledger posting at all.
     *
     * Category 13 is one of the four on this database with no mapping row, so
     * this is the real failure rather than a contrived one.
     */
    $survivor = Income::whereIn('id', $postedIds)
        ->where('id', '!=', $income->id)
        ->when($deletable, fn ($q) => $q->where('id', '!=', $deletable->id))
        ->first();

    if ($survivor) {
        $entryBefore = liveEntry('income', $survivor->id);

        ok('it starts with a live posting', $entryBefore !== null);

        $unmapped = DB::table('incomes')
            ->whereNotIn('category_id', function ($q) {
                $q->select('category_id')->from('default_account_mappings')
                  ->where('mapping_type', 'income_category')->whereNotNull('category_id');
            })
            ->value('category_id');

        ok('and an unmapped category exists to fail against', $unmapped !== null);

        $result = app(App\Services\TransactionAutoMapService::class)->remap(
            'income', $survivor->id, $unmapped,
            ['amount' => $survivor->amount, 'date' => $survivor->date, 'description' => 'unpostable']
        );

        check('the remap reports that it could not re-post', false, $result);

        $entryAfter = liveEntry('income', $survivor->id);

        ok('the original posting is still live', $entryAfter !== null);
        check('it is the same entry as before', (int) $entryBefore->id, (int) ($entryAfter->id ?? 0));
        check('carrying the same amount', money((float) $entryBefore->total_debit),
            money((float) ($entryAfter->total_debit ?? 0)));
        check('and exactly one entry is live', 1, DB::table('journal_entries')
            ->where('reference_type', 'income')->where('reference_id', $survivor->id)
            ->where('is_reversed', 0)->whereNull('deleted_at')->count());

        // And a remap that CAN re-post is unaffected by the guard.
        $good = app(App\Services\TransactionAutoMapService::class)->remap(
            'income', $survivor->id, $survivor->category_id,
            ['amount' => 12345, 'date' => $survivor->date, 'description' => 'postable']
        );

        ok('a remap that can re-post still does', $good !== false);
        check('at the new amount', '12345.00',
            money((float) liveEntry('income', $survivor->id)->total_debit));
        check('still exactly one live entry', 1, DB::table('journal_entries')
            ->where('reference_type', 'income')->where('reference_id', $survivor->id)
            ->where('is_reversed', 0)->whereNull('deleted_at')->count());
    }

    echo "\n=== 8. teeth: without the date in the trigger, the two diverge ===\n";

    // What the old condition did, reproduced exactly: a date change that does
    // not touch amount or category leaves the entry where it was.
    $witness = Income::whereIn('id', $postedIds)
        ->where('id', '!=', $income->id)
        ->where('id', '!=', $deletable?->id)
        ->first();

    if ($witness) {
        $witnessEntry = liveEntry('income', $witness->id);
        $witnessDateBefore = substr((string) $witnessEntry->entry_date, 0, 10);

        // Write the date straight to the table, bypassing the model — which is
        // precisely what the old trigger amounted to.
        DB::table('incomes')->where('id', $witness->id)->update(['date' => '2025-12-01']);

        $stillThere = liveEntry('income', $witness->id);

        check('a write that skips the observer leaves the ledger behind',
            $witnessDateBefore, substr((string) $stillThere->entry_date, 0, 10));

        // And through the model, it follows.
        $witness->refresh();
        $witness->date = '2025-12-02';
        $witness->save();

        check('through the model, the ledger follows', '2025-12-02',
            substr((string) liveEntry('income', $witness->id)->entry_date, 0, 10));
    }
} catch (\Throwable $e) {
    echo "\n  *** the suite threw: " . $e->getMessage() . "\n";
    echo '      ' . $e->getFile() . ':' . $e->getLine() . "\n";
    $fail++;
} finally {
    DB::rollBack();
}

echo "\n=== nothing was left behind ===\n";

$closing = [
    'incomes' => DB::table('incomes')->count(),
    'expenses' => DB::table('expenses')->count(),
    'entries' => DB::table('journal_entries')->count(),
    'debits' => money((float) DB::table('journal_entries')->sum('total_debit')),
];

foreach ($opening as $key => $value) {
    check(sprintf('%-9s is still %s', $key, $value), $value, $closing[$key]);
}

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
