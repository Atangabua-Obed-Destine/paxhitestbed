<?php

/**
 * Retiring a stale orphan mapping.
 *
 * The repair is narrow on purpose: it clears mappings whose transaction has
 * been deleted and which never posted anything. The two things that must stay
 * true of it are that an orphan carrying a live entry is never swept up with
 * them — that would strand money in the ledger with nothing pointing at it —
 * and that no total moves, since nothing was posted to begin with. Both are
 * asserted below, in both directions.
 *
 * Every write is inside a transaction that is rolled back, and the closing
 * section proves it with absolute counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Accounting\LedgerHealth;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

function check($what, $expected, $actual) {
    global $pass, $fail;

    if ($expected === $actual) { $pass++; return; }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

$of = function (array $checks, string $key) {
    foreach ($checks as $c) { if ($c['key'] === $key) return $c; }
    return null;
};

$opening = [
    'active'   => DB::table('transaction_mappings')->where('status', 'active')->count(),
    'reversed' => DB::table('transaction_mappings')->where('status', 'reversed')->count(),
    'entries'  => DB::table('journal_entries')->count(),
    'lines'    => DB::table('journal_entry_lines')->count(),
    'debit'    => (float) DB::table('journal_entry_lines')->sum('debit'),
    'credit'   => (float) DB::table('journal_entry_lines')->sum('credit'),
];

DB::beginTransaction();

/*
 * Both orphans are built here rather than borrowed from whatever the books
 * happen to contain. An earlier version leaned on two stale rows that really
 * were there — and passed, right up until the repair it was testing cleared
 * them and left it asserting against nothing.
 */
$live = DB::table('journal_entries')->whereNull('deleted_at')->where('is_reversed', 0)->first();
$ghost = (int) DB::table('expenses')->max('id') + 99999;

$orphan = function (int $txId, ?int $entryId, float $amount) {
    DB::table('transaction_mappings')->insert([
        'transaction_type' => 'expense', 'transaction_id' => $txId,
        'debit_account_id' => 159, 'credit_account_id' => 151, 'amount' => $amount,
        'transaction_date' => '2026-10-01', 'description' => 'orphan repair test',
        'journal_entry_id' => $entryId, 'mapped_at' => now(), 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('transaction_mappings')->where('transaction_id', $txId)->value('id');
};

$postedId = $orphan($ghost, $live->id, 1234.56);
$staleA = $orphan($ghost + 1, null, 158994.93);
$staleB = $orphan($ghost + 2, null, 158994.93);

echo "=== both kinds of orphan are seen, and told apart ===\n";

$health = new LedgerHealth();
$checks = $health->checks();

check('the posted orphan is critical', 1, $of($checks, 'mapping_orphan')['count']);
check('and reads as critical', 'critical', $of($checks, 'mapping_orphan')['state']);
check('the stale ones are a warning', 2, $of($checks, 'mapping_orphan_unposted')['count']);
check('and read as a warning', 'warning', $of($checks, 'mapping_orphan_unposted')['state']);
check('neither is re-listed as missing an entry', 0, $of($checks, 'mapping_no_entry')['count']);

echo "\n=== only the stale ones are retired ===\n";

$health->forget();
$retired = $health->retireUnpostedOrphans();

check('two rows retired', 2, count($retired));
check('the posted orphan is untouched', 'active',
    DB::table('transaction_mappings')->where('id', $postedId)->value('status'));
check('the stale rows are no longer active', 0,
    DB::table('transaction_mappings')->whereIn('id', [$staleA, $staleB])->where('status', 'active')->count());
check('they read as reversed', 2,
    DB::table('transaction_mappings')->whereIn('id', [$staleA, $staleB])->where('status', 'reversed')->count());

echo "\n=== the checks agree afterwards ===\n";

$health->forget();
$after = $health->checks();

check('no stale orphans left', 0, $of($after, 'mapping_orphan_unposted')['count']);
check('the posted orphan still stands', 1, $of($after, 'mapping_orphan')['count']);
check('and is still not listed as missing an entry', 0, $of($after, 'mapping_no_entry')['count']);

echo "\n=== the ledger did not move ===\n";

check('journal entries unchanged', $opening['entries'], DB::table('journal_entries')->count());
check('lines unchanged', $opening['lines'], DB::table('journal_entry_lines')->count());
check('debits unchanged', $opening['debit'], (float) DB::table('journal_entry_lines')->sum('debit'));
check('credits unchanged', $opening['credit'], (float) DB::table('journal_entry_lines')->sum('credit'));

echo "\n=== running it twice is harmless ===\n";

$health->forget();
check('a second run retires nothing', 0, count($health->retireUnpostedOrphans()));

DB::rollBack();

echo "\n=== nothing was left behind ===\n";

check(sprintf('active mappings back to %d', $opening['active']), $opening['active'],
    DB::table('transaction_mappings')->where('status', 'active')->count());
check(sprintf('reversed mappings back to %d', $opening['reversed']), $opening['reversed'],
    DB::table('transaction_mappings')->where('status', 'reversed')->count());
check(sprintf('entries back to %d', $opening['entries']), $opening['entries'],
    DB::table('journal_entries')->count());
check(sprintf('lines back to %d', $opening['lines']), $opening['lines'],
    DB::table('journal_entry_lines')->count());

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
