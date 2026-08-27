<?php
/**
 * The Daybook is the Income & Expenditure sheet, itemised.
 *
 * The Bursar keeps a columnar cash analysis book: every movement on one row,
 * its amount repeated under the analysis column it belongs to, and a monthly
 * summary that turns those columns into budget-versus-actual with the year to
 * date carried forward. Nothing is typed twice — every row already exists as a
 * fee, an income, an expense or a payroll run.
 *
 * The property this suite exists to defend: DaybookService itemises and
 * BudgetActualsService summarises the SAME money, so the book and the sheet
 * cannot disagree. If they ever do, one of them is lying and the test says
 * which line.
 *
 * Usage: php scripts/daybook_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Services\BudgetActualsService;
use App\Services\DaybookService;
use App\User;
use Illuminate\Support\Facades\Auth;
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

$daybook = app(DaybookService::class);
$actuals = app(BudgetActualsService::class);

$budget = Budget::where('is_institutional', true)->orderByDesc('id')->first();
if (!$budget) {
    fwrite(STDERR, "no institutional budget to work with\n");
    exit(2);
}

$from = optional($budget->start_date)->format('Y-m-d');
$to = optional($budget->end_date)->format('Y-m-d');

echo "\n== The book itemises real movements ==\n";

$rows = $daybook->rows($from, $to);
check('the book has rows', count($rows) > 0, count($rows) . ' rows');

$sources = array_unique(array_column($rows, 'source'));
sort($sources);
check(
    'all four sources are itemised',
    $sources === ['expense', 'fee', 'income', 'payroll'],
    implode(', ', $sources)
);

$badDirection = array_filter($rows, fn ($r) => !in_array($r['direction'], ['in', 'out'], true));
check('every row is either in or out', $badDirection === []);

$negative = array_filter($rows, fn ($r) => $r['amount'] < 0);
check('no row carries a negative amount', $negative === [], count($negative) . ' negative');

$dated = array_column($rows, 'date');
$sorted = $dated;
sort($sorted);
check('rows are in date order', $dated === $sorted);

$outOfRange = array_filter($rows, fn ($r) => $r['date'] < $from || $r['date'] > $to);
check('no row falls outside the period asked for', $outOfRange === [], count($outOfRange) . ' stray');

echo "\n== The book and the sheet are the same money ==\n";

$book = $daybook->totalsByLine($from, $to);
$sheet = $actuals->forPeriod($from, $to);

$ids = array_unique(array_merge(array_keys($book['lines']), array_keys($sheet['lines'])));
sort($ids);

$disagreements = [];
foreach ($ids as $id) {
    $a = round((float) ($book['lines'][$id] ?? 0), 2);
    $b = round((float) ($sheet['lines'][$id] ?? 0), 2);

    if (abs($a - $b) > 0.01) {
        $line = BudgetLine::find($id);
        $disagreements[] = ($line->code ?? $id) . ': book ' . number_format($a) . ' vs sheet ' . number_format($b);
    }
}

check(
    'every line agrees between book and sheet (' . count($ids) . ' lines)',
    $disagreements === [],
    implode(' | ', array_slice($disagreements, 0, 4))
);

// The sum of the rows must be the sum of the columns, or something was dropped
// between itemising and totalling.
$rowSum = array_sum(array_column($rows, 'amount'));
$lineSum = array_sum($book['lines']) + $book['unallocated'];
check(
    'the rows sum to the columns',
    abs($rowSum - $lineSum) < 0.01,
    number_format($rowSum) . ' vs ' . number_format($lineSum)
);

echo "\n== Payroll reaches the book ==\n";

// The whole reason the book could not be generated before: payroll never
// became an expense row, so all three original sources were blind to it.
$payrollRows = array_values(array_filter($rows, fn ($r) => $r['source'] === 'payroll'));
check('payroll appears as rows', count($payrollRows) > 0, count($payrollRows) . ' rows');
check(
    'and every payroll row is analysed',
    array_filter($payrollRows, fn ($r) => $r['line_id'] === null) === []
);

// An unposted payroll run is not a cost yet and must stay out, or the wage
// bill inflates by a run nobody has paid.
$unposted = DB::table('payrolls as p')
    ->leftJoin('journal_entries as je', function ($j) {
        $j->on('je.reference_id', '=', 'p.id')->where('je.reference_type', '=', 'payroll');
    })
    ->whereNull('je.id')
    ->sum('p.total_cost');

if ($unposted > 0) {
    $payrollTotal = array_sum(array_column($payrollRows, 'amount'));
    check(
        'an unposted payroll run stays out of the book (' . number_format($unposted) . ' excluded)',
        abs($payrollTotal - $unposted) > 0.01,
        'book payroll ' . number_format($payrollTotal)
    );
}

echo "\n== The Monthly Summary carries forward ==\n";

$periods = $daybook->periodsFor($budget);
check('the budget spans periods', $periods->isNotEmpty(), $periods->count() . ' periods');

$previousCumulative = null;
$carryBreaks = [];
$firstBrought = null;

foreach ($periods as $index => $period) {
    $summary = $daybook->monthlySummary($budget, $period);
    $t = $summary['totals']['expenditure'];

    if ($index === 0) {
        $firstBrought = $t['brought'];
    }

    if (abs(($t['monthly'] + $t['brought']) - $t['cumulative']) > 0.01) {
        $carryBreaks[] = $period->name . ' (monthly + brought != cumulative)';
    }

    if ($previousCumulative !== null && abs($t['brought'] - $previousCumulative) > 0.01) {
        $carryBreaks[] = $period->name . ' (brought ' . number_format($t['brought'])
            . ' != previous cumulative ' . number_format($previousCumulative) . ')';
    }

    $previousCumulative = $t['cumulative'];
}

check('cumulative = monthly + brought forward, every period', $carryBreaks === [], implode(' | ', array_slice($carryBreaks, 0, 3)));
check(
    'the first period of a budget opens at zero, not last year\'s total',
    abs((float) $firstBrought) < 0.01,
    'opened at ' . number_format((float) $firstBrought)
);

// The year-end cumulative must be the annual figure the sheet reports.
$lastSummary = $daybook->monthlySummary($budget, $periods->last());
foreach (['income', 'expenditure', 'capital'] as $section) {
    $sectionTotal = $actuals->sectionTotal($actuals->withHeaderTotals($sheet['lines']), $section);
    check(
        "the final cumulative equals the annual sheet ({$section})",
        abs($lastSummary['totals'][$section]['cumulative'] - $sectionTotal) < 0.01,
        number_format($lastSummary['totals'][$section]['cumulative']) . ' vs ' . number_format($sectionTotal)
    );
}

$balanced = true;
foreach ($lastSummary['lines'] as $row) {
    if (abs(($row['budget'] - $row['cumulative']) - $row['balance']) > 0.01) {
        $balanced = false;
        break;
    }
}
check('balance is budget minus cumulative on every line', $balanced);

echo "\n== The summary is grouped like the sheet ==\n";

$grouped = $daybook->monthlySummary($budget, $periods->last());

$headers = array_filter($grouped['lines'], fn ($r) => $r['is_header']);
$children = array_filter($grouped['lines'], fn ($r) => !$r['is_header']);

check('headings appear at all', $headers !== [], count($headers) . ' headings');
check('lines appear beneath them', $children !== [], count($children) . ' lines');
check(
    'every line of the sheet is present',
    count($grouped['lines']) === BudgetLine::sheet()->count(),
    count($grouped['lines']) . ' vs ' . BudgetLine::sheet()->count()
);

// A heading must equal the lines filed under it, in every column, or the
// grouping is decoration rather than arithmetic.
$wrongHeading = [];
foreach ($headers as $row) {
    $kids = array_filter($grouped['lines'], fn ($r) => $r['line']->parent_id === $row['line']->id);

    foreach (['budget', 'monthly', 'brought', 'cumulative'] as $column) {
        $sum = array_sum(array_column($kids, $column));
        if (abs($sum - $row[$column]) > 0.01) {
            $wrongHeading[] = $row['line']->code . ' ' . $column;
        }
    }
}
check('each heading equals the lines under it', $wrongHeading === [], implode(', ', array_slice($wrongHeading, 0, 4)));

// The classic way a grouped sheet starts lying.
$doubleCount = [];
foreach (['income', 'expenditure', 'capital'] as $section) {
    $fromLines = 0.0;
    foreach ($children as $row) {
        if ($row['line']->section === $section) {
            $fromLines += $row['cumulative'];
        }
    }

    if (abs($fromLines - $grouped['totals'][$section]['cumulative']) > 0.01) {
        $doubleCount[] = $section . ': lines ' . number_format($fromLines)
            . ' vs total ' . number_format($grouped['totals'][$section]['cumulative']);
    }
}
check('section totals exclude headings, so nothing is counted twice', $doubleCount === [], implode(' | ', $doubleCount));

check(
    'a subtotal is marked for every heading that has lines',
    count($grouped['group_ends']) === count(array_filter($headers, function ($row) use ($grouped) {
        return array_filter($grouped['lines'], fn ($r) => $r['line']->parent_id === $row['line']->id) !== [];
    })),
    count($grouped['group_ends']) . ' subtotals'
);

// And the screen must actually draw them. Auth and the kernel are set up here
// rather than borrowed from the section below, so this block does not depend on
// the order the file happens to run in.
$admin = User::whereHas('roles')->first() ?: User::first();
Auth::guard('web')->login($admin);
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/admin/daybook/summary', 'GET');
$request->setLaravelSession(app('session.store'));
$groupedHtml = $kernel->handle($request)->getContent();

check('the screen draws heading rows', substr_count($groupedHtml, 'class="ms-header"') === count($headers));
check('the screen draws a subtotal per group', substr_count($groupedHtml, 'class="ms-subtotal"') === count($grouped['group_ends']));
check('the screen indents the lines under a heading', strpos($groupedHtml, 'class="ms-child"') !== false);

echo "\n== Profit centres ==\n";

DB::beginTransaction();
try {
    $incomeLine = BudgetLine::where('section', 'income')->where('is_header', false)->first();
    $expenseLine = BudgetLine::where('section', 'expenditure')->where('is_header', false)->first();

    $incomeLine->profit_centre = 'ZZ Canteen';
    $incomeLine->save();
    $expenseLine->profit_centre = 'ZZ Canteen';
    $expenseLine->save();

    $summary = $daybook->monthlySummary($budget, $periods->last());
    $centres = $summary['profit_centres'];

    check('a paired activity appears', isset($centres['ZZ Canteen']));

    if (isset($centres['ZZ Canteen'])) {
        $c = $centres['ZZ Canteen'];
        check('it reports both sides', $c['income'] > 0 || $c['expenditure'] > 0);
        check('margin is earned minus cost', abs($c['margin'] - ($c['income'] - $c['expenditure'])) < 0.01);
    }

    // A profit centre re-presents lines that are already in a section. Adding
    // it to the totals would count that money twice.
    $totalsBefore = $lastSummary['totals'];
    check(
        'pairing does not change the section totals',
        abs($summary['totals']['income']['cumulative'] - $totalsBefore['income']['cumulative']) < 0.01
            && abs($summary['totals']['expenditure']['cumulative'] - $totalsBefore['expenditure']['cumulative']) < 0.01
    );

    // A single unpaired line is an activity with one side, not an error.
    $expenseLine->profit_centre = null;
    $expenseLine->save();
    $solo = $daybook->monthlySummary($budget, $periods->last())['profit_centres'];
    check('an unpaired line still reports, with a zero on the other side', isset($solo['ZZ Canteen']));
} finally {
    DB::rollBack();
}

check(
    'the test activity left nothing behind',
    BudgetLine::where('profit_centre', 'ZZ Canteen')->doesntExist()
);

echo "\n== The screens ==\n";

$admin = User::whereHas('roles')->first() ?: User::first();
Auth::guard('web')->login($admin);
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

foreach (['/admin/daybook' => 'the book', '/admin/daybook/summary' => 'the monthly summary'] as $uri => $label) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    check("$label renders", $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
}

// A quiet month is a fact, not a failure.
$empty = $periods->first(function (AccountingPeriod $p) use ($daybook) {
    return $daybook->rows(
        substr((string) $p->start_date, 0, 10),
        substr((string) $p->end_date, 0, 10)
    ) === [];
});

if ($empty) {
    $request = Illuminate\Http\Request::create('/admin/daybook', 'GET', ['period_id' => $empty->id]);
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    check(
        'a period with no movement renders an empty book (' . $empty->name . ')',
        $response->getStatusCode() === 200,
        'status ' . $response->getStatusCode()
    );
    check('and says so rather than showing a broken table', str_contains($response->getContent(), 'No cash moved'));
}

echo "\n== Trends and analysis ==\n";

$analysis = $daybook->analysis($budget);

check('a month is produced for every period', count($analysis['months']) === $periods->count());

// Each month's in/out must be the same money the book itemises for it.
$monthMismatch = [];
foreach ($analysis['months'] as $month) {
    $monthRows = $daybook->rows($month['start'], $month['end']);
    $in = array_sum(array_map(fn ($r) => $r['direction'] === DaybookService::IN ? $r['amount'] : 0, $monthRows));
    $out = array_sum(array_map(fn ($r) => $r['direction'] === DaybookService::OUT ? $r['amount'] : 0, $monthRows));

    if (abs($in - $month['in']) > 0.01 || abs($out - $month['out']) > 0.01) {
        $monthMismatch[] = $month['label'];
    }
}
check('every month agrees with the book for that month', $monthMismatch === [], implode(', ', $monthMismatch));

// The running totals are what the pacing curve is drawn from.
$runIn = 0.0;
$runOut = 0.0;
$cumBroken = [];
foreach ($analysis['months'] as $month) {
    $runIn += $month['in'];
    $runOut += $month['out'];
    if (abs($month['cum_in'] - $runIn) > 0.01 || abs($month['cum_out'] - $runOut) > 0.01) {
        $cumBroken[] = $month['label'];
    }
}
check('the cumulative curve accumulates correctly', $cumBroken === [], implode(', ', $cumBroken));

$lastMonth = end($analysis['months']);
check(
    'the final cumulative equals the year total',
    abs($lastMonth['cum_out'] - $analysis['totals']['out']) < 0.01
);

$top = $analysis['top_expenditure'];
check('the largest lines are ranked, largest first', $top === [] || $top[0]['amount'] >= end($top)['amount']);
check(
    'no ranked line exceeds total expenditure',
    $top === [] || $top[0]['amount'] <= $analysis['totals']['out'] + 0.01
);

// A tool that shouts "overspent" at a budget nobody filled in gets ignored, so
// the two cases must be told apart.
$pacing = $analysis['pacing'];
check(
    'pacing reports a status',
    in_array($pacing['status'], ['none', 'incomplete', 'good', 'warning', 'critical'], true),
    $pacing['status']
);
check(
    'with no budget set, pacing says so rather than showing a percentage',
    $pacing['budgeted'] > 0 || $pacing['status'] === 'none'
);
check('elapsed is a real proportion of the year', $pacing['elapsed'] >= 0 && $pacing['elapsed'] <= 100);

// Verify the judgement on a budget that actually has figures.
//
// Pacing measures SPENDING, so the budget has to carry expenditure or capital
// allocations. A budget whose only figures sit on income lines has nothing to
// pace against and correctly reports "none" — picking one of those would be
// testing the wrong thing.
$budgetedOne = Budget::where('is_institutional', true)
    ->whereIn('id', function ($q) {
        $q->select('a.budget_id')
            ->from('budget_allocations as a')
            ->join('budget_lines as bl', 'bl.id', '=', 'a.budget_line_id')
            ->whereIn('bl.section', ['expenditure', 'capital'])
            ->where('a.allocated_amount', '>', 0);
    })
    ->orderByDesc('id')
    ->first();

if ($budgetedOne) {
    $other = $daybook->analysis($budgetedOne)['pacing'];
    check(
        'a budget with figures is paced, not dismissed',
        $other['status'] !== 'none',
        'status ' . $other['status'] . ', budgeted ' . number_format($other['budgeted'])
    );
    check(
        'spending far beyond the budget reads as unfinished, not as an overspend',
        $other['consumed'] <= 150 || $other['status'] === 'incomplete',
        number_format($other['consumed'], 0) . '% consumed, status ' . $other['status']
    );
}

$request = Illuminate\Http\Request::create('/admin/daybook/analysis', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);
$analysisHtml = $response->getContent();

check('the analysis screen renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
check('it draws its charts', substr_count($analysisHtml, 'new Chart(') === 2);

// These schools run on intermittent connections; a chart that needs the
// internet is a chart that is sometimes simply blank.
check(
    'charting is served locally, not from a CDN',
    strpos($analysisHtml, 'plugins/chart-chartjs/js/chart.min.js') !== false
        && strpos($analysisHtml, 'cdn.jsdelivr') === false
);

echo "\n== Every screen explains itself ==\n";

foreach ([
    '/admin/daybook' => ['What the Daybook is', 'How it relates to the sheet'],
    '/admin/daybook/summary' => ['What each column means', 'BB Forward'],
    '/admin/daybook/analysis' => ['What this page is for', 'How to read it'],
] as $uri => $phrases) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));
    $html = $kernel->handle($request)->getContent();

    foreach ($phrases as $phrase) {
        check($uri . ' explains "' . $phrase . '"', stripos($html, $phrase) !== false);
    }
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
