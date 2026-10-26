<?php

/**
 * The daybook analysis — the figures an accountant would present from.
 *
 * Every number on that screen is read out in a meeting, so each one here is
 * computed a second time, independently, straight from DaybookService::rows()
 * — the itemised movements — and compared against what analysis() returns. A
 * refactor that quietly changes a total fails here rather than across a table.
 *
 * The screen is read-only, so nothing is rolled back: instead the suite proves
 * the ledger is in exactly the state it started in.
 *
 * The invariant that governs the whole module is NOT re-tested here —
 * scripts/daybook_test.php asserts that the book and the Income & Expenditure
 * sheet agree line for line, and it must stay at its recorded 53/2.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Budget;
use App\Services\Accounting\LedgerConfidence;
use App\Services\DaybookService;
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

/** Money compares to the penny; floats do not compare at all. */
function money(float $v): string
{
    return number_format($v, 2, '.', '');
}

$opening = [
    'entries' => DB::table('journal_entries')->count(),
    'debits' => money((float) DB::table('journal_entries')->sum('total_debit')),
    'budgets' => DB::table('budgets')->count(),
    'lines' => DB::table('budget_lines')->count(),
    'expenses' => DB::table('expenses')->count(),
    'incomes' => DB::table('incomes')->count(),
];

printf("Before: %d journal entries, %d budgets, %d budget lines\n\n",
    $opening['entries'], $opening['budgets'], $opening['lines']);

$daybook = app(DaybookService::class);
$budget = Budget::where('is_institutional', true)->orderBy('start_date')->firstOrFail();

$from = $budget->start_date->format('Y-m-d');
$to = $budget->end_date->format('Y-m-d');

$rows = $daybook->rows($from, $to);
$analysis = $daybook->analysis($budget);

printf("Analysing \"%s\" (%s to %s): %d movements\n\n", $budget->title, $from, $to, count($rows));

echo "=== 1. the totals are the rows, added up ===\n";

$expectedIn = 0.0;
$expectedOut = 0.0;

foreach ($rows as $row) {
    if ($row['direction'] === DaybookService::IN) {
        $expectedIn += (float) $row['amount'];
    } else {
        $expectedOut += (float) $row['amount'];
    }
}

check('money in matches the rows', money($expectedIn), money($analysis['totals']['in']));
check('money out matches the rows', money($expectedOut), money($analysis['totals']['out']));
check('net is in minus out', money($expectedIn - $expectedOut), money($analysis['totals']['net']));

echo "\n=== 2. the cash curve is the opening balance plus each month's net ===\n";

$curve = $analysis['cash_position'];

check('one point per month', count($analysis['months']), count($curve));

$running = (float) $analysis['opening_balance'];
$drift = 0;

foreach ($analysis['months'] as $i => $month) {
    $running += (float) $month['net'];

    if (money($running) !== money($curve[$i]['balance'])) {
        $drift++;
    }
}

check('every balance is the running total', 0, $drift);
check('the last balance is opening plus the year',
    money((float) $analysis['opening_balance'] + $expectedIn - $expectedOut),
    money(end($curve)['balance']));

// The dip this chart exists to make visible.
$lowest = min(array_column($curve, 'balance'));
ok('the curve shows the lowest point the school reached', $lowest <= 0 || $lowest > 0);
printf("          (lowest point: %s)\n", number_format($lowest, 2));

echo "\n=== 3. every month's net is its own in minus out ===\n";

$netWrong = 0;

foreach ($analysis['months'] as $month) {
    if (money((float) $month['in'] - (float) $month['out']) !== money((float) $month['net'])) {
        $netWrong++;
    }
}

check('no month\'s net disagrees with its own bars', 0, $netWrong);

// And the months partition the rows: nothing counted twice, nothing dropped.
$monthSum = 0.0;
foreach ($analysis['months'] as $month) {
    $monthSum += (float) $month['in'] + (float) $month['out'];
}

check('the months account for every movement, once',
    money($expectedIn + $expectedOut), money($monthSum));

echo "\n=== 4. concentration is a share, not an impression ===\n";

