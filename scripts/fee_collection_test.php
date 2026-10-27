<?php

/**
 * Who has paid — the student collection figures on the daybook analysis page.
 *
 * Every number here is read out in a meeting, so every number is computed a
 * SECOND time in this file, from raw SQL, and compared against what
 * FeeCollection returns. Calling the service twice would only prove it is
 * deterministic; recomputing proves it is right.
 *
 * Fixtures are written inside a transaction that is always rolled back, and
 * the suite closes by proving the fees table is in exactly the state it
 * started in.
 *
 * Neighbouring baselines this must not move:
 *   daybook_analysis_test   58/0
 *   daybook_test            54/2  <- PRE-EXISTING. Both failures are one
 *                                    cause: no posted payroll run falls in
 *                                    the window that suite uses. Unrelated to
 *                                    this module; it must still read 54/2.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Budget;
use App\Models\Fee;
use App\Services\DaybookService;
use App\Services\FeeCollection;
use App\User;
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

/** Money compares to the penny; floats do not compare at all. */
function money($v): string
{
    return number_format((float) $v, 2, '.', '');
}

$svc = app(FeeCollection::class);
$daybook = app(DaybookService::class);

$budgets = Budget::where('is_institutional', true)->orderBy('id')->get();

ok('there is at least one institutional budget to analyse', $budgets->isNotEmpty());

/* The state the suite must leave behind untouched. */
$opening = [
    'fees' => DB::table('fees')->count(),
    'billed' => money(DB::table('fees')->sum(DB::raw('fee_amount - COALESCE(discount_amount,0) + COALESCE(fine_amount,0)'))),
    'paid' => money(DB::table('fees')->sum('paid_amount')),
    'credits' => DB::table('student_credits')->count(),
    'applications' => DB::table('credit_applications')->count(),
];

/* ===================================================================
 |  The scope, resolved independently
 |
 |  The service matches 'instal+ment' against the catalogue. This suite
 |  resolves the same set by reading every category title and testing it in
 |  PHP, so a change to the service's regex has to be matched here
 |  deliberately rather than agreeing with itself by construction.
 |===================================================================*/

$catalogue = DB::table('fees_categories')->orderBy('id')->get(['id', 'title']);

$instalmentIds = [];
$otherIds = [];

foreach ($catalogue as $row) {
    $title = strtolower((string) $row->title);
    $isInstalment = str_contains($title, 'instalment') || str_contains($title, 'installment');
    if ($isInstalment) {
        $instalmentIds[] = (int) $row->id;
    } else {
        $otherIds[] = (int) $row->id;
    }
}

/* One placeholder list, reused by every raw expectation below, so the bound
   parameters and the scope can never drift apart. */
$scopeSql = implode(',', array_fill(0, count($instalmentIds), '?'));

ok('the catalogue has instalment categories to scope to', count($instalmentIds) > 0);
ok('and other categories that must be left out', count($otherIds) > 0);

check('the service scopes to exactly those categories',
    $instalmentIds,
    array_map('intval', $svc->instalmentScope()['ids']));

ok('and reports the match as found, not as the fall-back to everything',
    $svc->instalmentScope()['matched'] === true);

/* The fall-back is the one branch a wrong regex would silently take, and the
   consequence is the worst possible: "0 of 0 students", stated confidently.
   So it is asserted to open the scope rather than close it. */
ok('an unmatched catalogue would widen the scope to every fee, never to none',
    count($svc->instalmentScope()['ids']) > 0);

/* ===================================================================
 |  The independent recomputation
 |
 |  Deliberately a different shape of query from the service's: this one
 |  pulls every fee row and buckets them in PHP, where the service groups in
 |  SQL. Two routes to the same figure is the point.
 |===================================================================*/

/**
 * @return array{student: array, applicant: array, people: array}
 */
function recompute(string $from, string $to, array $categoryIds): array
{
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

    $rows = DB::select("
        SELECT f.id,
               se.student_id,
               f.applicant_id,
               f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0) AS due,
               f.paid_amount - (
                   SELECT COALESCE(SUM(ca.amount_applied), 0)
                     FROM credit_applications ca
                     JOIN student_credits sc ON sc.id = ca.student_credit_id
                    WHERE sc.source_fee_id = f.id
                      AND sc.source_type = 'overpayment'
               ) AS paid
          FROM fees f
          LEFT JOIN student_enrolls se ON se.id = f.student_enroll_id
         WHERE f.status <> 3
           AND f.category_id IN ({$placeholders})
           AND f.assign_date >= ?
           AND f.assign_date <= ?
    ", array_merge($categoryIds, [$from, $to]));

    $people = [];

    foreach ($rows as $r) {
        // The identity rule, written out longhand so a change to the
        // service's CASE expression has to be matched here deliberately.
        if ($r->student_id !== null) {
            $key = 's' . $r->student_id;
            $kind = 'student';
        } elseif ($r->applicant_id !== null) {
            $key = 'a' . $r->applicant_id;
            $kind = 'applicant';
        } else {
            continue;   // belongs to nobody; counted separately
        }

        if (!isset($people[$key])) {
            $people[$key] = ['kind' => $kind, 'due' => 0.0, 'paid' => 0.0];
        }

        $people[$key]['due'] += (float) $r->due;
        $people[$key]['paid'] += (float) $r->paid;
    }

    $empty = [
        'full' => 0, 'part' => 0, 'none' => 0, 'total' => 0,
        'billed' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0,
        'overpaid_people' => 0, 'overpaid_amount' => 0.0,
    ];

    $out = ['student' => $empty, 'applicant' => $empty];

    foreach ($people as $p) {
        $k = $p['kind'];
        $due = $p['due'];
        $paid = $p['paid'];

        if ($paid <= 0.005) {
            $bucket = $due <= 0.005 ? 'full' : 'none';
        } else {
            $bucket = $due - $paid <= 0.005 ? 'full' : 'part';
        }

        $out[$k][$bucket]++;
        $out[$k]['total']++;
        $out[$k]['billed'] += $due;
        $out[$k]['collected'] += $paid;
        $out[$k]['outstanding'] += max(0.0, $due - $paid);

        if ($paid - $due > 0.005) {
            $out[$k]['overpaid_people']++;
            $out[$k]['overpaid_amount'] += $paid - $due;
        }
    }

    return $out + ['people' => $people];
}

/* ===================================================================
 |  1. The three figures, recomputed
 |===================================================================*/

echo "\n== The three figures are what the fees say ==\n";

