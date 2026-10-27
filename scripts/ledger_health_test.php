<?php

/**
 * The ledger health check.
 *
 * A diagnostic is only worth having if it fails when something is wrong. A
 * check that always returns "all clear" is worse than no check: it is a claim
 * nobody re-examines.
 *
 * So every check here is driven BOTH ways — against the books as they stand,
 * and against a fault constructed on purpose — inside a transaction that is
 * rolled back. Several of them exist because the corresponding fault really
 * happened on this system: a date-only edit that left the journal entry in the
 * old month and the old exercice, and a re-posting that reversed the original
 * and never wrote its replacement.
 *
 * The service only reads, so the closing section proves the database is in
 * exactly the state it started in.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Accounting\LedgerHealth;
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

/** One check out of a run, by key. */
function find(array $checks, string $key): array
{
    foreach ($checks as $c) {
        if ($c['key'] === $key) {
            return $c;
        }
    }

    return ['key' => $key, 'state' => 'MISSING', 'count' => -1, 'rows' => []];
}

$health = app(LedgerHealth::class);

$opening = [
    'entries' => DB::table('journal_entries')->count(),
    'lines' => DB::table('journal_entry_lines')->count(),
    'mappings' => DB::table('transaction_mappings')->count(),
    'incomes' => DB::table('incomes')->count(),
    'debits' => number_format((float) DB::table('journal_entries')->sum('total_debit'), 2, '.', ''),
];

printf("Before: %d entries, %d lines, %d mappings\n\n", $opening['entries'], $opening['lines'], $opening['mappings']);

$baseline = $health->checks();

echo "=== 1. it reports the state of the books as they are ===\n";

$keys = array_column($baseline, 'key');

foreach ([
    'mapping_no_entry', 'mapping_at_reversed', 'mapping_duplicate', 'mapping_orphan',
    'entry_no_lines', 'entry_unbalanced', 'entry_header_drift', 'reversal_orphan',
    'reversed_without_reversal', 'entry_duplicate',
    'source_unposted', 'date_drift', 'amount_drift',
    'wrong_exercice', 'wrong_period', 'no_exercice', 'no_period',
    'mapping_orphan_unposted',
] as $key) {
    ok('it runs the ' . $key . ' check', in_array($key, $keys, true));
}

check('eighteen checks in all', 18, count($baseline));

// The findings that are real on this database, as regression anchors.
check('the four unposted incomes are found', 4, find($baseline, 'source_unposted')['count']);
check('worth 20,254,000', '20254000.00',
    number_format(find($baseline, 'source_unposted')['value'], 2, '.', ''));
/*
 * The amount differences are asserted by their SHAPE, not by a figure.
 *
 * This used to pin "5, worth 196,666.66" — a snapshot of the live database.
 * It broke the first time somebody recorded a real payment: fee 242 was paid
 * 150,000 of which 90,000 came from credit already counted on another fee, so
 * the ledger correctly posted only the 60,000 cash and a sixth difference
 * appeared. Nothing was wrong; the assertion was.
 *
 * What is actually invariant is that every one of these is a FEE, and that the
 * gap is credit applied from elsewhere — which is why the check's own remedy
 * is fees:credit-audit. A difference on an income or an expense would be a
 * different fault, and that is what this now catches.
 */
$drift = find($baseline, 'amount_drift');

ok('there are amount differences to explain', $drift['count'] > 0);

foreach ($drift['rows'] as $row) {
    check('difference #' . $row->id . ' is on a fee', 'fee', $row->kind);
}

/*
 * And the ledger never claims MORE cash than the fee says was paid.
 *
 * That is the direction that matters. A ledger short of the fee is the known
 * credit case — some of what the student owed was settled by credit already
 * counted elsewhere, which fees:credit-audit owns. A ledger AHEAD of the fee
 * would mean money posted that nobody paid, and there is no benign reading of
 * that.
 */
$overstated = [];

foreach ($drift['rows'] as $row) {
    if ((float) $row->total_debit > (float) $row->source_amount + 0.01) {
        $overstated[] = sprintf('fee %s: ledger %s vs fee %s',
            $row->reference_id, $row->total_debit, $row->source_amount);
    }
}

check('the ledger never posts more than the fee', [], $overstated);
/*
 * The two dead payroll mappings were retired once it was established that
 * neither carried a posting — so the anchor is now that they are gone, and
 * that nothing orphaned is left on either severity. The repair itself is
 * exercised on constructed data in ledger_orphan_repair_test, where it can be
 * driven in both directions without depending on what the books contain.
 */