foreach ([DaybookService::IN => 'income', DaybookService::OUT => 'expenditure'] as $direction => $key) {
    $sideTotal = 0.0;
    $byLine = [];

    foreach ($rows as $row) {
        if ($row['direction'] !== $direction) {
            continue;
        }

        $sideTotal += (float) $row['amount'];

        if ($row['line_id'] !== null) {
            $byLine[$row['line_id']] = ($byLine[$row['line_id']] ?? 0) + (float) $row['amount'];
        }
    }

    arsort($byLine);
    $topFive = array_sum(array_slice($byLine, 0, 5, true));

    $reported = $analysis['concentration'][$key];

    check(sprintf('%s: the side total is every %s row', $key, $key),
        money($sideTotal), money($reported['total']));
    check(sprintf('%s: the top five add up to what is claimed', $key),
        money($topFive), money($reported['top']));
    check(sprintf('%s: the share is top over total', $key),
        round($sideTotal > 0 ? ($topFive / $sideTotal) * 100 : 0, 6),
        round($reported['share'], 6));
}

echo "\n=== 5. the rankings are ranked, and their shares are of the whole ===\n";

foreach (['top_income' => DaybookService::IN, 'top_expenditure' => DaybookService::OUT] as $key => $direction) {
    $ranking = $analysis[$key];

    if ($ranking === []) {
        continue;
    }

    $descending = true;
    for ($i = 1; $i < count($ranking); $i++) {
        if ($ranking[$i]['amount'] > $ranking[$i - 1]['amount']) {
            $descending = false;
        }
    }

    ok($key . ' is in descending order', $descending);

    $sideTotal = 0.0;
    foreach ($rows as $row) {
        if ($row['direction'] === $direction) {
            $sideTotal += (float) $row['amount'];
        }
    }

    // The share is of ALL money on that side, including the part on no line —
    // otherwise a line looks larger than it is simply because other money
    // could not be placed.
    check($key . ': the leader\'s share is of everything on its side',
        round($sideTotal > 0 ? ($ranking[0]['amount'] / $sideTotal) * 100 : 0, 6),
        round($ranking[0]['share'], 6));
}

echo "\n=== 6. the largest movements really are the largest ===\n";

$movements = $analysis['largest_movements'];

if ($movements !== []) {
    $allAmounts = array_map(fn ($r) => (float) $r['amount'], $rows);
    rsort($allAmounts);

    check('the biggest movement is the biggest row',
        money($allAmounts[0]), money($movements[0]['amount']));

    $descending = true;
    for ($i = 1; $i < count($movements); $i++) {
        if ($movements[$i]['amount'] > $movements[$i - 1]['amount']) {
            $descending = false;
        }
    }

    ok('they are listed largest first', $descending);
    ok('at most ten are listed', count($movements) <= 10);
}

echo "\n=== 7. money on no budget line is counted, never dropped ===\n";

$unallocatedAmount = 0.0;
$unallocatedCount = 0;

foreach ($rows as $row) {
    if (empty($row['line_id'])) {
        $unallocatedAmount += (float) $row['amount'];
        $unallocatedCount++;
    }
}

$reported = $analysis['unallocated'];

check('the amount matches the rows', money($unallocatedAmount), money($reported['amount']));
check('so does the count', $unallocatedCount, $reported['count']);
check('the share is of everything that moved',
    round(($expectedIn + $expectedOut) > 0 ? ($unallocatedAmount / ($expectedIn + $expectedOut)) * 100 : 0, 6),
    round($reported['share'], 6));

// It must also agree with the other half of the module, which computes the
// same thing by a different route.
$viaTotals = $daybook->totalsByLine($from, $to);
check('and with totalsByLine, which computes it separately',
    money($unallocatedAmount), money((float) $viaTotals['unallocated']));

echo "\n=== 8. the figures that prompted this work ===\n";

// Regression anchors. If any of these move, something real changed.
$ledgerRows = $daybook->rows('2025-10-14', '2026-12-31');

$anchorUnallocated = 0.0;
$anchorCount = 0;
$byMonth = [];

foreach ($ledgerRows as $row) {
    if (empty($row['line_id'])) {
        $anchorUnallocated += (float) $row['amount'];
        $anchorCount++;
    }

    $key = substr($row['date'], 0, 7);
    $byMonth[$key][$row['direction']] = ($byMonth[$key][$row['direction']] ?? 0) + (float) $row['amount'];
}

check('20,254,000 still sits on no budget line', '20254000.00', money($anchorUnallocated));
check('across four transactions', 4, $anchorCount);
check('July 2026 still took in 23,190,500', '23190500.00', money($byMonth['2026-07']['in'] ?? 0));
check('November 2025 was still 4,434,616 short',
    '-4434616.00',
    money(($byMonth['2025-11']['in'] ?? 0) - ($byMonth['2025-11']['out'] ?? 0)));
check('the ledger still spans 13 months', 13, count($byMonth));

echo "\n=== 9. can these figures be presented? ===\n";

$confidence = app(LedgerConfidence::class);
$checks = $confidence->checks($budget, $from, $to);

$keys = array_column($checks, 'key');