foreach ($budgets as $budget) {
    $from = substr((string) $budget->start_date, 0, 10);
    $to = substr((string) $budget->end_date, 0, 10);

    $c = $svc->forBudget($budget);
    $mine = recompute($from, $to, $instalmentIds);

    foreach (['student' => 'students', 'applicant' => 'applicants'] as $kind => $key) {
        foreach (['full', 'part', 'none', 'total'] as $bucket) {
            check(
                "{$budget->title}: {$key} {$bucket}",
                $mine[$kind][$bucket],
                $c[$key][$bucket]
            );
        }

        foreach (['billed', 'collected', 'outstanding', 'overpaid_amount'] as $figure) {
            check(
                "{$budget->title}: {$key} {$figure}",
                money($mine[$kind][$figure]),
                money($c[$key][$figure])
            );
        }
    }

    /* The buckets must PARTITION the cohort: everybody in exactly one. */
    foreach (['students', 'applicants', 'combined'] as $key) {
        check(
            "{$budget->title}: {$key} — the three buckets account for everybody",
            $c[$key]['total'],
            $c[$key]['full'] + $c[$key]['part'] + $c[$key]['none']
        );
    }

    /* Combined is the two cohorts and nothing else. */
    foreach (['full', 'part', 'none', 'total'] as $bucket) {
        check(
            "{$budget->title}: combined {$bucket} is students plus applicants",
            $c['students'][$bucket] + $c['applicants'][$bucket],
            $c['combined'][$bucket]
        );
    }

    /* The money invariant. Outstanding sums only the positive balances, so it
       equals billed minus collected ONLY once overpayment is added back —
       which is the whole reason overpayment is reported rather than netted. */
    foreach (['students', 'applicants', 'combined'] as $key) {
        check(
            "{$budget->title}: {$key} — billed less collected is outstanding, net of credit held",
            money($c[$key]['billed'] - $c[$key]['collected']),
            money($c[$key]['outstanding'] - $c[$key]['overpaid_amount'])
        );
    }

    /* A rate is a percentage and cannot exceed one. */
    foreach (['students', 'applicants', 'combined'] as $key) {
        ok("{$budget->title}: {$key} rate is between 0 and 100",
            $c[$key]['rate'] >= 0 && $c[$key]['rate'] <= 100);
    }
}

/* ===================================================================
 |  2. Students are not pooled with applicants
 |
 |  The reason this matters is measurable: pooling inverts the answer on the
 |  current year, where applicants outnumber students and have nearly all
 |  settled a small admission fee.
 |===================================================================*/

echo "\n== Students and applicants are counted apart ==\n";

$current = $budgets->last();
$c = $svc->forBudget($current);

ok('the student cohort excludes every applicant',
    $c['students']['total'] + $c['applicants']['total'] === $c['combined']['total']);

if ($c['applicants']['total'] > 0 && $c['students']['total'] > 0) {
    // Not an assertion about the live figures, which move. An assertion that
    // the two cohorts are genuinely separate populations.
    $pooledRate = $c['combined']['rate'];
    $studentRate = $c['students']['rate'];

    ok('the pooled rate differs from the student rate, which is why they are split',
        abs($pooledRate - $studentRate) > 0.0);

    printf("          (students %s%%, applicants %s%%, pooled %s%% — pooling would report the last)\n",
        number_format($c['students']['rate'], 1),
        number_format($c['applicants']['rate'], 1),
        number_format($pooledRate, 1));
}

$applicantIds = DB::table('fees as f')
    ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->whereNull('se.student_id')
    ->whereNotNull('f.applicant_id')
    ->where('f.status', '<>', 3)
    ->distinct()
    ->pluck('f.applicant_id');

ok('there are applicant-only fees on this install, so the split is exercised',
    $applicantIds->isNotEmpty());

/* ===================================================================
 |  3. Nobody is lost to a join
 |===================================================================*/

echo "\n== Fees that belong to nobody are shown, not dropped ==\n";

$orphans = DB::select("
    SELECT f.id,
           f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0) AS due,
           f.paid_amount,
           CASE WHEN f.student_enroll_id IS NOT NULL AND se.id IS NULL
                THEN 'missing_enrolment' ELSE 'no_person' END AS reason
      FROM fees f
      LEFT JOIN student_enrolls se ON se.id = f.student_enroll_id
     WHERE f.status <> 3
       AND COALESCE(se.student_id, f.applicant_id) IS NULL
");

check('every unattributable fee is reported', count($orphans), $c['unattributed']['count']);

check('their billed total is reported',
    money(array_sum(array_map(fn ($o) => (float) $o->due, $orphans))),
    money($c['unattributed']['amount']));

check('their outstanding total is reported',
    money(array_sum(array_map(fn ($o) => max(0.0, (float) $o->due - (float) $o->paid_amount), $orphans))),
    money($c['unattributed']['owed']));

ok('this install actually has one, so the case is exercised rather than assumed',
    count($orphans) > 0);

/* A fee with a dangling enrolment is named as such, not lumped in with one
   whose enrolment merely lacks a student. The two need different fixes. */
$dangling = array_values(array_filter($orphans, fn ($o) => $o->reason === 'missing_enrolment'));

if ($dangling) {
    $reported = collect($c['unattributed']['rows'])->firstWhere('id', (int) $dangling[0]->id);

    ok('a fee whose enrolment no longer exists is reported by that reason',
        $reported !== null && $reported['reason'] === 'missing_enrolment');
}

/* Teeth: an inner join is the mistake this guards against. Prove the figure
   would move, and by exactly how much. */
$innerBilled = (float) DB::table('fees as f')
    ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->whereRaw('COALESCE(se.student_id, f.applicant_id) IS NULL')
    ->where('f.status', '<>', 3)
    ->sum(DB::raw('f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0)'));

ok('an inner join would hide money (teeth: the LEFT join is load-bearing)',
    $innerBilled < $c['unattributed']['amount'] - 0.005);

printf("          (an inner join would see %s of %s)\n",
    number_format($innerBilled), number_format($c['unattributed']['amount']));

/* ===================================================================
 |  4. The monthly curve ties to the daybook
 |
 |  The one invariant that keeps two charts on one page from disagreeing.
 |===================================================================*/

echo "\n== The collection curve is the same money as the book ==\n";