check('no orphan mapping carries a live entry', 0, find($baseline, 'mapping_orphan')['count']);
check('and no stale orphan is left', 0, find($baseline, 'mapping_orphan_unposted')['count']);

// And what today's work left clean.
check('no entry is dated differently from its transaction', 'ok', find($baseline, 'date_drift')['state']);
check('no entry is filed outside its exercice', 'ok', find($baseline, 'wrong_exercice')['state']);
check('no entry is filed outside its month', 'ok', find($baseline, 'wrong_period')['state']);
check('no entry is unbalanced', 'ok', find($baseline, 'entry_unbalanced')['state']);
check('no transaction has two live entries', 'ok', find($baseline, 'entry_duplicate')['state']);

echo "\n=== 2. severity is a property of the fault, not of the count ===\n";

check('a transaction with no entry is critical', 'critical', find($baseline, 'source_unposted')['state']);
check('an amount difference is a warning', 'warning', find($baseline, 'amount_drift')['state']);
ok('a passing check offers no action', find($baseline, 'date_drift')['action'] === null);
ok('a failing one does', find($baseline, 'source_unposted')['action'] !== null);

echo "\n=== 3. the verdict reflects the worst finding ===\n";

check('the books currently need attention', 'critical', $health->worstState($baseline));
check('and two checks found something', 2, $health->concerns($baseline));

$allOk = [['key' => 'a', 'state' => 'ok'], ['key' => 'b', 'state' => 'ok']];
check('a clean run reports ok', 'ok', $health->worstState($allOk));
check('with nothing to concern anyone', 0, $health->concerns($allOk));

check('one warning makes the worst a warning', 'warning',
    $health->worstState([['key' => 'a', 'state' => 'ok'], ['key' => 'b', 'state' => 'warning']]));

echo "\n=== 4. each check actually catches its own fault ===\n";

/* --- a date that no longer matches its transaction ------------------- */
DB::beginTransaction();
try {
    $entry = DB::table('journal_entries')
        ->where('reference_type', 'income')->where('is_reversed', 0)->whereNull('deleted_at')
        ->first();

    // Written straight to the table, so no observer tidies it up — exactly
    // the state the old observer left behind.
    DB::table('journal_entries')->where('id', $entry->id)
        ->update(['entry_date' => '2001-01-01']);

    $drift = find($health->checks(), 'date_drift');

    check('a mismatched date is caught', 'critical', $drift['state']);
    ok('and the entry is named', collect($drift['rows'])->contains('id', $entry->id));
} finally {
    DB::rollBack();
}

/* --- an entry posted for the wrong amount ---------------------------- */
DB::beginTransaction();
try {
    $entry = DB::table('journal_entries')
        ->where('reference_type', 'expense')->where('is_reversed', 0)->whereNull('deleted_at')
        ->first();

    $before = find($health->checks(), 'amount_drift')['count'];

    DB::table('journal_entries')->where('id', $entry->id)
        ->update(['total_debit' => (float) $entry->total_debit + 500]);

    check('a changed amount is caught', $before + 1, find($health->checks(), 'amount_drift')['count']);
} finally {
    DB::rollBack();
}

/* --- two live entries for one transaction ---------------------------- */
DB::beginTransaction();
try {
    $entry = (array) DB::table('journal_entries')
        ->where('reference_type', 'expense')->where('is_reversed', 0)->whereNull('deleted_at')
        ->first();

    unset($entry['id']);
    $entry['entry_number'] = 'DUP-' . uniqid();
    DB::table('journal_entries')->insert($entry);

    $dup = find($health->checks(), 'entry_duplicate');

    check('a double posting is caught', 'critical', $dup['state']);
    ok('and it is counted once, for one transaction', $dup['count'] >= 1);
} finally {
    DB::rollBack();
}

/* --- an entry whose lines do not balance ----------------------------- */
DB::beginTransaction();
try {
    $line = DB::table('journal_entry_lines')->where('debit', '>', 0)->first();

    DB::table('journal_entry_lines')->where('id', $line->id)
        ->update(['debit' => (float) $line->debit + 1000]);

    check('an unbalanced entry is caught', 'critical',
        find($health->checks(), 'entry_unbalanced')['state']);
    check('as is a header that no longer matches its lines', 'critical',
        find($health->checks(), 'entry_header_drift')['state']);
} finally {
    DB::rollBack();
}

