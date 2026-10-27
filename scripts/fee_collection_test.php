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
 |  The independent recomputation
 |
 |  Deliberately a different shape of query from the service's: this one
 |  pulls every fee row and buckets them in PHP, where the service groups in
 |  SQL. Two routes to the same figure is the point.
 |===================================================================*/

/**
 * @return array{student: array, applicant: array, people: array}
 */
function recompute(string $from, string $to): array
{
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
           AND f.assign_date >= ?
           AND f.assign_date <= ?
    ", [$from, $to]);

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
    $mine = recompute($from, $to);

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

    $curve = array_sum(array_column($svc->forBudget($budget)['by_month'], 'cash'));

    check("{$budget->title}: the curve equals the daybook's fee rows",
        money($feeCash), money($curve));
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

    /* An invariant, not a snapshot. Pinning "11 people, 862,666.67" is how
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
               AND COALESCE(se.student_id, f.applicant_id) IS NOT NULL
             GROUP BY CASE WHEN se.student_id IS NOT NULL
                           THEN CONCAT('s', se.student_id)
                           ELSE CONCAT('a', f.applicant_id) END
            HAVING owed > 0.005
          ) t
    ", [$from])[0];

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

/* Category covers every fee in the window, so its billed total is the window's
   billed total INCLUDING the unattributable fees — which is why this is not
   compared against the people figures. */
$windowBilled = (float) DB::table('fees')
    ->where('status', '<>', 3)
    ->whereBetween('assign_date', [$c['window']['from'], $c['window']['to']])
    ->sum(DB::raw('fee_amount - COALESCE(discount_amount,0) + COALESCE(fine_amount,0)'));

check('the fee categories account for every fee billed in the window',
    money($windowBilled),
    money(array_sum(array_column($c['by_category'], 'due'))));

check('the programmes account for every fee billed in the window',
    money($windowBilled),
    money(array_sum(array_column($c['by_programme'], 'due'))));

$windowFees = DB::table('fees')
    ->where('status', '<>', 3)
    ->whereBetween('assign_date', [$c['window']['from'], $c['window']['to']])
    ->count();

check('and every fee is counted exactly once by category',
    $windowFees, array_sum(array_column($c['by_category'], 'fees')));

check('and exactly once by programme',
    $windowFees, array_sum(array_column($c['by_programme'], 'fees')));

/* Worst first, so the row that needs chasing is the row you read. */
$rates = array_column($c['by_programme'], 'rate');
$sorted = $rates;
sort($sorted);
check('programmes are ranked worst collection rate first', $sorted, $rates);

$catRates = array_column($c['by_category'], 'rate');
$catSorted = $catRates;
sort($catSorted);
check('so are fee categories', $catSorted, $catRates);

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
$category = DB::table('fees_categories')->first();

ok('there is an enrolment and a category to build fixtures on',
    $enroll !== null && $category !== null);

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
    $mine = recompute($windowFrom, $windowTo);
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

/* The figures on the page are the figures from the service. */
$c = $svc->forBudget($current);

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

    ok('and carries the cohort table either way',
        strpos($packHtml, __('Together')) !== false);
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
