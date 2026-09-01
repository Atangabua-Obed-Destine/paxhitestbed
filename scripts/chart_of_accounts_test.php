<?php
/**
 * The chart of accounts holds together.
 *
 * These are the invariants the ledger, the budget sheet and the daybook all
 * lean on. Each one, broken, is a report that quietly reads wrong rather than
 * an error anyone would see: a posting on a heading disappears from every
 * total built by summing leaves; a mapping pointing at an inactive account
 * writes an entry nobody can find; a class that disagrees with its own code
 * files an expense under income.
 *
 * Usage: php scripts/chart_of_accounts_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChartOfAccount;
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

echo "\n== The ledger balances ==\n";

$t = DB::table('journal_entry_lines')->selectRaw('SUM(debit) d, SUM(credit) c')->first();
check('total debits equal total credits', abs($t->d - $t->c) < 0.01,
    number_format($t->d, 2) . ' vs ' . number_format($t->c, 2));

// An entry that balances in aggregate but not individually still produces a
// clean trial balance, so the per-entry check is the one that has teeth.
$unbalanced = DB::table('journal_entry_lines')
    ->select('journal_entry_id')
    ->groupBy('journal_entry_id')
    ->havingRaw('ABS(SUM(debit) - SUM(credit)) > 0.01')
    ->get()->count();
check('every entry balances on its own', $unbalanced === 0, $unbalanced . ' unbalanced');

$single = DB::table('journal_entry_lines')
    ->select('journal_entry_id')
    ->groupBy('journal_entry_id')
    ->havingRaw('COUNT(*) < 2')
    ->get()->count();
check('no entry has a single line', $single === 0, $single . ' one-sided');

echo "\n== Postings land only where they can be counted ==\n";

// A heading is a subtotal. Anything posted to it is summed twice by a report
// that adds the heading to its children, or lost entirely by one that sums
// leaves — which is what the budget sheet does.
$onHeadings = DB::table('journal_entry_lines as jl')
    ->join('chart_of_accounts as a', 'a.id', '=', 'jl.account_id')
    ->where('a.account_category', 'heading')->count();
check('nothing is posted to a heading', $onHeadings === 0, $onHeadings . ' lines');

$onInactive = DB::table('journal_entry_lines as jl')
    ->join('chart_of_accounts as a', 'a.id', '=', 'jl.account_id')
    ->where('a.is_active', 0)->count();
check('nothing is posted to an inactive account', $onInactive === 0, $onInactive . ' lines');

$orphanLines = DB::table('journal_entry_lines as jl')
    ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'jl.account_id')
    ->whereNull('a.id')->count();
check('every line names an account that exists', $orphanLines === 0, $orphanLines . ' orphans');

echo "\n== Mappings point somewhere postable ==\n";

// A mapping is what turns a payment into an entry. Aimed at a heading or a
// disabled account it still saves, and the failure only surfaces later as a
// figure missing from the sheet.
foreach (['debit_account_id' => 'debit', 'credit_account_id' => 'credit'] as $column => $side) {
    $bad = DB::table('default_account_mappings as m')
        ->join('chart_of_accounts as a', 'a.id', '=', 'm.' . $column)
        ->where(function ($q) {
            $q->where('a.account_category', 'heading')->orWhere('a.is_active', 0);
        })->count();
    check("no mapping's {$side} is a heading or inactive", $bad === 0, $bad . ' mappings');

    $missing = DB::table('default_account_mappings as m')
        ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'm.' . $column)
        ->whereNull('a.id')->count();
    check("every mapping's {$side} account exists", $missing === 0, $missing . ' mappings');
}

echo "\n== The hierarchy is consistent ==\n";

$badParent = 0;
$badClass = 0;
$orphan = 0;

foreach (ChartOfAccount::all() as $a) {
    if ((int) $a->class_number !== (int) substr($a->account_code, 0, 1)) {
        $badClass++;
    }

    if ($a->parent_id === null) {
        continue;
    }

    $parent = ChartOfAccount::find($a->parent_id);

    if (!$parent) {
        $orphan++;
        continue;
    }

    // 245 must sit under 24, not under 62. The code carries the hierarchy, so
    // the parent's code has to be a prefix of the child's.
    if (strpos($a->account_code, $parent->account_code) !== 0) {
        $badParent++;
    }
}

check('every child sits under a code that prefixes it', $badParent === 0, $badParent . ' misfiled');
check('class number matches the leading digit', $badClass === 0, $badClass . ' mismatched');
check('no account points at a missing parent', $orphan === 0, $orphan . ' orphans');

// An account with children must be a heading, or the same money is reachable
// twice: once on the parent and once below it.
$parentIds = ChartOfAccount::whereNotNull('parent_id')->distinct()->pluck('parent_id');
$postableParents = ChartOfAccount::whereIn('id', $parentIds)
    ->where('account_category', '!=', 'heading')->pluck('account_code');
check('no account is both a parent and postable', $postableParents->isEmpty(),
    $postableParents->implode(', '));

echo "\n== The accounts that other accounts depend on ==\n";

// Each of these was missing while its counterpart already existed, so the
// counterpart had nothing to work against.
$pairs = [
    ['683', '245', 'vehicle depreciation has a vehicle asset to depreciate'],
    ['79', '691', 'a provision can be released as well as raised'],
];

foreach ($pairs as [$a, $b, $label]) {
    check($label,
        ChartOfAccount::where('account_code', $a)->exists()
        && ChartOfAccount::where('account_code', $b)->exists());
}

foreach (['16' => 'borrowings', '419' => 'fees paid in advance', '585' => 'transfers between cash accounts',
          '641' => 'taxes', '651' => 'bad debts', '701' => 'trading income'] as $code => $what) {
    check("there is an account for {$what} ({$code})",
        ChartOfAccount::where('account_code', $code)->where('is_active', 1)->exists());
}

echo "\n== Every account is fit to be shown ==\n";

check('all accounts carry a French name',
    ChartOfAccount::whereNull('account_name_fr')->orWhere('account_name_fr', '')->doesntExist());

$dupes = ChartOfAccount::select('account_code')->groupBy('account_code')
    ->havingRaw('COUNT(*) > 1')->pluck('account_code');
check('account codes are unique', $dupes->isEmpty(), $dupes->implode(', '));

// account_type drives the sign a report gives a balance. Wrong, an expense is
// added to income rather than taken from it.
// Class 1 carries both halves of long-term funding — capital and reserves are
// equity, borrowings are a liability — so it accepts either.
$expected = [
    1 => ['equity', 'liability'],
    2 => ['asset'],
    3 => ['asset'],
    5 => ['asset'],
    6 => ['expense'],
    7 => ['revenue'],
];
$mismatched = [];

foreach (ChartOfAccount::whereIn('class_number', array_keys($expected))->get() as $a) {
    // Class 2 holds one contra-asset (28 Amortissements) and class 4 is mixed
    // by nature, so only the unambiguous classes are asserted here.
    if ($a->class_number == 2 && strpos($a->account_code, '28') === 0) {
        continue;
    }

    if (!in_array($a->account_type, $expected[$a->class_number], true)) {
        $mismatched[] = $a->account_code . ' is ' . $a->account_type;
    }
}

check('account type matches the class', $mismatched === [], implode(', ', $mismatched));

echo "\n== Re-running the seeder changes nothing ==\n";

// It is written with updateOrCreate precisely so it can be re-run on a live
// database. If that ever stops being true, a deploy duplicates the chart.
$before = ChartOfAccount::count();
$snapshot = ChartOfAccount::orderBy('account_code')->pluck('account_category', 'account_code')->toArray();

Illuminate\Support\Facades\Artisan::call('db:seed', [
    '--class' => 'Database\Seeders\OhadaMissingAccountsSeeder',
    '--force' => true,
]);

check('no accounts were added the second time', ChartOfAccount::count() === $before,
    $before . ' then ' . ChartOfAccount::count());
check('no categories moved',
    ChartOfAccount::orderBy('account_code')->pluck('account_category', 'account_code')->toArray() === $snapshot);

echo "\n$passed passed, $failed failed\n";