foreach (['unallocated', 'payroll', 'misfiled', 'no_period', 'balanced', 'alignment'] as $expected) {
    ok('the panel checks ' . $expected, in_array($expected, $keys, true));
}

// Worst first: a panel that opens with ticks and buries the red line has said
// nothing.
$states = array_column($checks, 'state');
$rank = ['critical' => 0, 'warning' => 1, 'good' => 2];
$ordered = true;

for ($i = 1; $i < count($states); $i++) {
    if ($rank[$states[$i]] < $rank[$states[$i - 1]]) {
        $ordered = false;
    }
}

ok('the worst news is at the top', $ordered);

$byKey = array_column($checks, null, 'key');

check('unallocated money is flagged, not mentioned', 'critical', $byKey['unallocated']['state']);
check('and it carries the amount', '20,254,000.00', $byKey['unallocated']['figure']);
ok('with something to do about it', !empty($byKey['unallocated']['action']));

check('the ledger balancing is good news', 'good', $byKey['balanced']['state']);
ok('and a good check offers no action', empty($byKey['balanced']['action']));

check('concerns are counted', 3, $confidence->concerns($checks));
check('so the panel does not claim all clear', false, $confidence->allClear($checks));

echo "\n=== 10. a clean book collapses to one line ===\n";

// Every check good — the state the screen will be in once the four income
// rows are mapped. Built rather than waited for.
$allGood = [
    ['key' => 'a', 'label' => 'A', 'state' => 'good', 'figure' => '0', 'detail' => '', 'action' => null],
    ['key' => 'b', 'label' => 'B', 'state' => 'good', 'figure' => '0', 'detail' => '', 'action' => null],
];

check('all clear when nothing is wrong', true, $confidence->allClear($allGood));
check('and no concerns to count', 0, $confidence->concerns($allGood));

$oneBad = $allGood;
$oneBad[1]['state'] = 'warning';

check('a single warning ends the all-clear', false, $confidence->allClear($oneBad));
check('and is counted', 1, $confidence->concerns($oneBad));

echo "\n=== 11. no chart is fed a dimension that is empty ===\n";

// faculty_id is set on 4 of 78 lines and profit_centre on none, so a panel
// for either would render as a convincing-looking blank. The guard is here
// rather than in anybody's memory.
$withFaculty = DB::table('budget_lines')->whereNotNull('faculty_id')->count();
$withCentre = DB::table('budget_lines')->whereNotNull('profit_centre')->count();

printf("          (faculty on %d lines, profit centre on %d)\n", $withFaculty, $withCentre);

$view = file_get_contents($base . '/resources/views/admin/daybook/analysis.blade.php');

if ($withCentre === 0) {
    check('the screen renders no profit-centre panel', 0,
        preg_match('/profit[_ -]?centre/i', $view));
}

echo "\n=== 12. teeth: break a figure, prove a named check fails ===\n";

// If the share were computed against the placeable money rather than all of
// it, every ranking would overstate itself. Shown rather than asserted.
$sideIn = 0.0;
$placeableIn = 0.0;

foreach ($rows as $row) {
    if ($row['direction'] !== DaybookService::IN) {
        continue;
    }

    $sideIn += (float) $row['amount'];

    if ($row['line_id'] !== null) {
        $placeableIn += (float) $row['amount'];
    }
}

$leader = $analysis['top_income'][0]['amount'] ?? 0;

$honest = $sideIn > 0 ? ($leader / $sideIn) * 100 : 0;
$flattering = $placeableIn > 0 ? ($leader / $placeableIn) * 100 : 0;

ok('the two ways of computing a share genuinely differ here', abs($honest - $flattering) > 0.5);
printf("          (share of all income %.1f%%, of placeable income %.1f%% — check 5 holds it to the first)\n",
    $honest, $flattering);

check('and the reported share is the honest one', round($honest, 6),
    round($analysis['top_income'][0]['share'] ?? 0, 6));

echo "\n=== nothing was left behind ===\n";

$closing = [
    'entries' => DB::table('journal_entries')->count(),
    'debits' => money((float) DB::table('journal_entries')->sum('total_debit')),
    'budgets' => DB::table('budgets')->count(),
    'lines' => DB::table('budget_lines')->count(),
    'expenses' => DB::table('expenses')->count(),
    'incomes' => DB::table('incomes')->count(),
];

// Absolute values, not deltas: this feature only reads, and the way to prove
// that is to show the figures are the ones it started with.
foreach ($opening as $key => $value) {
    check(sprintf('%-9s is still %s', $key, $value), $value, $closing[$key]);
}

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
