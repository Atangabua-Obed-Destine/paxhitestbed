<?php
/**
 * Does `ledger:sync-preview` tell the truth — and does it really write nothing?
 *
 * The preview is what someone will trust before syncing on production, so it
 * is tested against states where the answer is known:
 *
 *   - as the database stands: agrees, nothing to post;
 *   - with one real expense un-posted: off by exactly that amount now, and
 *     agreeing after the simulated sync;
 *   - with that expense's category mapping switched off too: will NOT agree,
 *     and says which category.
 *
 * Each state is set up inside an outer transaction the test rolls back. The
 * command runs in the same process and connection, so it sees that state, and
 * its own simulation nests inside it — which also lets the test check that the
 * command undid its own work and nothing else.
 *
 * Usage: php scripts/ledger_sync_preview_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DefaultAccountMapping;
use App\Models\JournalEntry;
use App\Models\TransactionMapping;
use App\Services\TransactionAutoMapService;
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

function preview(int $budget): string
{
    Artisan::call('ledger:sync-preview', ['budget' => $budget]);

    return Artisan::output();
}

$budget = (int) (DB::table('budgets')->where('id', 64)->value('id')
    ?? DB::table('budgets')->where('is_institutional', 1)->orderByDesc('id')->value('id'));

if (!$budget) {
    echo "  SKIP  no budget sheet to preview\n";
    exit(0);
}

$range = DB::table('budgets')->where('id', $budget)->first(['start_date', 'end_date']);

// A posted expense inside the sheet's range whose category has a mapping.
$expense = DB::table('expenses as e')
    ->join('transaction_mappings as m', function ($j) {
        $j->on('m.transaction_id', '=', 'e.id')->where('m.transaction_type', 'expense')->where('m.status', 'active');
    })
    ->join('default_account_mappings as d', function ($j) {
        $j->on('d.category_id', '=', 'e.category_id')->where('d.mapping_type', 'expense_category')->where('d.status', 'active');
    })
    ->whereBetween('e.date', [$range->start_date, $range->end_date])
    ->orderByDesc('e.amount')
    ->select('e.id', 'e.amount', 'e.category_id', 'd.id as default_id')
    ->first();

$entriesAtStart = JournalEntry::withTrashed()->count();
$mappingsAtStart = TransactionMapping::count();

echo "\n== As the database stands ==\n";

$out = preview($budget);

check('the preview runs and reports on the sheet', str_contains($out, 'Budget sheet ' . $budget));
check('it describes itself as a simulation', str_contains($out, 'Simulation only'));

$agreesNow = str_contains($out, 'VERDICT: WILL AGREE');
check('it gives a verdict', $agreesNow || str_contains($out, 'VERDICT: WILL NOT AGREE'));

echo "\n== One expense un-posted ==\n";

if (!$expense) {
    echo "  SKIP  no posted, mapped expense in the sheet's range\n";
} else {
    $amount = number_format((float) $expense->amount, 0, '.', ',');

    DB::beginTransaction();

    try {
        app(TransactionAutoMapService::class)->reverse('expense', $expense->id);

        $out = preview($budget);

        check("before syncing, it reports Expenditure off by exactly {$amount}",
            (bool) preg_match('/Expenditure \(class 6\)\s*\|[^|]*\|[^|]*\|\s*' . preg_quote($amount, '/') . '\s*\|\s*OFF/', $out),
            'no Expenditure row showing ' . $amount . ' OFF');

        check('it counts the one transaction as postable', str_contains($out, 'Syncing would post:   1, worth ' . $amount));

        if ($agreesNow) {
            check('and says the sheet will agree after syncing', str_contains($out, 'VERDICT: WILL AGREE'));
        } else {
            echo "  SKIP  the sheet disagrees for other reasons already, so WILL AGREE cannot be expected\n";
        }

        // The command must have undone its own posting and nothing else: the
        // expense is still un-posted, exactly as this test left it.
        check('after the preview, the expense is still un-posted — the simulation rolled itself back',
            !TransactionMapping::where('transaction_type', 'expense')->where('transaction_id', $expense->id)
                ->where('status', 'active')->exists());

        // And your rule, through the preview: without a mapping, it will not agree.
        DefaultAccountMapping::where('id', $expense->default_id)->update(['status' => 'inactive']);

        $out = preview($budget);
        $category = (string) DB::table('expense_categories')->where('id', $expense->category_id)->value('title');

        check('with its category mapping switched off, the verdict is WILL NOT AGREE', str_contains($out, 'VERDICT: WILL NOT AGREE'));
        check('and it names the category that needs a mapping', $category !== '' && str_contains($out, $category), $category);
        check('and counts it as refused, not posted', str_contains($out, 'Syncing would post:   0'));
    } finally {
        DB::rollBack();
    }
}

echo "\n== It wrote nothing ==\n";

check('no journal entry was added', JournalEntry::withTrashed()->count() === $entriesAtStart,
    JournalEntry::withTrashed()->count() . ' vs ' . $entriesAtStart);
check('no transaction mapping was added or changed', TransactionMapping::count() === $mappingsAtStart);

if ($expense) {
    check('the expense used in the test is posted again, as it was',
        TransactionMapping::where('transaction_type', 'expense')->where('transaction_id', $expense->id)
            ->where('status', 'active')->exists());
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