foreach ($budgets as $budget) {
    $from = substr((string) $budget->start_date, 0, 10);
    $to = substr((string) $budget->end_date, 0, 10);

    $feeCash = 0.0;
    foreach ($daybook->rows($from, $to) as $row) {
        if ($row['source'] === 'fee') {
            $feeCash += (float) $row['amount'];
        }
    }

    $months = $svc->forBudget($budget)['by_month'];
    $curve = array_sum(array_column($months, 'cash'));
    $allCurve = array_sum(array_column($months, 'all_cash'));

    /* The tie is on the ALL-FEE series, and that is why the series exists.
       The chart is about instalments, but a page carrying two pictures of
       fee cash that disagree is a page nobody trusts, so the table beside
       the chart carries the figure that reconciles to the daybook and this
       asserts it to the franc. */
    check("{$budget->title}: all fee cash equals the daybook's fee rows",
        money($feeCash), money($allCurve));

    /* And the instalment series is the same money, narrowed: recomputed here
       from its own query rather than taken from the service, so a scope
       applied to the cohort but forgotten on the curve would fail. */
    $instalmentCash = 0.0;
    foreach (DB::select("
        SELECT COALESCE(SUM(" . Fee::cashReceivedSql('f') . "), 0) AS cash
          FROM fees f
         WHERE f.status <> 3
           AND f.paid_amount > 0
           AND f.pay_date IS NOT NULL
           AND f.pay_date >= ?
           AND f.pay_date <= ?
           AND f.category_id IN ({$scopeSql})
    ", array_merge([$from, $to], $instalmentIds)) as $row) {
        $instalmentCash = (float) $row->cash;
    }

    check("{$budget->title}: the instalment curve is the instalment cash",
        money($instalmentCash), money($curve));

    ok("{$budget->title}: and never exceeds all fee cash",
        $curve <= $allCurve + 0.005);
}

/* The running total is the series, accumulated — not a second query. */
$months = $svc->forBudget($current)['by_month'];
$running = 0.0;
$cumulativeOk = true;

foreach ($months as $m) {
    $running += $m['cash'];

    if (money($running) !== money($m['cumulative'])) {
        $cumulativeOk = false;
    }
}

ok('the running total is the months accumulated', $cumulativeOk);
ok('every month of the budget is in the series, including the empty ones',
    count($months) >= 12);

$labels = array_column($months, 'label');
check('no two months share a label, so the axis cannot be misread',
    count($labels), count(array_unique($labels)));

/* ===================================================================
 |  5. Arrears
 |===================================================================*/

echo "\n== Money owed from earlier years ==\n";

foreach ($budgets as $budget) {
    $from = substr((string) $budget->start_date, 0, 10);
    $c2 = $svc->forBudget($budget);

    /* Arrears carry the same scope as the cohort, and must: an unscoped
       arrears figure beside a scoped headline is how a page shows a student
       as fully paid on one line and in debt on the next.

       An invariant, not a snapshot. Pinning "11 people, 862,666.67" is how
       ledger_health_test broke the first time somebody took a payment: the
       figure was right and the assertion was still wrong. What cannot change
       is that every franc of arrears is on a fee assigned before the window
       and still carrying a balance. */
    $expected = DB::select("
        SELECT COUNT(*) AS people, COALESCE(SUM(owed), 0) AS amount
          FROM (
            SELECT SUM(f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0))
                 - SUM(f.paid_amount - (
                       SELECT COALESCE(SUM(ca.amount_applied), 0)
                         FROM credit_applications ca
                         JOIN student_credits sc ON sc.id = ca.student_credit_id
                        WHERE sc.source_fee_id = f.id AND sc.source_type = 'overpayment'
                   )) AS owed
              FROM fees f
              LEFT JOIN student_enrolls se ON se.id = f.student_enroll_id
             WHERE f.status <> 3
               AND f.assign_date < ?
               AND f.category_id IN ({$scopeSql})
               AND COALESCE(se.student_id, f.applicant_id) IS NOT NULL
             GROUP BY CASE WHEN se.student_id IS NOT NULL
                           THEN CONCAT('s', se.student_id)
                           ELSE CONCAT('a', f.applicant_id) END
            HAVING owed > 0.005
          ) t
    ", array_merge([$from], $instalmentIds))[0];

    check("{$budget->title}: arrears people", (int) $expected->people, $c2['arrears']['people']);
    check("{$budget->title}: arrears amount", money($expected->amount), money($c2['arrears']['amount']));

    if ($c2['arrears']['people'] > 0) {
        ok("{$budget->title}: the oldest arrear predates the budget year",
            $c2['arrears']['oldest'] < $from);
    }
}

/* The earliest budget can have no arrears: there is nothing before it. */
$first = $svc->forBudget($budgets->first());
check('the earliest budget year has no arrears behind it', 0, $first['arrears']['people']);

/* ===================================================================
 |  6. Breakdowns
 |===================================================================*/

echo "\n== The breakdowns agree with the whole ==\n";

$c = $svc->forBudget($current);

/* The two breakdowns answer to two different wholes, and that is the point of
   the scope rather than an inconsistency:

     - by_category is UNSCOPED, so it must account for every fee in the window.
       It is the page's record of what the counts leave out, and a row missing
       from it is money that has disappeared from the screen entirely.
     - by_programme is SCOPED, so it must account for the instalments only.

   Asserting both against their own whole is what would catch the scope being
   applied to one and forgotten on the other. */
$billedIn = function (?array $categoryIds) use ($c) {
    $q = DB::table('fees')
        ->where('status', '<>', 3)
        ->whereBetween('assign_date', [$c['window']['from'], $c['window']['to']]);

    if ($categoryIds !== null) {
        $q->whereIn('category_id', $categoryIds);
    }

    return (float) $q->sum(DB::raw('fee_amount - COALESCE(discount_amount,0) + COALESCE(fine_amount,0)'));
};

$countIn = function (?array $categoryIds) use ($c) {
    $q = DB::table('fees')
        ->where('status', '<>', 3)
        ->whereBetween('assign_date', [$c['window']['from'], $c['window']['to']]);

    if ($categoryIds !== null) {
        $q->whereIn('category_id', $categoryIds);
    }

    return $q->count();
};

check('the fee categories account for every fee billed in the window',
    money($billedIn(null)),
    money(array_sum(array_column($c['by_category'], 'due'))));

check('the programmes account for every INSTALMENT billed in the window',
    money($billedIn($instalmentIds)),
    money(array_sum(array_column($c['by_programme'], 'due'))));

check('and every fee is counted exactly once by category',
    $countIn(null), array_sum(array_column($c['by_category'], 'fees')));

check('and every instalment exactly once by programme',
    $countIn($instalmentIds), array_sum(array_column($c['by_programme'], 'fees')));

/* The scope is reported as well as applied: the page states what it left out,
   and a figure the page states is a figure that has to be right. */
check('the excluded total is everything the scope dropped',
    money($billedIn(null) - $billedIn($instalmentIds)),
    money($c['scope']['excluded']['billed']));

check('and the excluded fee count agrees',
    $countIn(null) - $countIn($instalmentIds),
    $c['scope']['excluded']['fees']);

/* Every category appears, each marked, and only the instalments are marked.
   Without this a future scope change could mark the wrong rows and the table
   would still add up. */
$marked = [];
$unmarked = [];

foreach ($c['by_category'] as $row) {
    if ($row['in_scope']) {
        $marked[] = $row['category'];
    } else {
        $unmarked[] = $row['category'];
    }
}

ok('the category table marks the counted rows', count($marked) > 0);
ok('and leaves the rest unmarked rather than hiding them', count($unmarked) > 0);

$markedAreInstalments = true;
foreach ($marked as $title) {
    $lower = strtolower($title);
    if (!str_contains($lower, 'instalment') && !str_contains($lower, 'installment')) {
        $markedAreInstalments = false;
    }
}
ok('and nothing but an instalment is ever marked as counted', $markedAreInstalments);

/* Counted rows lead, so the reader meets the figures that feed the headline
   before the ones that do not. */
$seenUnmarked = false;
$orderOk = true;

foreach ($c['by_category'] as $row) {
    if (!$row['in_scope']) {
        $seenUnmarked = true;
    } elseif ($seenUnmarked) {
        $orderOk = false;
    }
}
ok('the counted categories are listed before the uncounted ones', $orderOk);

/* Worst first, so the row that needs chasing is the row you read. */
$rates = array_column($c['by_programme'], 'rate');
$sorted = $rates;
sort($sorted);
check('programmes are ranked worst collection rate first', $sorted, $rates);

/* Categories are worst-first WITHIN each block, counted ones first — not
   worst-first overall, which is what this would have asserted before the
   scope existed. */
$catRates = [];
$catSorted = [];

foreach ([true, false] as $inScope) {
    $block = [];
    foreach ($c['by_category'] as $row) {
        if ($row['in_scope'] === $inScope) {
            $block[] = $row['rate'];
        }
    }
    $catRates = array_merge($catRates, $block);
    sort($block);
    $catSorted = array_merge($catSorted, $block);
}

check('so are fee categories, within the counted and uncounted blocks',
    $catSorted, $catRates);

/* A programme row's people count must never be zero while it carries fees —
   the bug that COUNT(DISTINCT student_id) produced on applicant fees. */
$zeroPeople = array_values(array_filter(
    $c['by_programme'],
    fn ($r) => $r['people'] === 0 && $r['fees'] > 0 && $r['programme'] !== __('No programme recorded')
));

check('no programme shows fees with nobody behind them', [], $zeroPeople);

/* Debtors: everybody listed owes something, ordered by what they owe. */
$owed = array_column($c['top_debtors'], 'owed');
$owedSorted = $owed;
rsort($owedSorted);
check('the largest balances are listed largest first', $owedSorted, $owed);
ok('nobody who owes nothing appears among the debtors',
    count(array_filter($owed, fn ($v) => $v <= 0.005)) === 0);

$debtorCount = DB::table('fees as f')
    ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->whereRaw('COALESCE(se.student_id, f.applicant_id) IS NOT NULL')
    ->where('f.status', '<>', 3)
    ->whereIn('f.category_id', $instalmentIds)
    ->whereBetween('f.assign_date', [$c['window']['from'], $c['window']['to']])
    ->groupByRaw("CASE WHEN se.student_id IS NOT NULL THEN CONCAT('s', se.student_id)
                       ELSE CONCAT('a', f.applicant_id) END")
    ->havingRaw('SUM(f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0))
               - SUM(' . Fee::netPaidSql('f') . ') > 0.005')
    ->selectRaw('1')
    ->get()
    ->count();

check('the debtor list holds everybody who owes, up to its limit',
    min(15, $debtorCount), count($c['top_debtors']));

/* ===================================================================
 |  7. Fixtures: the cases live data does not cover
 |===================================================================*/

echo "\n== Credit, cancellation and the edges ==\n";

$enroll = DB::table('student_enrolls')->whereNotNull('student_id')->first();

/* The fixtures must sit INSIDE the scope, or every assertion below passes for
   the wrong reason: a fee in a category the section does not count moves no
   figure, and "the service did not change" would then be a vacuous pass on
   credit handling, cancellation and overpayment alike. The id is taken from
   the suite's own resolution of the catalogue, not from the service. */
$category = DB::table('fees_categories')->whereIn('id', $instalmentIds)->first();

/* And the opposite fixture needs a category OUTSIDE the scope, to prove the
   scope is doing anything at all. */
$outsideCategory = DB::table('fees_categories')->whereIn('id', $otherIds)->first();

ok('there is an enrolment and an instalment category to build fixtures on',
    $enroll !== null && $category !== null);
ok('and a non-instalment category to test the scope against',
    $outsideCategory !== null);

$windowFrom = $c['window']['from'];
$windowTo = $c['window']['to'];
$assign = $windowFrom;

DB::beginTransaction();

try {
    /* --- a person settled entirely by credit moved from another fee -------
       The case raw paid_amount gets wrong in both directions: the source fee
       looks overpaid and the destination looks unpaid. */
    $sourceId = DB::table('fees')->insertGetId([
        'student_enroll_id' => $enroll->id, 'category_id' => $category->id,
        'fee_amount' => 100000, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 150000, 'assign_date' => $assign, 'due_date' => $assign,
        'pay_date' => $assign, 'status' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $destId = DB::table('fees')->insertGetId([
        'student_enroll_id' => $enroll->id, 'category_id' => $category->id,
        'fee_amount' => 50000, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 50000, 'assign_date' => $assign, 'due_date' => $assign,
        'pay_date' => $assign, 'status' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $creditId = DB::table('student_credits')->insertGetId([
        'student_id' => $enroll->student_id, 'original_amount' => 50000,
        'remaining_amount' => 0, 'source_fee_id' => $sourceId,
        'source_type' => 'overpayment', 'status' => 'fully_applied',
        'refund_requested' => 0, 'refund_approved' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('credit_applications')->insert([
        'student_credit_id' => $creditId, 'fee_id' => $destId,
        'amount_applied' => 50000, 'application_type' => 'manual',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $withCredit = $svc->forBudget($current);
    $mine = recompute($windowFrom, $windowTo, $instalmentIds);
    $key = 's' . $enroll->student_id;

    /* 100,000 + 50,000 billed; 150,000 received, of which 50,000 was moved to
       the destination fee — so net 100,000 on the source and 50,000 on the
       destination. The person is settled, and holds no credit. */
    check('a person settled partly by moved credit is fully paid, not overpaid',
        money(150000), money($mine['people'][$key]['paid'] ?? -1));

    ok('and the service agrees the credit is not counted twice',
        money($withCredit['students']['collected']) === money($mine['student']['collected']));

    /* Teeth: raw paid_amount would double-count the moved 50,000. */
    $raw = (float) DB::table('fees')
        ->whereIn('id', [$sourceId, $destId])
        ->sum('paid_amount');

    $net = (float) DB::table('fees')
        ->whereIn('id', [$sourceId, $destId])
        ->sum(DB::raw(Fee::netPaidSql('fees')));

    check('teeth: raw paid_amount overstates this pair by the moved credit',
        money(50000), money($raw - $net));

    /* --- a fee outside the scope must move no count ----------------------
       The direct test of what the scope is for. A resit fee is real money
       and belongs on the page, but it is not what "has this student paid
       their fees" asks, and before the scope existed it was enough to lift a
       student out of "paid nothing at all" — 9 students on the 2026/2027
       window read as 3 for exactly this reason. */
    $beforeScope = $svc->forBudget($current);

    $outsideId = DB::table('fees')->insertGetId([
        'student_enroll_id' => $enroll->id,
        // The non-instalment category, resolved by this suite from the
        // catalogue, never from FeeCollection — a teeth check that widens
        // the service's regex must not also move this fixture.
        'category_id' => $outsideCategory->id,
        'fee_amount' => 777000, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 0, 'assign_date' => $assign, 'due_date' => $assign,
        'status' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $afterScope = $svc->forBudget($current);

    check('a fee outside the scope changes nothing billed',
        money($beforeScope['students']['billed']), money($afterScope['students']['billed']));
    check('nor anything outstanding',
        money($beforeScope['students']['outstanding']), money($afterScope['students']['outstanding']));
    check('nor who has paid in full',
        $beforeScope['students']['full'], $afterScope['students']['full']);
    check('nor who has paid nothing at all',
        $beforeScope['students']['none'], $afterScope['students']['none']);
    check('nor the collection rate',
        $beforeScope['students']['rate'], $afterScope['students']['rate']);

    /* But it is NOT invisible. Dropping it from the counts and dropping it
       from the page are different things, and only the first was asked for. */
    check('but the page still reports it among what was left out',
        money($beforeScope['scope']['excluded']['billed'] + 777000),
        money($afterScope['scope']['excluded']['billed']));

    $outsideShown = false;
    foreach ($afterScope['by_category'] as $row) {
        if ($row['category'] === $outsideCategory->title) {
            $outsideShown = !$row['in_scope'];
        }
    }
    ok('and lists its category, marked as not counted', $outsideShown);

    DB::table('fees')->where('id', $outsideId)->delete();

    /* --- a student who has paid ONLY a non-instalment fee ----------------
       The case the scope exists to get right, written as a whole person
       rather than as an amount: somebody billed an instalment they have not
       touched, who has settled a resit, must read as having paid nothing. */
    $resitOnly = DB::table('student_enrolls')
        ->whereNotNull('student_id')
        ->where('student_id', '<>', $enroll->student_id)
        ->whereNotExists(function ($q) use ($windowFrom, $windowTo) {
            $q->select(DB::raw(1))->from('fees')
              ->whereColumn('fees.student_enroll_id', 'student_enrolls.id')
              ->whereBetween('fees.assign_date', [$windowFrom, $windowTo]);
        })
        ->first();

    if ($resitOnly !== null) {
        $unpaidInstalment = DB::table('fees')->insertGetId([
            'student_enroll_id' => $resitOnly->id, 'category_id' => $category->id,
            'fee_amount' => 500000, 'fine_amount' => 0, 'discount_amount' => 0,
            'paid_amount' => 0, 'assign_date' => $assign, 'due_date' => $assign,
            'status' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $paidResit = DB::table('fees')->insertGetId([
            'student_enroll_id' => $resitOnly->id, 'category_id' => $outsideCategory->id,
            'fee_amount' => 5000, 'fine_amount' => 0, 'discount_amount' => 0,
            'paid_amount' => 5000, 'assign_date' => $assign, 'due_date' => $assign,
            'pay_date' => $assign, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resitView = $svc->forBudget($current);

        check('a student who has settled only a resit has paid NOTHING at all',
            $beforeScope['students']['none'] + 1, $resitView['students']['none']);
        check('and is not counted as partly paid',
            $beforeScope['students']['part'], $resitView['students']['part']);

        /* Teeth, stated as the arithmetic rather than as a claim: counting
           every category, this same person lands in 'part'. That is the
           answer the page used to give. */
        $pooled = 5000 / (500000 + 5000);
        ok('teeth: unscoped, that same person would read as partly paid',
            $pooled > 0 && $pooled < 1);

        DB::table('fees')->whereIn('id', [$unpaidInstalment, $paidResit])->delete();
    }

    /* --- a cancelled fee must move nothing ------------------------------- */
    $before = $svc->forBudget($current);

    $cancelledId = DB::table('fees')->insertGetId([
        'student_enroll_id' => $enroll->id, 'category_id' => $category->id,
        'fee_amount' => 999000, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 0, 'assign_date' => $assign, 'due_date' => $assign,
        // The literal 3, deliberately, not FeeCollection::CANCELLED. Written
        // as the constant, this fixture moved with the code: a teeth check
        // that changed the constant to 99 also changed the fixture, so the
        // suite passed while cancelled fees were being counted as debt.
        'status' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $after = $svc->forBudget($current);

    check('a cancelled fee changes nothing billed',
        money($before['students']['billed']), money($after['students']['billed']));
    check('a cancelled fee changes nothing outstanding',
        money($before['students']['outstanding']), money($after['students']['outstanding']));
    check('and does not appear among the unattributable',
        $before['unattributed']['count'], $after['unattributed']['count']);

    DB::table('fees')->where('id', $cancelledId)->delete();

    /* --- a fee billed at zero with nothing paid is settled, not unpaid ---- */
    $zeroEnroll = DB::table('student_enrolls')
        ->whereNotNull('student_id')
        ->where('student_id', '<>', $enroll->student_id)
        ->whereNotExists(function ($q) use ($windowFrom, $windowTo) {
            $q->select(DB::raw(1))->from('fees')
              ->whereColumn('fees.student_enroll_id', 'student_enrolls.id')
              ->whereBetween('fees.assign_date', [$windowFrom, $windowTo]);
        })
        ->first();

    if ($zeroEnroll) {
        $zeroBefore = $svc->forBudget($current)['students'];

        $waivedId = DB::table('fees')->insertGetId([
            'student_enroll_id' => $zeroEnroll->id, 'category_id' => $category->id,
            'fee_amount' => 80000, 'fine_amount' => 0, 'discount_amount' => 80000,
            'paid_amount' => 0, 'assign_date' => $assign, 'due_date' => $assign,
            'status' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $zeroAfter = $svc->forBudget($current)['students'];

        check('a wholly discounted fee counts its student as settled, not as having paid nothing',
            $zeroBefore['none'], $zeroAfter['none']);
        check('and that student is counted among the fully paid',
            $zeroBefore['full'] + 1, $zeroAfter['full']);

        DB::table('fees')->where('id', $waivedId)->delete();
    }

    /* --- someone holding credit is fully paid AND reported as holding it -- */
    $overEnroll = DB::table('student_enrolls')
        ->whereNotNull('student_id')
        ->where('student_id', '<>', $enroll->student_id)
        ->whereNotExists(function ($q) use ($windowFrom, $windowTo) {
            $q->select(DB::raw(1))->from('fees')
              ->whereColumn('fees.student_enroll_id', 'student_enrolls.id')
              ->whereBetween('fees.assign_date', [$windowFrom, $windowTo]);
        })
        ->first();

    if ($overEnroll) {
        $overBefore = $svc->forBudget($current)['students'];

        $overId = DB::table('fees')->insertGetId([
            'student_enroll_id' => $overEnroll->id, 'category_id' => $category->id,
            'fee_amount' => 60000, 'fine_amount' => 0, 'discount_amount' => 0,
            'paid_amount' => 75000, 'assign_date' => $assign, 'due_date' => $assign,
            'pay_date' => $assign, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $overAfter = $svc->forBudget($current)['students'];

        check('somebody who has paid more than they owe is counted as fully paid',
            $overBefore['full'] + 1, $overAfter['full']);
        check('they are not counted as a fourth kind of person',
            $overBefore['part'] + $overBefore['none'],
            $overAfter['part'] + $overAfter['none']);
        check('and the credit they hold is reported',
            money($overBefore['overpaid_amount'] + 15000),
            money($overAfter['overpaid_amount']));
        check('their held credit does not reduce what others still owe',
            money($overBefore['outstanding']), money($overAfter['outstanding']));

        DB::table('fees')->where('id', $overId)->delete();
    }

    /* --- a fee whose enrolment vanishes becomes unattributable ----------- */
    $ghostId = DB::table('fees')->insertGetId([
        'student_enroll_id' => 2147483000, 'category_id' => $category->id,
        'fee_amount' => 123456, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 0, 'assign_date' => $assign, 'due_date' => $assign,
        'status' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $ghost = $svc->forBudget($current);

    check('a fee pointing at an enrolment that does not exist is reported, not dropped',
        money($c['unattributed']['amount'] + 123456),
        money($ghost['unattributed']['amount']));

    $ghostRow = collect($ghost['unattributed']['rows'])->firstWhere('id', $ghostId);
    ok('and it is named as a missing enrolment',
        $ghostRow !== null && $ghostRow['reason'] === 'missing_enrolment');

    DB::table('fees')->where('id', $ghostId)->delete();

    DB::rollBack();
} catch (\Throwable $e) {
    DB::rollBack();
    $fail++;
    echo '  FAIL  the fixtures could not be exercised: ' . $e->getMessage() . "\n";
    echo '        ' . $e->getFile() . ':' . $e->getLine() . "\n";
}

/* ===================================================================
 |  8. It does not query per student
 |===================================================================*/

echo "\n== The cost does not grow with the roll ==\n";

DB::flushQueryLog();
DB::enableQueryLog();
$svc->forBudget($current);
$queries = count(DB::getQueryLog());
DB::disableQueryLog();

$peopleCount = $svc->forBudget($current)['combined']['total'];

ok('the whole section is a handful of aggregate queries, not one per person',
    $queries < 20);

printf("          (%d queries for %d people)\n", $queries, $peopleCount);

ok('and there are enough people on this install for a per-person query to show',
    $peopleCount > 5);

/* ===================================================================
 |  9. Colour is measured, not chosen
 |===================================================================*/

echo "\n== The encoding ==\n";

$analysisView = file_get_contents($base . '/resources/views/admin/daybook/analysis.blade.php');

/** WCAG relative luminance. */
$lum = function (string $hex): float {
    $hex = ltrim($hex, '#');
    $channel = function (int $v): float {
        $s = $v / 255;

        return $s <= 0.03928 ? $s / 12.92 : pow(($s + 0.055) / 1.055, 2.4);
    };

    return 0.2126 * $channel((int) hexdec(substr($hex, 0, 2)))
         + 0.7152 * $channel((int) hexdec(substr($hex, 2, 2)))
         + 0.0722 * $channel((int) hexdec(substr($hex, 4, 2)));
};

$ratio = function (string $a, string $b) use ($lum): float {
    $la = $lum($a);
    $lb = $lum($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
};

$fills = ['--paid-full' => '#0f7a3d', '--paid-part' => '#b8860b', '--paid-none' => '#b3261e'];

foreach ($fills as $token => $hex) {
    ok("{$token} is declared as {$hex}",
        (bool) preg_match('~' . preg_quote($token, '~') . ':\s*' . preg_quote($hex, '~') . ';~', $analysisView));

    // 3:1 is the floor for a graphical mark against its background.
    $r = $ratio($hex, '#ffffff');
    ok(sprintf('%s reaches 3:1 on the surface (%.2f:1)', $token, $r), $r >= 3.0);
}

/* The page's own status hues are NOT reused as fills, because --warning is
   1.79:1 on white and vanishes as a bar segment. Assert the absence. */
foreach (['--paid-full', '--paid-part', '--paid-none'] as $token) {
    ok("{$token} is not an alias of the chip palette",
        !preg_match('~' . preg_quote($token, '~') . ':\s*var\(~', $analysisView));
}

ok('the amber chip hue is still only a chip, never a fill',
    $ratio('#fab219', '#ffffff') < 3.0);

/* No fourth categorical hue crept in. */
$collectionView = file_get_contents($base . '/resources/views/admin/daybook/partials/collection.blade.php');
$withoutComments = preg_replace('~\{\{--.*?--\}\}~s', '', $collectionView);

check('the section introduces no raw hex of its own — every colour is a token',
    [], array_values(array_unique(preg_match_all('~#[0-9a-fA-F]{6}~', $withoutComments, $m) ? $m[0] : [])));

/* Identity is never colour alone: every swatch travels with its word. */
foreach ([__('Paid in full'), __('Paid in part'), __('Paid nothing')] as $word) {
    ok("the key names '{$word}' beside its swatch",
        strpos($withoutComments, $word) !== false);
}

/* ===================================================================
 |  10. The screen and the pack
 |===================================================================*/

echo "\n== The screen and the pack ==\n";

$admin = User::whereHas('roles')->first() ?: User::first();
Auth::guard('web')->login($admin);
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$rendered = [];

foreach ($budgets as $budget) {
    $request = Illuminate\Http\Request::create('/admin/daybook/analysis', 'GET', ['budget_id' => $budget->id]);
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    check("{$budget->title}: the analysis screen renders",
        200, $response->getStatusCode());

    $rendered[$budget->id] = $response->getContent();
}

$html = $rendered[$current->id];

foreach ([
    __('Who has paid, and who has not'),
    __('Paid in full'),
    __('Paid in part'),
    __('Paid nothing at all'),
    __('Collection by programme'),
    __('Collection by fee'),
    __('The largest outstanding balances'),
    __('Fee collection, month by month'),
] as $heading) {
    ok("the screen carries '{$heading}'", strpos($html, $heading) !== false);
}

/* The scope is on the screen, not only in the service. A page that applies a
   filter without naming it is a page that quietly answers a question nobody
   asked, and the reader has no way to tell. */
$c = $svc->forBudget($current);

foreach ($c['scope']['names'] as $name) {
    ok("the screen names '{$name}' as a fee it counted",
        strpos($html, e($name)) !== false);
}

ok('the screen marks which categories feed the counts',
    strpos($html, __('Counted above')) !== false);

if ($c['scope']['excluded']['fees'] > 0) {
    ok('and states what it left out, in money',
        strpos($html, e(__('Not counted above: :amount', [
            'amount' => number_format($c['scope']['excluded']['billed'])]))) !== false);
}

/* The uncounted categories are still on the page. This is the assertion that
   would fail if somebody "tidied up" by scoping the category table too. */
$uncounted = [];
foreach ($c['by_category'] as $row) {
    if (!$row['in_scope']) {
        $uncounted[] = $row['category'];
    }
}

$allShown = true;
foreach ($uncounted as $name) {
    if (strpos($html, e($name)) === false) {
        $allShown = false;
    }
}
ok('every uncounted category is still shown on the page', $allShown);

/* --- admission fees are reported, and reported APART ---------------------- */
$ad = $c['admissions'];

if ($ad['has_data']) {
    ok('the screen reports admission fees under their own heading',
        strpos($html, __('Admission fees, counted on their own')) !== false);

    /* The assertion that matters: not one person, franc or count of the
       admission block appears inside the student block. Reported apart is
       the whole requirement, and an accidental += would still produce a page
       that looked right. */
    $expectedStudents = recompute($c['window']['from'], $c['window']['to'], $instalmentIds);

    check('the student counts do not absorb the admission people',
        $expectedStudents['student']['total'], $c['students']['total']);
    check('nor the admission money',
        money($expectedStudents['student']['billed']), money($c['students']['billed']));

    /* And the admission block is itself right, recomputed by the same
       independent route with the other category set. */
    $admissionCats = [];
    foreach ($catalogue as $row) {
        $title = strtolower((string) $row->title);
        $isInstalment = str_contains($title, 'instalment') || str_contains($title, 'installment');
        if (!$isInstalment && str_contains($title, 'admission')) {
            $admissionCats[] = (int) $row->id;
        }
    }

    check('the service scopes admissions to the admission categories',
        $admissionCats, array_map('intval', $svc->admissionScope()['ids']));

    $mineAdmissions = recompute($c['window']['from'], $c['window']['to'], $admissionCats);
    $allAdmissions = [
        'full' => $mineAdmissions['student']['full'] + $mineAdmissions['applicant']['full'],
        'part' => $mineAdmissions['student']['part'] + $mineAdmissions['applicant']['part'],
        'none' => $mineAdmissions['student']['none'] + $mineAdmissions['applicant']['none'],
        'total' => $mineAdmissions['student']['total'] + $mineAdmissions['applicant']['total'],
    ];

    check('admission: paid in full', $allAdmissions['full'], $ad['full']);
    check('admission: paid in part', $allAdmissions['part'], $ad['part']);
    check('admission: paid nothing', $allAdmissions['none'], $ad['none']);
    check('admission: people billed', $allAdmissions['total'], $ad['total']);

    /* The two groups share no category, so no fee is counted twice. */
    check('no category is both an instalment and an admission fee',
        [], array_values(array_intersect($instalmentIds, $admissionCats)));
}

/* --- the programme ranking carries no row nobody can explain --------------
   Checked on EVERY budget, not on the current one. The dangling fee on this
   install was assigned in 2025/2026, so a check that looked only at the
   budget the page happens to open on passed while the row it was written to
   catch was still on the screen - which is exactly what it did the first
   time this was written. */
$ghostRows = [];

foreach ($budgets as $budget) {
    foreach ($svc->forBudget($budget)['by_programme'] as $row) {
        if ($row['people'] === 0) {
            $ghostRows[] = $budget->title . ': ' . $row['programme']
                . ' (' . number_format($row['due']) . ')';
        }
    }
}

/* A programme row with money and zero people is a fee whose enrolment was
   deleted, landing under "No programme recorded". It is unreadable in a
   report and it puts the same money on the page twice, since it is already
   counted as unattributable. */
check('no programme row shows money against nobody, on any budget', [], $ghostRows);

/* The figures on the page are the figures from the service. */

ok('the student counts are printed on the page',
    strpos($html, '>' . number_format($c['students']['full']) . '<') !== false);

ok('the proportion bar is drawn',
    strpos($html, 'class="fc-bar"') !== false);

ok('the bar carries its figures for a screen reader, not colour alone',
    (bool) preg_match('~class="fc-bar"[^>]*aria-label="[^"]+"~', $html));

/* --- the chart-capture contract ------------------------------------------
   The pack reads $charts[0..3] by POSITION. Assert the registration order in
   the page source so a chart inserted ahead of the others fails here rather
   than printing the wrong picture in a board pack. */
/* Modelled on what actually determines the capture index: the order of the
   keep() calls, and the canvas each one draws on. An earlier version of this
   assertion read the order of getElementById() calls instead, and survived a
   deliberate reordering — because a stray lookup elsewhere in the file still
   left the first four and the last one in place. */
$registrations = [];
$offset = 0;

while (($at = strpos($analysisView, 'keep(new Chart(', $offset)) !== false) {
    $window = substr($analysisView, $at, 400);
    $registrations[] = preg_match("~'(chart[A-Za-z]+)'~", $window, $m) ? $m[1]
        : (preg_match('~\b(feeCanvas)\b~', $window) ? 'chartFeeCollection' : '?');
    $offset = $at + 1;
}

check('the five charts are registered in the order the pack reads them',
    ['chartCash', 'chartMonthly', 'chartNet', 'chartPacing', 'chartFeeCollection'],
    $registrations);

$pdfView = file_get_contents($base . '/resources/views/admin/daybook/analysis-pdf.blade.php');

ok('the pack reads the collection chart at index 4',
    strpos($pdfView, '$chart(4)') !== false);

/* The pack renders, with and without captured images. */
$confidence = app(App\Services\Accounting\LedgerConfidence::class);

foreach ([[], ['data:image/png;base64,AAAA']] as $i => $charts) {
    $checks = $confidence->checks($current,
        optional($current->start_date)->format('Y-m-d'),
        optional($current->end_date)->format('Y-m-d'));

    $packHtml = view('admin.daybook.analysis-pdf', [
        'budget' => $current,
        'budgets' => $budgets,
        'periods' => $daybook->periodsFor($current),
        'period' => $daybook->periodsFor($current)->first(),
        'analysis' => $daybook->analysis($current),
        'collection' => $c,
        'checks' => $checks,
        'allClear' => $confidence->allClear($checks),
        'concerns' => $confidence->concerns($checks),
        'charts' => $charts,
        'generatedAt' => now(),
        'letterhead' => '', 'letterheadStyles' => '',
    ])->render();

    ok('the pack renders ' . ($i ? 'with a captured chart' : 'with no charts at all'),
        strpos($packHtml, __('Who has paid, and who has not')) !== false);

    /* The Students row, not the "Together" total: under the instalment scope
       no applicant holds a fee on this install, and the pack drops both the
       applicant row and the total rather than printing the same figures three
       times. Asserting on "Together" would pin the pack to a data condition
       instead of to its content. */
    ok('and carries the cohort table either way',
        strpos($packHtml, __('Students')) !== false
        && strpos($packHtml, __('Nothing')) !== false);

    /* The total row is conditional, so assert the condition rather than the
       row: present exactly when there is a second cohort to add up. */
    ok('and shows a combined total only when there are two cohorts to combine',
        (strpos($packHtml, __('Together')) !== false) === ($c['applicants']['total'] > 0));

    /* The pack states the scope. A pack that printed the counts without
       saying which fees they cover is the document somebody reads in a board
       meeting and misquotes. */
    ok('and names the fees it counted',
        strpos($packHtml, $c['scope']['names'][0]) !== false);

    ok('and says what it left out',
        $c['scope']['excluded']['fees'] === 0
        || strpos($packHtml, __('Counted')) !== false);

    /* The repair list is for the screen, not for a board meeting. The pack
       still has to account for the money, so the table goes and a sentence
       stays: dropping both would make the pack's totals quietly short. */
    ok('the pack leaves the repair list off',
        strpos($packHtml, __('Fees that belong to nobody')) === false);

    if ($c['unattributed']['count'] > 0) {
        ok('but still accounts for the money in a sentence',
            strpos($packHtml, __('A note on the totals: :count fee(s) worth :amount, of which :owed is still outstanding, are attached to enrolments that no longer exist and so belong to no named person. They are in none of the figures above. They are listed on the analysis screen for correction.', [
                'count' => number_format($c['unattributed']['count']),
                'amount' => number_format($c['unattributed']['amount']),
                'owed' => number_format($c['unattributed']['owed']),
            ])) !== false);
    }

    /* Admission fees reach the pack, under their own heading. */
    if ($c['admissions']['has_data']) {
        ok('the pack reports admission fees apart',
            strpos($packHtml, __('Admission fees, counted on their own')) !== false);
    }
}

/* --- the styling stays on this page -------------------------------------- */
$selectors = preg_match_all('~^\s*(\.fc-[a-z0-9-]+[^{]*)\{~mi', $analysisView, $m) ? $m[1] : [];

ok('the section has its own stylesheet rules', count($selectors) > 10);

/* Every new rule is prefixed .fc-, so nothing reaches the book or the summary,
   which share this page's layout but not its stylesheet. */
foreach (['/admin/daybook', '/admin/daybook/summary'] as $uri) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    check("{$uri} still renders", 200, $response->getStatusCode());

    ok("{$uri} does not carry the collection section",
        strpos($response->getContent(), __('Who has paid, and who has not')) === false);
}

/* ===================================================================
 |  10b. Each instalment on its own, and each programme's three counts
 |===================================================================*/

echo "\n== each instalment, and each programme, counted on its own ==\n";

$netSql = App\Models\Fee::netPaidSql('f');
$dueSql = 'f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0)';

/* The expectation is built from raw SQL, one category at a time, and never by
   asking the service a second question. */
$instalmentCohort = function (string $from, string $to, int $categoryId) use ($netSql, $dueSql) {
    $rows = DB::select("
        SELECT CONCAT('s:', se.student_id) AS person,
               SUM({$dueSql}) AS due, SUM({$netSql}) AS paid
        FROM fees f
        LEFT JOIN student_enrolls se ON se.id = f.student_enroll_id
        WHERE f.status <> 3
          AND f.category_id = ?
          AND se.student_id IS NOT NULL
          AND DATE(f.assign_date) >= ? AND DATE(f.assign_date) <= ?
        GROUP BY person
    ", [$categoryId, $from, $to]);

    $out = ['full' => 0, 'part' => 0, 'none' => 0, 'total' => 0,
            'billed' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0];

    foreach ($rows as $row) {
        $due = (float) $row->due;
        $paid = (float) $row->paid;

        if ($paid <= 0.005) {
            $out[$due <= 0.005 ? 'full' : 'none']++;
        } else {
            $out[$due - $paid <= 0.005 ? 'full' : 'part']++;
        }

        $out['total']++;
        $out['billed'] += $due;
        $out['collected'] += $paid;
        $out['outstanding'] += max(0.0, $due - $paid);
    }

    return $out;
};

foreach ($budgets as $budget) {
    $from = substr((string) $budget->start_date, 0, 10);
    $to = substr((string) $budget->end_date, 0, 10);
    $c = $svc->forBudget($budget);

    check("{$budget->title}: one row per instalment category",
        count($instalmentIds), count($c['by_instalment']));

    /* Read aloud to a board, so "Second" must not print above "First". The
       order is asserted from the NAMES, not from the ids that happen to
       produce the right order on this install. */
    $ordinals = [];
    foreach ($c['by_instalment'] as $row) {
        $lower = strtolower($row['category']);
        $rank = PHP_INT_MAX;
        foreach (['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5] as $word => $n) {
            if (strpos($lower, $word) !== false) {
                $rank = $n;
                break;
            }
        }
        $ordinals[] = $rank;
    }
    $ordered = $ordinals;
    sort($ordered);
    check("{$budget->title}: instalments print in their stated order", $ordered, $ordinals);

    $billedAcross = 0.0;

    foreach ($c['by_instalment'] as $row) {
        $mine = $instalmentCohort($from, $to, $row['category_id']);
        $label = "{$budget->title}: {$row['category']}";

        foreach (['full', 'part', 'none', 'total'] as $bucket) {
            check("{$label} {$bucket}", $mine[$bucket], $row[$bucket]);
        }

        foreach (['billed', 'collected', 'outstanding'] as $figure) {
            check("{$label} {$figure}", money($mine[$figure]), money($row[$figure]));
        }

        /* Self-consistent on its own line: the three counts are a partition of
           the people that instalment was billed to. */
        check("{$label}: the three counts are the students billed",
            $row['total'], $row['full'] + $row['part'] + $row['none']);

        $billedAcross += $row['billed'];
    }

    /* The counts do not add down, but the money must: every instalment fee has
       exactly one category, so the rows partition the headline's money. This
       is what stops the split from quietly losing or duplicating a fee. */
    check("{$budget->title}: the instalment rows account for all the student money",
        money($c['students']['billed']), money($billedAcross));

    /* ---- the programme table's three counts ---- */
    foreach ($c['by_programme'] as $row) {
        check("{$budget->title}: {$row['programme']} counts add to its students",
            $row['people'], $row['full'] + $row['part'] + $row['none']);
    }

    /* Recomputed per (person, programme) pair, which is the grouping the page
       claims to use: a student enrolled in two programmes is counted once
       under each, against what that programme billed them. */
    $scopeIn = implode(',', array_fill(0, count($instalmentIds), '?'));
    $pairs = DB::select("
        SELECT se.program_id AS pid,
               SUM({$dueSql}) AS due, SUM({$netSql}) AS paid
        FROM fees f
        LEFT JOIN student_enrolls se ON se.id = f.student_enroll_id
        WHERE f.status <> 3
          AND f.category_id IN ({$scopeIn})
          AND COALESCE(se.student_id, f.applicant_id) IS NOT NULL
          AND DATE(f.assign_date) >= ? AND DATE(f.assign_date) <= ?
        GROUP BY se.program_id,
                 CONCAT(CASE WHEN se.student_id IS NOT NULL THEN 's' ELSE 'a' END, ':',
                        COALESCE(se.student_id, f.applicant_id))
    ", array_merge($instalmentIds, [$from, $to]));

    $mineProg = [];
    foreach ($pairs as $pair) {
        $key = $pair->pid === null ? 0 : (int) $pair->pid;
        $mineProg[$key] = $mineProg[$key] ?? ['full' => 0, 'part' => 0, 'none' => 0];
        $due = (float) $pair->due;
        $paid = (float) $pair->paid;

        if ($paid <= 0.005) {
            $mineProg[$key][$due <= 0.005 ? 'full' : 'none']++;
        } else {
            $mineProg[$key][$due - $paid <= 0.005 ? 'full' : 'part']++;
        }
    }

    foreach (['full', 'part', 'none'] as $bucket) {
        check("{$budget->title}: programme column '{$bucket}' totals",
            array_sum(array_column($mineProg, $bucket)),
            array_sum(array_column($c['by_programme'], $bucket)));
    }

    /* The caveat the page prints has to be true of the data it prints it on. */
    $progPeople = array_sum(array_column($c['by_programme'], 'people'));
    ok("{$budget->title}: no student is lost between the programme rows and the headline",
        $progPeople >= $c['students']['total']);
}

/* ---- teeth ---- */

/* If by_instalment were scoped to the whole instalment set instead of to one
   category at a time, every row would print the same pooled figures - the bug
   this section was written to remove. */
$teeth = $svc->forBudget($budgets->first());
if (count($teeth['by_instalment']) > 1) {
    $distinct = count(array_unique(array_map(
        fn ($r) => $r['total'] . '/' . $r['full'] . '/' . $r['billed'],
        $teeth['by_instalment']
    )));

    ok('teeth: the instalment rows differ from one another, so each is its own cohort',
        $distinct > 1);
}

/* A person-level bucket on the programme table puts a dual-enrolled student in
   one programme only, leaving the other with people > 0 and three zero counts.
   That row was written once; this asserts it cannot come back. */
$selfContradicting = [];
foreach ($budgets as $budget) {
    foreach ($svc->forBudget($budget)['by_programme'] as $row) {
        if ($row['people'] > 0 && $row['full'] + $row['part'] + $row['none'] === 0) {
            $selfContradicting[] = $budget->title . ': ' . $row['programme'];
        }
    }
}
check('teeth: no programme row shows students with none of them counted', [], $selfContradicting);

/* ===================================================================
 |  11. Nothing was left behind
 |===================================================================*/

echo "\n== nothing was left behind ==\n";

$closing = [
    'fees' => DB::table('fees')->count(),
    'billed' => money(DB::table('fees')->sum(DB::raw('fee_amount - COALESCE(discount_amount,0) + COALESCE(fine_amount,0)'))),
    'paid' => money(DB::table('fees')->sum('paid_amount')),
    'credits' => DB::table('student_credits')->count(),
    'applications' => DB::table('credit_applications')->count(),
];

foreach ($opening as $key => $value) {
    check("the {$key} figure is exactly as it was ({$value})", $value, $closing[$key]);
}

printf("\n%d passed, %d failed\n", $pass, $fail);

exit($fail > 0 ? 1 : 0);
