<?php
/**
 * Installing the defaults never overwrites what somebody set by hand.
 *
 * The seeders used to run updateOrCreate, which re-runs without duplicating but
 * silently rewrites the existing row. On a live system that is not safe: it
 * wipes the budget-line ordering the finance office dragged into place,
 * repoints mappings that were deliberately moved, and reactivates accounts that
 * were deliberately switched off.
 *
 * Idempotent and safe are different properties, and this suite tests the second
 * one. Every mutation runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/defaults_install_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use Illuminate\Support\Facades\Artisan;
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

/** Everything the seeders could possibly touch, as one comparable value. */
function snapshot(): string
{
    return md5(json_encode([
        ChartOfAccount::orderBy('id')->get([
            'id', 'account_code', 'account_name', 'account_name_fr', 'parent_id',
            'account_category', 'account_type', 'normal_balance', 'is_active', 'is_system',
        ])->toArray(),
        BudgetLine::orderBy('id')->get([
            'id', 'code', 'name', 'section', 'is_header', 'parent_id',
            'sort_order', 'status', 'faculty_id',
        ])->toArray(),
        DefaultAccountMapping::orderBy('id')->get([
            'id', 'mapping_type', 'category_id', 'budget_line_id',
            'debit_account_id', 'credit_account_id', 'status',
        ])->toArray(),
    ]));
}

echo "\n== A run without --commit writes nothing ==\n";

$before = snapshot();
Artisan::call('defaults:install');
$output = Artisan::output();

check('the database is byte-identical afterwards', snapshot() === $before);
check('and it says so', strpos($output, 'Rolled back') !== false);
check('the report names each seeder', substr_count($output, 'already present') >= 5,
    substr_count($output, 'already present') . ' lines');

echo "\n== Hand-made configuration survives a re-install ==\n";

DB::beginTransaction();
try {
    // Exactly the four things a person changes on these screens.
    $line = BudgetLine::whereNotNull('code')->where('is_header', false)->first();
    $line->update(['sort_order' => 99999, 'name' => 'ZZ Renamed By Hand']);

    $mapping = DefaultAccountMapping::whereNotNull('budget_line_id')->first();
    $elsewhere = BudgetLine::where('id', '!=', $mapping->budget_line_id)
        ->where('is_header', false)->first();
    $mapping->update(['budget_line_id' => $elsewhere->id]);

    $account = ChartOfAccount::where('account_code', '646')->first();
    $account->update(['is_active' => 0, 'account_name' => 'ZZ Renamed Account']);

    $tuition = BudgetLine::where('code', '610')->first();
    $tuition?->update(['faculty_id' => null]);

    Artisan::call('defaults:install', ['--commit' => true]);

    $line->refresh();
    $mapping->refresh();
    $account->refresh();

    check('a dragged sort_order is untouched', (int) $line->sort_order === 99999,
        'became ' . $line->sort_order);
    check('a renamed budget line keeps its name', $line->name === 'ZZ Renamed By Hand',
        'became ' . $line->name);
    check('a repointed mapping stays where it was put',
        (int) $mapping->budget_line_id === (int) $elsewhere->id,
        'moved to ' . $mapping->budget_line_id);
    check('a deactivated account stays deactivated', (int) $account->is_active === 0);
    check('a renamed account keeps its name', $account->account_name === 'ZZ Renamed Account',
        'became ' . $account->account_name);
} finally {
    DB::rollBack();
}

check('the fixtures left nothing behind',
    BudgetLine::where('name', 'ZZ Renamed By Hand')->doesntExist()
    && ChartOfAccount::where('account_name', 'ZZ Renamed Account')->doesntExist());

echo "\n== Genuine gaps are still filled ==\n";

DB::beginTransaction();
try {
    // 646 has no postings and no mapping, so it can be removed and restored.
    // Force-deleted, because a soft delete is a different case entirely —
    // tested on its own below.
    $code = '646';
    $original = ChartOfAccount::where('account_code', $code)->first()->toArray();
    ChartOfAccount::where('account_code', $code)->forceDelete();

    $linesBefore = BudgetLine::orderBy('id')->get(['id', 'sort_order', 'name'])->toJson();
    $mapsBefore = DefaultAccountMapping::orderBy('id')->get(['id', 'budget_line_id'])->toJson();

    Artisan::call('defaults:install', ['--commit' => true]);

    $restored = ChartOfAccount::where('account_code', $code)->first();

    check('a genuinely absent account comes back', $restored !== null);
    check('with its name', $restored && $restored->account_name === $original['account_name']);
    check('under the right parent', $restored && (int) $restored->parent_id === (int) $original['parent_id']);
    check('and postable', $restored && $restored->account_category === 'detail');
    check('budget lines were not disturbed while filling the gap',
        BudgetLine::orderBy('id')->get(['id', 'sort_order', 'name'])->toJson() === $linesBefore);
    check('nor were the mappings',
        DefaultAccountMapping::orderBy('id')->get(['id', 'budget_line_id'])->toJson() === $mapsBefore);
} finally {
    DB::rollBack();
}

echo "
== A deleted account is not resurrected, and does not break the run ==
";

