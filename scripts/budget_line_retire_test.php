<?php

/**
 * Retiring a budget line.
 *
 * Deleting a line has always been guarded: it refuses while transactions point
 * at the line, and refuses while other lines sit under it. Retiring — the
 * everyday action, the one with a button next to every row — was guarded by
 * nothing at all, and does most of the same damage.
 *
 * Two faults, both measured on this database before they were fixed:
 *
 *   - Retiring a funded line took 35,357,704 off the rendered sheet. The
 *     transactions keep their budget_line_id, so the money is neither on the
 *     sheet nor counted as unallocated, and the reconciliation raised nothing.
 *     That is the quietest way to lose money in this system.
 *
 *   - Retiring a heading left its five children on the sheet, filed under
 *     nothing — the exact state delete() exists to prevent.
 *
 * The controller is called directly rather than over HTTP: these checks run
 * inside a transaction that is rolled back, and putting a full request
 * lifecycle inside an open transaction is how rows escape one.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BudgetLine;
use App\Services\BudgetActualsService;
use App\Services\BudgetReconciliationService;
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

Auth::guard('web')->login(App\User::first());

$opening = [
    'lines' => DB::table('budget_lines')->count(),
    'retired' => DB::table('budget_lines')->where('status', false)->count(),
];

printf("Before: %d budget lines, %d retired\n\n", $opening['lines'], $opening['retired']);

$controller = app(App\Http\Controllers\Admin\BudgetLineController::class);
$actuals = app(BudgetActualsService::class);

// The line carrying the most money, so the consequence is unmissable.
$totals = $actuals->forPeriod()['lines'];
arsort($totals);
$fundedId = array_key_first($totals);
$fundedAmount = (float) $totals[$fundedId];

DB::beginTransaction();

try {
    $funded = BudgetLine::findOrFail($fundedId);

    printf("Funded line: %s \"%s\" carrying %s\n\n",
        $funded->code, $funded->name, number_format($fundedAmount, 2));

    echo "=== 1. money on a retired line is reported, not lost ===\n";

    $before = app(BudgetReconciliationService::class)->reconcile();
    $beforeRetiredIssues = count(array_filter($before['issues'],
        fn ($i) => $i['type'] === 'retired_line_with_money'));

    check('nothing is reported while the line is active', 0, $beforeRetiredIssues);

    // Retired at the model, which is the state the screen can leave it in.
    $funded->status = false;
    $funded->save();

    ok('it has left the rendered sheet', !BudgetLine::sheet()->contains('id', $fundedId));

    $visible = 0.0;
    $nowTotals = $actuals->forPeriod()['lines'];
    foreach (BudgetLine::sheet() as $line) {
        $visible += (float) ($nowTotals[$line->id] ?? 0);
    }

    ok('and its money has left the sheet with it',
        abs(array_sum($nowTotals) - $visible - $fundedAmount) < 0.01);

    // The money is still attributed, so it is NOT unallocated — which is
    // exactly why nothing used to notice.
    $unallocated = $actuals->forPeriod()['unallocated'];
    ok('it is not counted as unallocated either',
        abs(array_sum($unallocated) - array_sum($before['unallocated'])) < 0.01);

    $after = app(BudgetReconciliationService::class)->reconcile();
    $reported = array_values(array_filter($after['issues'],
        fn ($i) => $i['type'] === 'retired_line_with_money'));

    check('the reconciliation now reports exactly one such line', 1, count($reported));
    ok('naming the line', str_contains($reported[0]['detail'] ?? '', (string) $funded->code));
    ok('and the amount', str_contains($reported[0]['detail'] ?? '', number_format($fundedAmount, 2)));
    check('so the sheet does not claim to agree', false, $after['agrees']);

    // Put it back: the report must go away on its own.
    $funded->status = true;
    $funded->save();

    $restored = app(BudgetReconciliationService::class)->reconcile();
    check('restoring the line clears the report', 0,
        count(array_filter($restored['issues'], fn ($i) => $i['type'] === 'retired_line_with_money')));

    echo "\n=== 2. the screen warns before it happens ===\n";

    // Through the controller, which is the path with the button on it.
    //
    // What is asserted is the behaviour, not the wording: retiring a funded
    // line is ALLOWED — a school that has stopped using a category may take it
    // off next year's form — and the durable report in section 1 is what stops
    // the money going quiet. Reaching into Flasher to read the message back
    // would test the notification library rather than this screen.
    $controller->toggle($fundedId);
    check('retiring a funded line is allowed, not refused', false,
        (bool) BudgetLine::find($fundedId)->status);

    $controller->toggle($fundedId);
    check('and it can be put back', true, (bool) BudgetLine::find($fundedId)->status);

    echo "\n=== 3. a heading cannot be retired over its children ===\n";

    $header = BudgetLine::where('is_header', true)
        ->whereHas('children', fn ($q) => $q->where('status', true))
        ->first();

    if (!$header) {
        echo "  (no heading with active children on this database)\n";
    } else {
        $childCount = $header->children()->where('status', true)->count();

        printf("  heading \"%s\" has %d active line(s) under it\n", $header->name, $childCount);

        $controller->toggle($header->id);

        check('the heading is still on the sheet', true, (bool) $header->fresh()->status);
        check('and its children are untouched', $childCount,
            $header->children()->where('status', true)->count());

        // No child left filed under nothing.
        $orphans = 0;
        foreach (BudgetLine::sheet() as $line) {
            if ($line->parent_id && !BudgetLine::sheet()->contains('id', $line->parent_id)) {
                $orphans++;
            }
        }

        check('no line on the sheet sits under a heading that is not there', 0, $orphans);

        // Retire the children first, and then the heading may go.
        $header->children()->update(['status' => false]);
        $controller->toggle($header->id);

        check('with its children gone, the heading can be retired', false,
            (bool) $header->fresh()->status);

        // Put the branch back before moving on. The sections below count
        // retired-with-money reports, and leaving five funded lines retired
        // here made them fail for a reason that had nothing to do with what
        // they were testing.
        $header->children()->update(['status' => true]);
        $header->status = true;
        $header->save();
    }

    echo "\n=== 4. an empty line retires without complaint ===\n";

    $empty = BudgetLine::where('is_header', false)
        ->where('status', true)
        ->whereNotIn('id', array_keys(array_filter($totals)))
        ->first();

    if (!$empty) {
        echo "  (every active line carries money on this database)\n";
    } else {
        $controller->toggle($empty->id);
        check('it retires', false, (bool) $empty->fresh()->status);

        $clean = app(BudgetReconciliationService::class)->reconcile();
        check('and raises no retired-with-money report', 0,
            count(array_filter($clean['issues'], fn ($i) => $i['type'] === 'retired_line_with_money')));
    }

    echo "\n=== 5. teeth: without the check, the loss is silent ===\n";

    $funded->refresh();
    $funded->status = false;
    $funded->save();

    $withCheck = app(BudgetReconciliationService::class)->reconcile();
    $flagged = count(array_filter($withCheck['issues'],
        fn ($i) => $i['type'] === 'retired_line_with_money'));

    ok('the check is what reports it', $flagged === 1);

    // What the other reports would have said on their own: nothing.
    $others = array_filter($withCheck['issues'], fn ($i) => $i['type'] !== 'retired_line_with_money');
    $mentionsLine = false;
    foreach ($others as $issue) {
        if (str_contains($issue['detail'], (string) $funded->code)) {
            $mentionsLine = true;
        }
    }

    check('no other report mentions the line at all', false, $mentionsLine);
    printf("          (%s would otherwise have gone missing in silence)\n",
        number_format($fundedAmount, 2));
} catch (\Throwable $e) {
    echo "\n  *** the suite threw: " . $e->getMessage() . "\n";
    echo '      ' . $e->getFile() . ':' . $e->getLine() . "\n";
    $fail++;
} finally {
    DB::rollBack();
}

echo "\n=== nothing was left behind ===\n";

$closing = [
    'lines' => DB::table('budget_lines')->count(),
    'retired' => DB::table('budget_lines')->where('status', false)->count(),
];

foreach ($opening as $key => $value) {
    check(sprintf('%-8s is still %s', $key, $value), $value, $closing[$key]);
}

$final = app(BudgetReconciliationService::class)->reconcile();
check('and no line is left retired holding money', 0,
    count(array_filter($final['issues'], fn ($i) => $i['type'] === 'retired_line_with_money')));

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