/* --- an entry filed outside its own exercice ------------------------- */
DB::beginTransaction();
try {
    $other = DB::table('fiscal_years')->orderBy('id')->first();

    $entry = DB::table('journal_entries')
        ->whereNull('deleted_at')
        ->where('fiscal_year_id', '!=', $other->id)
        ->first();

    if ($entry) {
        DB::table('journal_entries')->where('id', $entry->id)
            ->update(['fiscal_year_id' => $other->id]);

        check('an entry in the wrong exercice is caught', 'critical',
            find($health->checks(), 'wrong_exercice')['state']);
    }
} finally {
    DB::rollBack();
}

/* --- a mapping pointing at a reversed entry -------------------------- */
DB::beginTransaction();
try {
    $mapping = DB::table('transaction_mappings')
        ->where('status', 'active')->whereNotNull('journal_entry_id')->first();

    DB::table('journal_entries')->where('id', $mapping->journal_entry_id)
        ->update(['is_reversed' => 1]);

    check('a mapping pointing at a reversed entry is caught', 'critical',
        find($health->checks(), 'mapping_at_reversed')['state']);
} finally {
    DB::rollBack();
}

/* --- a mapping with no entry ----------------------------------------- */
DB::beginTransaction();
try {
    $before = find($health->checks(), 'mapping_no_entry')['count'];

    $mapping = DB::table('transaction_mappings')
        ->where('status', 'active')->whereNotNull('journal_entry_id')->first();

    DB::table('transaction_mappings')->where('id', $mapping->id)
        ->update(['journal_entry_id' => null]);

    check('a mapping with no entry is caught', $before + 1,
        find($health->checks(), 'mapping_no_entry')['count']);
} finally {
    DB::rollBack();
}

/* --- a duplicate active mapping -------------------------------------- *
 * This one cannot be provoked: unique_transaction_mapping on
 * (transaction_type, transaction_id) refuses the second row outright. So the
 * guard is asserted where it actually lives — in the schema — and the check
 * stays in the service for the day that index changes.
 */
$uniqueIndex = collect(DB::select('SHOW INDEX FROM transaction_mappings'))
    ->where('Key_name', 'unique_transaction_mapping')
    ->where('Non_unique', 0)
    ->pluck('Column_name')
    ->sort()
    ->values()
    ->all();

check('the database itself forbids a duplicate mapping',
    ['transaction_id', 'transaction_type'], $uniqueIndex);

DB::beginTransaction();
try {
    $mapping = (array) DB::table('transaction_mappings')->where('status', 'active')->first();
    unset($mapping['id']);

    $refused = false;

    try {
        DB::table('transaction_mappings')->insert($mapping);
    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
        $refused = true;
    }

    ok('and a second one is refused rather than written', $refused);
    check('so the check has nothing to report', 'ok',
        find($health->checks(), 'mapping_duplicate')['state']);
} finally {
    DB::rollBack();
}

echo "\n=== 5. a reversal of several originals is not a double posting ===\n";

// expense_reversal #562 carries three live entries, each reversing a different
// original. Treating that as a duplicate posting would report a fault on every
// correctly corrected transaction.
$reversalTypes = DB::table('journal_entries')
    ->where('reference_type', 'like', '%\_reversal')
    ->where('is_reversed', 0)
    ->select('reference_type', 'reference_id', DB::raw('count(*) as n'))
    ->groupBy('reference_type', 'reference_id')
    ->having('n', '>', 1)
    ->get();

ok('this database has such a case to prove it on', $reversalTypes->count() > 0);
check('and it is not reported as a double posting', 'ok',
    find($baseline, 'entry_duplicate')['state']);

echo "\n=== nothing was left behind ===\n";

$closing = [
    'entries' => DB::table('journal_entries')->count(),
    'lines' => DB::table('journal_entry_lines')->count(),
    'mappings' => DB::table('transaction_mappings')->count(),
    'incomes' => DB::table('incomes')->count(),
    'debits' => number_format((float) DB::table('journal_entries')->sum('total_debit'), 2, '.', ''),
];

foreach ($opening as $key => $value) {
    check(sprintf('%-9s is still %s', $key, $value), $value, $closing[$key]);
}

// And the checks still say exactly what they said at the start.
$final = $health->checks();
check('the verdict is unchanged by having been tested', $health->concerns($baseline),
    $health->concerns($final));

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