DB::beginTransaction();
try {
    // account_code is unique across soft-deleted rows too, so an account
    // removed through the interface used to make the whole install throw on
    // the constraint. It is also a deliberate removal, so bringing it back
    // silently would be its own kind of overwriting.
    $account = ChartOfAccount::where('account_code', '648')->first();
    $account->delete();

    $exit = Artisan::call('defaults:install', ['--commit' => true]);
    $output = Artisan::output();

    check('the install still succeeds', $exit === 0, 'exit ' . $exit);
    check('the account stays deleted',
        ChartOfAccount::where('account_code', '648')->doesntExist());
    check('no duplicate was inserted',
        ChartOfAccount::withTrashed()->where('account_code', '648')->count() === 1);
    check('and the report says it was left alone',
        strpos($output, 'left deleted') !== false);
} finally {
    DB::rollBack();
}

echo "\n== The demotion never hides real money ==\n";

DB::beginTransaction();
try {
    // A parent holding postings must be left postable, or those postings
    // disappear from every report that sums leaf accounts.
    $parent = ChartOfAccount::where('account_code', '64')->first();
    $parent->update(['account_category' => 'detail']);

    $entry = DB::table('journal_entry_lines')->first();
    DB::table('journal_entry_lines')->where('id', $entry->id)
        ->update(['account_id' => $parent->id]);

    Artisan::call('defaults:install', ['--commit' => true]);

    $parent->refresh();
    check('a parent that holds postings is not demoted', $parent->account_category === 'detail');
    check('and the run says why', strpos(Artisan::output(), 'still holds postings') !== false);
} finally {
    DB::rollBack();
}

DB::beginTransaction();
try {
    $parent = ChartOfAccount::where('account_code', '64')->first();
    $parent->update(['account_category' => 'detail']);

    Artisan::call('defaults:install', ['--commit' => true]);

    $parent->refresh();
    check('a parent that holds nothing is demoted', $parent->account_category === 'heading');
} finally {
    DB::rollBack();
}

echo "\n== --refresh-names corrects labels and nothing else ==\n";

DB::beginTransaction();
try {
    $account = ChartOfAccount::where('account_code', '571')->first();
    $wasCalled = $account->account_name;
    $account->update(['account_name' => 'ZZ Wrong Name', 'is_active' => 0]);

    $line = BudgetLine::where('is_header', false)->first();
    $line->update(['sort_order' => 88888]);

    Artisan::call('defaults:install', ['--refresh-names' => true, '--commit' => true]);

    $account->refresh();
    $line->refresh();

    check('a wrong name is corrected', $account->account_name === $wasCalled,
        'still ' . $account->account_name);
    check('but is_active is not touched', (int) $account->is_active === 0);
    check('and sort_order is not touched', (int) $line->sort_order === 88888);
} finally {
    DB::rollBack();
}

echo "\n== The repair migration restores the leaf invariant ==\n";

DB::beginTransaction();
try {
    // Recreate the state a deploy produces when migrate runs before seeding:
    // the postings still on 24, no 241, and 24 marked postable.
    $parent = ChartOfAccount::where('account_code', '24')->first();
    $leaf = ChartOfAccount::where('account_code', '241')->first();

    DB::table('journal_entry_lines')->where('account_id', $leaf->id)
        ->update(['account_id' => $parent->id]);
    DB::table('default_account_mappings')->where('debit_account_id', $leaf->id)
        ->update(['debit_account_id' => $parent->id]);
    // Soft-deleted on purpose: the unique key survives, so the migration has
    // to restore this row rather than insert a second 241.
    ChartOfAccount::where('id', $leaf->id)->delete();
    $parent->update(['account_category' => 'detail']);

    $strandedBefore = DB::table('journal_entry_lines')->where('account_id', $parent->id)->count();
    check('the broken state is reproduced', $strandedBefore === 6, $strandedBefore . ' lines on 24');

    $migration = require __DIR__ . '/../database/migrations/2026_09_01_100200_repair_capital_postings_leaf.php';
    $migration->up();

    $parent->refresh();
    $newLeaf = ChartOfAccount::where('account_code', '241')->first();

    check('241 is available again without the seeder', $newLeaf !== null);
    check('and it is the same row, not a duplicate',
        ChartOfAccount::withTrashed()->where('account_code', '241')->count() === 1);
    check('the postings move onto it', $newLeaf
        && DB::table('journal_entry_lines')->where('account_id', $newLeaf->id)->count() === 6);
    check('24 holds nothing', DB::table('journal_entry_lines')->where('account_id', $parent->id)->count() === 0);
    check('and 24 is a heading', $parent->account_category === 'heading');
    check('the mapping followed', DB::table('default_account_mappings')
        ->where('debit_account_id', $parent->id)->count() === 0);

    // Running it again must be a no-op, not a second move.
    $migration->up();
    check('re-running it changes nothing', $newLeaf
        && DB::table('journal_entry_lines')->where('account_id', $newLeaf->id)->count() === 6);
} finally {
    DB::rollBack();
}

echo "\n== The ledger is untouched throughout ==\n";

$t = DB::table('journal_entry_lines')->selectRaw('SUM(debit) d, SUM(credit) c')->first();
check('debits still equal credits', abs($t->d - $t->c) < 0.01,
    number_format($t->d, 2) . ' vs ' . number_format($t->c, 2));
check('the trial balance is unmoved', abs($t->d - 109222820) < 0.01, number_format($t->d, 2));
check('the database is exactly as it started', snapshot() === $before);

echo "\n$passed passed, $failed failed\n";
