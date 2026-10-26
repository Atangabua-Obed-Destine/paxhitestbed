<?php

/**
 * Re-posting a transaction whose entry has drifted.
 *
 * The reason this command exists is itself asserted here: re-saving a
 * transaction that is already correct does nothing, because Eloquent only
 * fires `updated` when something is dirty. If that ever stops being true the
 * first section fails, and the command can be reconsidered.
 *
 * Every write is inside a transaction that is rolled back, and the closing
 * section proves it with absolute counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Income;
use App\Services\Accounting\LedgerHealth;
use Illuminate\Support\Facades\Artisan;
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

function ok($what, $cond) { check($what, true, (bool) $cond); }

$of = function (array $checks, string $key) {
    foreach ($checks as $c) { if ($c['key'] === $key) return $c; }
    return null;
};

$opening = [
    'entries'  => DB::table('journal_entries')->count(),
    'lines'    => DB::table('journal_entry_lines')->count(),
    'active'   => DB::table('transaction_mappings')->where('status', 'active')->count(),
    'debit'    => (float) DB::table('journal_entry_lines')->sum('debit'),
    'credit'   => (float) DB::table('journal_entry_lines')->sum('credit'),
];

DB::beginTransaction();

// Drag a live entry's date out of step with its income, which is exactly the
// shape date_drift reports. The income itself stays correct throughout.
$map = DB::table('transaction_mappings')->where('transaction_type', 'income')
    ->where('status', 'active')->whereNotNull('journal_entry_id')->first();
$income = Income::find($map->transaction_id);
$trueDate = substr((string) $income->date, 0, 10);

DB::table('journal_entries')->where('id', $map->journal_entry_id)
    ->update(['entry_date' => '2026-01-15']);

$health = new LedgerHealth();

echo "=== the drift is seen ===\n";

$drift = $of($health->checks(), 'date_drift');
check('one entry has drifted', 1, $drift['count']);
check('and it is critical', 'critical', $drift['state']);

echo "\n=== re-saving does nothing, which is why this command exists ===\n";

$income->save();
check('a plain save leaves the entry where it was', '2026-01-15',
    substr((string) DB::table('journal_entries')->where('id', $map->journal_entry_id)->value('entry_date'), 0, 10));

$income->update(['amount' => $income->amount, 'date' => $income->date, 'category_id' => $income->category_id]);
check('an update with identical values leaves it too', '2026-01-15',
    substr((string) DB::table('journal_entries')->where('id', $map->journal_entry_id)->value('entry_date'), 0, 10));

check('so the drift is still reported', 1, (new LedgerHealth())->checks()[0] ? $of((new LedgerHealth())->checks(), 'date_drift')['count'] : -1);

echo "\n=== a dry run changes nothing ===\n";

Artisan::call('accounting:repost', ['--type' => 'income']);
$dry = Artisan::output();

ok('it names the drifted transaction', str_contains($dry, '#' . $income->id));
ok('and says it is a dry run', str_contains($dry, 'Dry run'));
check('the entry is untouched', '2026-01-15',
    substr((string) DB::table('journal_entries')->where('id', $map->journal_entry_id)->value('entry_date'), 0, 10));
check('entry count unchanged by the dry run', $opening['entries'], DB::table('journal_entries')->count());

echo "\n=== --commit re-posts it onto the transaction's own date ===\n";

Artisan::call('accounting:repost', ['--type' => 'income', '--commit' => true]);
$out = Artisan::output();

ok('it reports one re-posted', str_contains($out, 're-posted : 1'));

$now = DB::table('transaction_mappings')->where('transaction_type', 'income')
    ->where('transaction_id', $income->id)->where('status', 'active')->first();

ok('the mapping points at a NEW entry', $now && (int) $now->journal_entry_id !== (int) $map->journal_entry_id);
check('dated as the transaction is', $trueDate,
    substr((string) DB::table('journal_entries')->where('id', $now->journal_entry_id)->value('entry_date'), 0, 10));
check('the old entry is reversed, not deleted', 1,
    (int) DB::table('journal_entries')->where('id', $map->journal_entry_id)->value('is_reversed'));

echo "\n=== and the books agree again ===\n";

$after = (new LedgerHealth())->checks();
check('no drift left', 0, $of($after, 'date_drift')['count']);
check('nothing became unbalanced', 'ok', $of($after, 'entry_unbalanced')['state']);
check('no transaction gained a second live entry', 'ok', $of($after, 'entry_duplicate')['state']);
check('debits still equal credits',
    round((float) DB::table('journal_entry_lines')->sum('debit'), 2),
    round((float) DB::table('journal_entry_lines')->sum('credit'), 2));

echo "\n=== a second run is a no-op ===\n";

Artisan::call('accounting:repost', ['--type' => 'income', '--commit' => true]);
ok('it finds nothing to do', str_contains(Artisan::output(), 'Nothing has drifted'));

echo "\n=== it refuses what it cannot number ===\n";

check('--id without --type is refused', 1,
    Artisan::call('accounting:repost', ['--id' => 1]));
check('an unknown --type is refused', 1,
    Artisan::call('accounting:repost', ['--type' => 'payroll']));

DB::rollBack();

echo "\n=== nothing was left behind ===\n";

check(sprintf('entries back to %d', $opening['entries']), $opening['entries'],
    DB::table('journal_entries')->count());
check(sprintf('lines back to %d', $opening['lines']), $opening['lines'],
    DB::table('journal_entry_lines')->count());
check(sprintf('active mappings back to %d', $opening['active']), $opening['active'],
    DB::table('transaction_mappings')->where('status', 'active')->count());
check('debits back where they were', $opening['debit'],
    (float) DB::table('journal_entry_lines')->sum('debit'));
check('credits back where they were', $opening['credit'],
    (float) DB::table('journal_entry_lines')->sum('credit'));

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
