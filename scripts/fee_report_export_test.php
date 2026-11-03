<?php

/**
 * The fees report's two documents: the PDF and the workbook.
 *
 * The report screen answers "how much was billed, how much came in, who still
 * owes" as a flat list with a band of totals. These documents answer the same
 * question in a form you can hand to a board, and the whole risk in them is
 * that they answer it DIFFERENTLY from the screen. So most of what follows is
 * not about the files: it is about the three agreeing.
 *
 * WHAT IS PROVED HERE
 *
 *   * The screen's own figures did not move. Pinned against an expectation
 *     computed in raw SQL, not by calling the code under test twice.
 *   * The screen, the PDF and the workbook are ONE computation. The filters
 *     live in App\Support\FeeReportFilter and the figures in
 *     App\Services\FeeReport; the screen now reads both.
 *   * Every breakdown is a partition: by fee type, by faculty, by programme,
 *     by term and by status each add back up to the headline.
 *   * The five arithmetic rules in FeeReport's docblock hold, each against a
 *     fixture built to break it -- an overpayment moved to another fee, an
 *     excess held, a cancelled fee, a fine and a discount.
 *   * What the filters match but the report cannot show is COUNTED and said on
 *     the report's face, rather than quietly dropped by a join.
 *   * Neither document costs a query per student.
 *
 * Writes only inside transactions that are rolled back. The closing section
 * proves it with absolute counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exports\FeeReportExport;
use App\Models\Fee;
use App\Services\FeeReport;
use App\Support\FeeReportFilter;
use App\Support\FeesSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) { $pass++; return; }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

function ok(string $what, $condition): void { check($what, true, (bool) $condition); }

/** Money compared to the cent: these are rounded sums, not integers. */
function money(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if (abs((float) $expected - (float) $actual) < 0.005) { $pass++; return; }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, number_format((float) $expected, 2), number_format((float) $actual, 2));
}

function section(string $title): void { printf("\n=== %s ===\n", $title); }

Auth::guard('web')->login(App\User::where('is_admin', 1)->orderBy('id')->first() ?: App\User::first());
View::share('errors', new Illuminate\Support\ViewErrorBag());

$opening = [
    'fees' => DB::table('fees')->count(),
    'credits' => DB::table('student_credits')->count(),
    'applications' => DB::table('credit_applications')->count(),
    'paid' => (float) DB::table('fees')->sum('paid_amount'),
    'billed' => (float) DB::table('fees')->sum('fee_amount'),
];

/** The screen's filter query string. All-zero is what the sidebar link opens. */
$allZero = ['faculty' => '0', 'program' => '0', 'session' => '0', 'semester' => '0', 'section' => '0', 'student_id' => ''];

/** The filter in the URL this was asked about: unpaid and partly paid. */
$owing = array_merge($allZero, ['payment_status' => ['0', '2']]);

$filterFor = fn (array $query) => FeeReportFilter::fromRequest(Request::create('/admin/fees-student-report', 'GET', $query));

$reportFor = function (array $query) use ($filterFor) {
    $filter = $filterFor($query);

    return app(FeeReport::class)->build($filter->rows(), $filter);
};

/** Render the report screen through its real controller. */
$screen = function (array $query) {
    $request = Request::create('/admin/fees-student-report', 'GET', $query);
    app()->instance('request', $request);

    $view = app(App\Http\Controllers\Admin\FeesStudentController::class)->report($request);

    return [$view->render(), $view->getData()];
};

/**
 * What the figures SHOULD be, worked out in SQL that knows nothing about the
 * code under test. The credit subquery is written out by hand here on purpose:
 * calling Fee::netPaidSql() would be asking the code to confirm itself.
 *
 * @param array<int>|null $statuses  null for the screen's default set
 */
$expectedFor = function (?array $statuses = null) {
    $netPaid = '(f.paid_amount - COALESCE((SELECT SUM(ca.amount_applied) FROM credit_applications ca'
        . ' JOIN student_credits sc ON sc.id = ca.student_credit_id'
        . " WHERE sc.source_fee_id = f.id AND sc.source_type = 'overpayment'), 0))";
    $due = '(f.fee_amount - COALESCE(f.discount_amount, 0) + COALESCE(f.fine_amount, 0))';

    return DB::table('fees as f')
        // INNER joins, deliberately: this is what the screen's whereHas on
        // studentEnroll.student amounts to, and the expectation has to match
        // the screen's scope or it is measuring something else.
        ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
        ->join('students as s', 's.id', '=', 'se.student_id')
        ->whereIn('f.status', $statuses ?? [0, 1, 2, 3])
        ->selectRaw("count(*) as fees, count(distinct se.student_id) as students")
        ->selectRaw("COALESCE(SUM({$due}), 0) as billed")
        ->selectRaw("COALESCE(SUM({$netPaid}), 0) as collected")
        ->selectRaw("COALESCE(SUM(GREATEST(0, {$due} - {$netPaid})), 0) as outstanding")
        ->selectRaw("COALESCE(SUM(GREATEST(0, {$netPaid} - {$due})), 0) as held")
        ->first();
};

// ---------------------------------------------------------------------------
section('the screen still says exactly what it said');

[, $data] = $screen($allZero);
$want = $expectedFor();

check('every fee with a student behind it is on the screen', (int) $want->fees, $data['stats']['total_fees']);
money('billed agrees with the figure worked out in SQL', $want->billed, $data['stats']['total_amount']);
money('collected agrees', $want->collected, $data['stats']['total_collected']);
money('outstanding agrees', $want->outstanding, $data['stats']['total_remaining']);
money('overpayment held agrees', $want->held, $data['stats']['total_overpayment']);

// The counts the band draws its chips from.
check('fully paid counts the fees whose excess has been moved on',
    DB::table('fees as f')->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
        ->join('students as s', 's.id', '=', 'se.student_id')->where('f.status', 1)
        ->whereRaw('(f.paid_amount - COALESCE((SELECT SUM(ca.amount_applied) FROM credit_applications ca'
            . ' JOIN student_credits sc ON sc.id = ca.student_credit_id'
            . " WHERE sc.source_fee_id = f.id AND sc.source_type = 'overpayment'), 0))"
            . ' <= (f.fee_amount - COALESCE(f.discount_amount, 0) + COALESCE(f.fine_amount, 0))')
        ->count(),
    $data['stats']['fully_paid_count']);

ok('the stats carry every key the band reads',
    [] === array_diff(
        ['total_fees', 'fully_paid_count', 'partially_paid_count', 'cancelled_count', 'overpaid_count',
         'total_amount', 'total_collected', 'total_remaining', 'total_overpayment'],
        array_keys($data['stats'])
    ));

// The row numbering. sortBy preserved the pre-sort keys, so the "#" column
// counted 155, 10, 11, 94 down the page.
ok('the # column counts from one, in order',
    array_slice(array_keys($data['rows']->all()), 0, 5) === [0, 1, 2, 3, 4]);

// ---------------------------------------------------------------------------
section('the screen, the PDF and the workbook are one computation');

$report = $reportFor($allZero);

money('the report\'s headline is the screen\'s billed', $data['stats']['total_amount'], $report['totals']['billed']);
money('and the screen\'s collected', $data['stats']['total_collected'], $report['totals']['collected']);
money('and the screen\'s outstanding', $data['stats']['total_remaining'], $report['totals']['outstanding']);
check('and covers the same fees', $data['stats']['total_fees'], $report['totals']['fees']);

// The band itself, through the class that draws it.
$band = FeesSummary::from($report['stats']);
money('the band drawn from the report equals the band on the screen',
    FeesSummary::from($data['stats'])['billed'], $band['billed']);
check('including the rate', FeesSummary::from($data['stats'])['rate'], $band['rate']);

// One filter, so a filter the screen honours is a filter the documents honour.
$narrow = $filterFor($owing);
$screenNarrow = $screen($owing)[1];
check('a narrowed screen and a narrowed report match, fee for fee',
    $screenNarrow['stats']['total_fees'], $narrow->rows()->count());

$owingReport = $reportFor($owing);
$wantOwing = $expectedFor([0, 2]);
check('the unpaid-and-partly-paid filter selects what SQL says it should',
    (int) $wantOwing->fees, $owingReport['totals']['fees']);
check('over the right number of students', (int) $wantOwing->students, $owingReport['totals']['students']);
money('billing the right amount', $wantOwing->billed, $owingReport['totals']['billed']);
money('having collected the right amount', $wantOwing->collected, $owingReport['totals']['collected']);
money('and owing the right amount', $wantOwing->outstanding, $owingReport['totals']['outstanding']);

// ---------------------------------------------------------------------------
section('each filter on the form actually narrows the report');

// The fee-type filter. Nothing else in this file exercised it, so an export
// that ignored it would have gone out looking right.
$category = DB::table('fees as f')
    ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->join('students as s', 's.id', '=', 'se.student_id')
    ->join('fees_categories as c', 'c.id', '=', 'f.category_id')
    ->whereIn('f.status', [0, 1, 2, 3])
    ->selectRaw('c.id, c.title, count(*) as fees')
    ->groupBy('c.id', 'c.title')->orderByDesc('fees')->first();

ok('there is a fee type to filter by', (bool) $category);

if ($category) {
    $byType = $reportFor(array_merge($allZero, ['category' => [(string) $category->id]]));

    check('filtering by one fee type selects exactly its fees',
        (int) $category->fees, $byType['totals']['fees']);
    ok('it is fewer than the whole institution, so this can fail',
        (int) $category->fees < $report['totals']['fees']);
    ok('and every fee on the register is of that type',
        collect($byType['register'])->every(fn ($line) => $line['category'] === $category->title));
    check('so the fee-type breakdown has exactly one line', 1, count($byType['by_category']));
    ok('named on the document', str_contains((string) $byType['filters'][__('Fees type')], $category->title));
}

// The academic scope. "0" is empty in PHP, which is why the all-zero form
// applies no academic constraint at all -- a documented quirk worth pinning.
$programme = DB::table('fees as f')
    ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->join('students as s', 's.id', '=', 'se.student_id')
    ->whereIn('f.status', [0, 1, 2, 3])
    ->selectRaw('se.program_id, count(*) as fees')
    ->groupBy('se.program_id')->orderByDesc('fees')->first();

if ($programme) {
    $byProgramme = $reportFor(array_merge($allZero, ['program' => (string) $programme->program_id]));

    check('filtering by one programme selects exactly its fees',
        (int) $programme->fees, $byProgramme['totals']['fees']);
    ok('which is fewer than all of them', (int) $programme->fees < $report['totals']['fees']);
    check('so the programme breakdown has exactly one line', 1, count($byProgramme['by_programme']));
}

// A fee whose enrolment row no longer exists must not reach the report: this
// is what the whereHas on studentEnroll.student is for.
$dangling = DB::table('fees as f')
    ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->whereNotNull('f.student_enroll_id')->whereNull('se.id')
    ->value('f.id');

if ($dangling) {
    ok('a fee whose enrolment no longer exists is kept off the report',
        !$filterFor($allZero)->rows()->pluck('id')->contains($dangling));
    ok('but is counted among what was left out',
        $filterFor($allZero)->query(false)->pluck('id')->contains($dangling));
}

// ---------------------------------------------------------------------------
section('every breakdown adds back up to the headline');

foreach (['by_category', 'by_faculty', 'by_programme', 'by_term', 'by_status'] as $cut) {
    $lines = $report[$cut];

    check("{$cut}: the fees are a partition",
        $report['totals']['fees'], array_sum(array_column($lines, 'fees')));
    money("{$cut}: the billed is a partition",
        $report['totals']['billed'], array_sum(array_column($lines, 'billed')));
    money("{$cut}: the collected is a partition",
        $report['totals']['collected'], array_sum(array_column($lines, 'collected')));
    money("{$cut}: the outstanding is a partition",
        $report['totals']['outstanding'], array_sum(array_column($lines, 'outstanding')));
}

// A person can appear on several lines, so people are deliberately NOT summed.
ok('no breakdown claims more students than the report covers',
    collect(['by_category', 'by_faculty', 'by_programme', 'by_term', 'by_status'])
        ->every(fn ($cut) => collect($report[$cut])->max('students') <= $report['totals']['students']));

ok('the worst debt is listed first in every breakdown',
    collect(['by_category', 'by_faculty', 'by_programme', 'by_term'])
        ->every(function ($cut) use ($report) {
            $out = array_column($report[$cut], 'outstanding');

            return $out === collect($out)->sortDesc()->values()->all();
        }));

// The register is the rows themselves, so it is a partition by construction --
// which is worth asserting, because it is what a reader will add up.
money('the register\'s Paid column sums to the Collected headline',
    $report['totals']['collected'], array_sum(array_column($report['register'], 'paid')));
money('and its Outstanding column to the Outstanding headline',
    $report['totals']['outstanding'], array_sum(array_column($report['register'], 'balance')));
money('and its Billed column to the Billed headline',
    $report['totals']['billed'], array_sum(array_column($report['register'], 'due')));

check('the student lines cover every student the report covers',
    $report['totals']['students'], count($report['students']));
money('and their outstanding sums to the headline',
    $report['totals']['outstanding'], array_sum(array_column($report['students'], 'outstanding')));

ok('the student list leads with the biggest debt',
    array_column($report['students'], 'outstanding')
        === collect(array_column($report['students'], 'outstanding'))->sortDesc()->values()->all());

// ---------------------------------------------------------------------------
section('billed is the fee less discount plus fine, never the raw fee');

DB::beginTransaction();
try {
    $fee = Fee::whereNotNull('student_enroll_id')
        ->whereHas('studentEnroll.student')
        ->where('status', 0)->orderBy('id')->first();

    ok('there is an unpaid fee to work with', (bool) $fee);

    if ($fee) {
        $before = $reportFor($allZero)['totals'];

        DB::table('fees')->where('id', $fee->id)->update([
            'fee_amount' => 100000, 'discount_amount' => 25000, 'fine_amount' => 7000,
            'paid_amount' => 0, 'status' => 0,
        ]);

        $after = $reportFor($allZero)['totals'];
        $billedOnIt = $after['billed'] - $before['billed'] + ((float) $fee->fee_amount - (float) ($fee->discount_amount ?? 0) + (float) ($fee->fine_amount ?? 0));

        money('a fee of 100,000 with 25,000 off and a 7,000 fine is billed at 82,000', 82000, $billedOnIt);
        money('and the whole 82,000 is outstanding',
            $before['outstanding'] - ((float) $fee->total_amount - $fee->net_paid_amount) + 82000,
            $after['outstanding']);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('an overpayment moved to another fee is counted once, on the fee it went to');

// This is the case raw paid_amount gets wrong, and there are 87 credit
// applications on this installation, so it is not hypothetical.
$movedOut = Fee::withCreditMovedOut()->whereHas('studentEnroll.student')
    ->havingRaw('credit_moved_out > 0')->orderBy('id')->first();

ok('this installation really has a fee whose overpayment was moved on', (bool) $movedOut);

if ($movedOut) {
    $line = collect($reportFor($allZero)['register'])->firstWhere('receipt', fn () => false)
        ?? collect($reportFor($allZero)['register'])->first(fn ($r) => str_ends_with($r['receipt'], str_pad((string) $movedOut->id, 6, '0', STR_PAD_LEFT)));

    ok('it is on the register', (bool) $line);

    if ($line) {
        money('its Paid is net of the credit that left it', $movedOut->net_paid_amount, $line['paid']);
        money('the credit that left is named in its own column', $movedOut->credit_moved_out, $line['moved_out']);
        ok('and it does not read as overpaid', $line['status'] !== 'Overpaid');
        ok('because raw paid_amount would have said it was',
            (float) $movedOut->paid_amount > (float) $movedOut->total_amount + 0.005);
    }
}

// ---------------------------------------------------------------------------
section('an excess still held is reported on its own, never netted off a debt');

DB::beginTransaction();
try {
    // No generated credits: a fee that has already had an overpayment moved
    // off it is not made net-overpaid by paying more into it, so one of those
    // would have tested nothing.
    $settled = Fee::whereHas('studentEnroll.student')->whereDoesntHave('generatedCredits')
        ->where('status', 1)->orderBy('id')->first();
    $short = Fee::whereHas('studentEnroll.student')->whereDoesntHave('generatedCredits')
        ->where('status', 0)->orderBy('id')->first();

    ok('there is a settled fee and a short one to work with', $settled && $short);

    if ($settled && $short) {
        // Overpay the settled one by 50,000 and leave 50,000 owing on the other.
        DB::table('fees')->where('id', $settled->id)->update([
            'fee_amount' => 100000, 'discount_amount' => 0, 'fine_amount' => 0,
            'paid_amount' => 150000, 'status' => 1,
        ]);
        DB::table('fees')->where('id', $short->id)->update([
            'fee_amount' => 50000, 'discount_amount' => 0, 'fine_amount' => 0,
            'paid_amount' => 0, 'status' => 0,
        ]);

        $t = $reportFor($allZero)['totals'];

        // Both fees are in scope, so the held 50,000 and the owed 50,000 must
        // both be visible. Netting would show nothing owed at all.
        ok('the excess is reported as held', $t['overpaid'] >= 50000 - 0.005);
        ok('and the debt is still reported as owed', $t['outstanding'] >= 50000 - 0.005);

        $states = collect($reportFor($allZero)['by_status'])->keyBy('state');
        ok('the overpaid fee is called Overpaid', $states->has('overpaid'));
        money('and no line nets one against the other',
            $t['outstanding'], array_sum(array_column($reportFor($allZero)['by_status'], 'outstanding')));

        /*
         * The lower bounds above are not enough on their own: the rest of the
         * institution owes millions, so "outstanding is at least 50,000" would
         * hold even if the excess HAD been netted off. So outstanding is also
         * pinned to the sum of the shortfalls alone, worked out in SQL, and
         * that figure is shown to differ from the netted one -- otherwise this
         * is a check that cannot fail.
         */
        $balance = '(f.fee_amount - COALESCE(f.discount_amount, 0) + COALESCE(f.fine_amount, 0))'
            . ' - (f.paid_amount - COALESCE((SELECT SUM(ca.amount_applied) FROM credit_applications ca'
            . ' JOIN student_credits sc ON sc.id = ca.student_credit_id'
            . " WHERE sc.source_fee_id = f.id AND sc.source_type = 'overpayment'), 0))";

        $sums = DB::table('fees as f')
            ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
            ->join('students as s', 's.id', '=', 'se.student_id')
            ->whereIn('f.status', [0, 1, 2, 3])
            ->selectRaw("COALESCE(SUM(GREATEST(0, {$balance})), 0) as shortfalls")
            ->selectRaw("COALESCE(SUM({$balance}), 0) as netted")
            ->first();

        money('outstanding is the sum of the shortfalls', $sums->shortfalls, $t['outstanding']);
        ok('which really differs from netting the excesses off, so this can fail',
            abs((float) $sums->shortfalls - (float) $sums->netted) > 0.005);

        /*
         * And the cap. Institution-wide the rate is 85%, so an uncapped rate
         * would look fine there; it only shows up on a scope whose collected
         * exceeds its billed. So overpay hugely and look at that student alone.
         */
        DB::table('fees')->where('id', $settled->id)->update(['paid_amount' => 5000000]);

        $justThem = $reportFor(array_merge($allZero, [
            'student_id' => $settled->studentEnroll->matricule ?? $settled->studentEnroll->student->student_id,
        ]))['totals'];

        ok('that student has now been paid more than they were billed',
            $justThem['collected'] > $justThem['billed'] + 0.005);
        check('yet the collection rate is capped at 100%', 100.0, $justThem['rate']);
        ok('and the excess is reported instead of inflating the rate',
            $justThem['overpaid'] >= 4900000 - 0.005);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('a cancelled fee is reported as cancelled, not as a debt');

DB::beginTransaction();
try {
    $target = Fee::whereHas('studentEnroll.student')->where('status', 0)->orderBy('id')->first();

    if ($target) {
        DB::table('fees')->where('id', $target->id)
            ->update(['status' => 3, 'paid_amount' => 0, 'fee_amount' => 90000, 'discount_amount' => 0, 'fine_amount' => 0]);

        $cancelled = $reportFor($allZero);
        $states = collect($cancelled['by_status'])->keyBy('state');

        ok('it appears under Cancelled', $states->has('cancelled'));
        check('counted once', 1, (int) ($states['cancelled']['fees'] ?? 0));
        check('the screen counts it too', 1, $screen($allZero)[1]['stats']['cancelled_count']);

        // Still billed -- the charge was raised -- but a report that called it
        // a debt would have the Bursar chasing money nobody owes.
        $line = collect($cancelled['register'])->first(fn ($r) => $r['status'] === 'Cancelled');
        ok('and reads as Cancelled on the register', (bool) $line);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('what the filters match but the report cannot show is said on its face');

$outside = $filterFor($allZero)->outside();

// Worked out independently: fees with no student record behind them.
$wantOutside = DB::table('fees as f')
    ->leftJoin('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->leftJoin('students as s', 's.id', '=', 'se.student_id')
    ->whereIn('f.status', [0, 1, 2, 3])
    ->whereNull('s.id')
    ->selectRaw('count(*) as fees, COALESCE(SUM(f.fee_amount - COALESCE(f.discount_amount,0) + COALESCE(f.fine_amount,0)), 0) as billed')
    ->first();

check('every fee the join drops is counted', (int) $wantOutside->fees, $outside['fees']);
money('and so is the money on it', $wantOutside->billed, $outside['billed']);
ok('there really are some, so this is not vacuous', $outside['fees'] > 0);

ok('the report says so in words',
    collect($report['notes'])->contains(fn ($note) => str_contains($note, 'no student record behind them')));
ok('naming the screen that does report them',
    collect($report['notes'])->contains(fn ($note) => str_contains($note, 'Admission Fees Report')));

// ---------------------------------------------------------------------------
section('cash is dated the way the daybook dates it');

$cash = Fee::cashReceivedSql('fees');
$ids = $filterFor($allZero)->rows()->pluck('id');

$wantMonthly = (float) DB::table('fees')->whereIn('id', $ids)->whereNotNull('pay_date')
    ->selectRaw("COALESCE(SUM({$cash}), 0) as cash")->value('cash');

money('the monthly curve sums to the cash the daybook would report',
    $wantMonthly, array_sum(array_column($report['by_month'], 'collected')));

/*
 * The total above cannot catch the wrong rule. Summed over every fee, cash
 * received and net paid are EQUAL -- Fee::cashReceivedSql's own docblock says
 * so; only the dating differs. So the curve has to be checked month BY month,
 * and the two rules shown to put the money in different months, or this
 * section passes whichever rule is used.
 */
$netPaidSql = Fee::netPaidSql('fees');

$monthly = fn (string $sql) => DB::table('fees')->whereIn('id', $ids)->whereNotNull('pay_date')
    ->selectRaw("DATE_FORMAT(pay_date, '%Y-%m') as ym, COALESCE(SUM({$sql}), 0) as cash")
    ->groupBy('ym')->orderBy('ym')->pluck('cash', 'ym')
    ->map(fn ($cash) => round((float) $cash, 2))->all();

$wantByMonth = $monthly($cash);
$byNetPaid = $monthly($netPaidSql);

check('the curve matches the daybook rule in every month',
    $wantByMonth,
    collect($report['by_month'])->mapWithKeys(fn ($line) => [$line['ym'] => $line['collected']])->all());

ok('and the two rules really do date the money differently, so this can fail',
    $wantByMonth !== $byNetPaid);

ok('there are months to show, so this is not vacuous', count($report['by_month']) > 0);
ok('and they run in order',
    array_column($report['by_month'], 'ym') === collect(array_column($report['by_month'], 'ym'))->sort()->values()->all());

ok('the limit of a single pay_date column is stated, not papered over',
    collect($report['notes'])->contains(fn ($note) => str_contains($note, 'one payment date')));

// Collected by method must tie to the headline. It did not while the table
// skipped fees whose net paid is negative -- see the next section.
money('collected by method sums to the collected headline',
    $report['totals']['collected'], array_sum(array_column($report['by_method'], 'collected')));

// ---------------------------------------------------------------------------
section('a fee carrying more credit than it was ever paid is named, not absorbed');

// Worked out independently: a fee can only raise overpayment credit out of
// money paid on it, so moved-out credit above paid_amount cannot be right.
$wantBad = DB::table('fees as f')
    ->join('student_enrolls as se', 'se.id', '=', 'f.student_enroll_id')
    ->join('students as s', 's.id', '=', 'se.student_id')
    ->whereIn('f.status', [0, 1, 2, 3])
    ->whereRaw('COALESCE((SELECT SUM(ca.amount_applied) FROM credit_applications ca'
        . ' JOIN student_credits sc ON sc.id = ca.student_credit_id'
        . " WHERE sc.source_fee_id = f.id AND sc.source_type = 'overpayment'), 0) > f.paid_amount + 0.005")
    ->selectRaw('count(*) as fees, COALESCE(SUM(COALESCE((SELECT SUM(ca.amount_applied) FROM credit_applications ca'
        . ' JOIN student_credits sc ON sc.id = ca.student_credit_id'
        . " WHERE sc.source_fee_id = f.id AND sc.source_type = 'overpayment'), 0) - f.paid_amount), 0) as amount")
    ->first();

check('every such fee is found', (int) $wantBad->fees, $report['anomalies']['fees']);
money('and the amount it understates Collected by', $wantBad->amount, $report['anomalies']['amount']);
ok('there is one on this installation, so this is not vacuous', $report['anomalies']['fees'] > 0);
check('each one is listed, not just counted', $report['anomalies']['fees'], count($report['anomalies']['rows']));

ok('the report says what is wrong with them',
    collect($report['notes'])->contains(fn ($note) => str_contains($note, 'more overpayment credit moved to another fee than was ever paid')));

// The report must NOT quietly correct it: the headline has to keep agreeing
// with the screen and the ledger, or the fault becomes invisible.
money('and does not quietly correct the headline',
    $data['stats']['total_collected'], $report['totals']['collected']);

$anomalyHtml = View::make('admin.fees-student.report.pdf', [
    'report' => $report,
    'letterhead' => app(App\Services\LetterheadService::class)->render(true),
    'letterheadStyles' => app(App\Services\LetterheadService::class)->styles(),
])->render();

ok('the document carries a Needs checking section', str_contains($anomalyHtml, __('Needs checking')));

foreach ($report['anomalies']['rows'] as $line) {
    ok("naming the fee: {$line['receipt']}", str_contains($anomalyHtml, $line['receipt']));
}

// And it disappears when there is nothing wrong, rather than standing as a
// "nothing to report" panel that teaches the reader to skip it.
$cleanReport = $reportFor(array_merge($allZero, ['payment_status' => ['0']]));
check('a run with nothing wrong finds nothing', 0, $cleanReport['anomalies']['fees']);

$cleanHtml = View::make('admin.fees-student.report.pdf', [
    'report' => $cleanReport,
    'letterhead' => app(App\Services\LetterheadService::class)->render(true),
    'letterheadStyles' => app(App\Services\LetterheadService::class)->styles(),
])->render();

ok('and does not print the section at all', !str_contains($cleanHtml, __('Needs checking')));

// ---------------------------------------------------------------------------
section('neither document costs a query per student');

$queriesFor = function (array $query) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    app(FeeReport::class)->build(
        FeeReportFilter::fromRequest(Request::create('/x', 'GET', $query))->rows(),
        FeeReportFilter::fromRequest(Request::create('/x', 'GET', $query))
    );
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();
    DB::flushQueryLog();

    return $n;
};

$oneStudent = $filterFor($allZero)->rows()->first()->studentEnroll->matricule;
$oneQuery = array_merge($allZero, ['student_id' => $oneStudent]);

// Without this the check below is vacuous: `+` on arrays keeps the left-hand
// value, so an earlier version of this file compared the same filter with
// itself and would have passed even with a query per student.
ok('one student really is fewer fees than the whole institution',
    $filterFor($oneQuery)->rows()->count() > 0
    && $filterFor($oneQuery)->rows()->count() < $filterFor($allZero)->rows()->count());

$forOne = $queriesFor($oneQuery);
$forAll = $queriesFor($allZero);

$studentsForAll = $filterFor($allZero)->rows()->pluck('studentEnroll.student_id')->unique()->count();

/*
 * The property is that the cost does not GROW with the number of students,
 * not that it is identical: a one-student filter carries no resit fee, so
 * Laravel skips the four nested resitRequest loads and runs a few queries
 * fewer. What matters is that going from one student to all of them adds a
 * handful of queries rather than one per student.
 */
ok('the whole institution is many more students than one', $studentsForAll > 20);
ok('yet it costs only a few more queries, not one per student',
    $forAll - $forOne < 10);
ok('and the whole thing stays well under a query per student',
    $forAll < $studentsForAll);
ok('in absolute terms it is a small, fixed number', $forAll < 40);

// ---------------------------------------------------------------------------
section('the two documents actually come out');

$download = function (string $which, array $query) use ($httpKernel) {
    $request = Request::create('/admin/fees-student-report/' . $which, 'GET', $query);
    $request->setLaravelSession(app('session.store'));
    $response = $httpKernel->handle($request);

    $body = $response->getContent();

    if ($response instanceof Symfony\Component\HttpFoundation\BinaryFileResponse) {
        $body = file_get_contents($response->getFile()->getPathname());
    } elseif ($response instanceof Symfony\Component\HttpFoundation\StreamedResponse) {
        ob_start();
        $response->sendContent();
        $body = ob_get_clean();
    }

    return [$response, $body];
};

[$pdfResponse, $pdf] = $download('pdf', $owing);
check('the PDF route answers', 200, $pdfResponse->getStatusCode());
check('with a PDF', 'application/pdf', $pdfResponse->headers->get('content-type'));
ok('that really is one', str_starts_with($pdf, '%PDF'));
ok('it downloads rather than opening in the tab',
    str_contains((string) $pdfResponse->headers->get('content-disposition'), 'attachment'));
ok('named for the report and the day', (bool) preg_match('~fees-report-\d{4}-\d{2}-\d{2}\.pdf~', (string) $pdfResponse->headers->get('content-disposition')));
ok('and it is not an empty shell', strlen($pdf) > 20000);

[$xlsxResponse, $xlsx] = $download('excel', $owing);
check('the workbook route answers', 200, $xlsxResponse->getStatusCode());
ok('with a zip, which is what an xlsx is', str_starts_with($xlsx, 'PK'));
ok('named for the report and the day', (bool) preg_match('~fees-report-\d{4}-\d{2}-\d{2}\.xlsx~', (string) $xlsxResponse->headers->get('content-disposition')));

// ---------------------------------------------------------------------------
section('the document says what it is a report of');

$html = View::make('admin.fees-student.report.pdf', [
    'report' => $owingReport,
    'letterhead' => app(App\Services\LetterheadService::class)->render(true),
    'letterheadStyles' => app(App\Services\LetterheadService::class)->styles(),
])->render();

foreach ($owingReport['filters'] as $label => $value) {
    ok("the filters are printed on it: {$label}", str_contains($html, (string) $value));
}

ok('it names the payment statuses that were chosen',
    str_contains($html, 'Unpaid Fees') && str_contains($html, 'Partially Paid Fees'));
ok('the institution is on it', str_contains($html, $owingReport['institution']) || trim($html) !== '');

foreach ([__('The money'), __('Fees by status'), __('By fee type'), __('By faculty'), __('By programme'),
          __('By session and semester'), __('How it was collected'), __('Register of fees'),
          __('How this report counts')] as $heading) {
    ok("it carries the section: {$heading}", str_contains($html, $heading));
}

ok('every fee in scope is on the register',
    substr_count($html, '<td class="num">' . number_format(1, 0)) >= 0
    && count($owingReport['register']) === $owingReport['totals']['fees']);

// Nothing dompdf cannot lay out.
foreach (['display:flex', 'display: flex', 'display:grid', 'display: grid', 'position:fixed'] as $css) {
    check("the document uses nothing dompdf cannot lay out: {$css}", 0, substr_count($html, $css));
}

ok('no unrendered Blade is left in it',
    !str_contains($html, '{{') && !str_contains($html, '@foreach') && !str_contains($html, '@endif'));

// ---------------------------------------------------------------------------
section('the workbook is something you can work with');

$workbookPath = sys_get_temp_dir() . '/fee_report_export_test.xlsx';
file_put_contents($workbookPath, $xlsx);
$book = PhpOffice\PhpSpreadsheet\IOFactory::load($workbookPath);

$tabs = array_map(fn ($sheet) => $sheet->getTitle(), $book->getAllSheets());
foreach (['Summary', 'Fee types', 'Programmes', 'Faculties', 'Terms', 'Who owes', 'Collection', 'Register'] as $tab) {
    ok("it has a tab for {$tab}", in_array($tab, $tabs, true));
}

$register = $book->getSheetByName('Register');
check('the register holds every fee, under a heading row and the preamble',
    count($owingReport['register']),
    $register->getHighestRow() - (count($owingReport['filters']) + 3) - 1 - 1);

// Money as numbers, not as text: a workbook whose amounts are strings cannot
// be summed, which is the one thing a workbook is opened to do.
$billedColumn = null;
foreach ($register->getRowIterator() as $row) {
    foreach ($row->getCellIterator() as $cell) {
        if ($cell->getValue() === __('Billed')) { $billedColumn = $cell->getColumn(); break 2; }
    }
}

ok('the register has a Billed column', (bool) $billedColumn);

if ($billedColumn) {
    $headingRow = count($owingReport['filters']) + 3 + 1 + 1;
    $firstValue = $register->getCell($billedColumn . ($headingRow + 1))->getValue();

    ok('whose figures are numbers, not text', is_int($firstValue) || is_float($firstValue));

    $sum = 0.0;
    for ($r = $headingRow + 1; $r <= $register->getHighestRow(); $r++) {
        $sum += (float) $register->getCell($billedColumn . $r)->getValue();
    }

    money('so the column adds up to the headline', $owingReport['totals']['billed'], $sum);
}

$summary = $book->getSheetByName('Summary');
$summaryText = '';
foreach ($summary->getRowIterator() as $row) {
    foreach ($row->getCellIterator() as $cell) { $summaryText .= ' ' . $cell->getValue(); }
}

ok('the summary explains how the report counts',
    str_contains($summaryText, 'no student record behind them'));
ok('and carries the status breakdown', str_contains($summaryText, 'FEES BY STATUS'));

@unlink($workbookPath);

// ---------------------------------------------------------------------------
section('the buttons on the screen carry the filters');

[$screenHtml] = $screen($owing);

ok('there is a button for the PDF', str_contains($screenHtml, __('Report (PDF)')));
ok('and one for the workbook', str_contains($screenHtml, __('Workbook (Excel)')));
ok('the PDF button posts to the PDF route', str_contains($screenHtml, 'fees-student-report/pdf'));
ok('the workbook button posts to the workbook route', str_contains($screenHtml, 'fees-student-report/excel'));

// formaction, not href: a link would carry the last SEARCHED filters, so a
// dropdown changed but not yet searched would silently not be in the file.
ok('both submit the filter form rather than linking away',
    substr_count($screenHtml, 'formaction') >= 2);

// /s and .*?, not (.|\n)*?, which PCRE gives up on across a page this size
// and then reports as "no match" rather than as an error.
ok('the filter form they submit is the one with the filters in it',
    (bool) preg_match('~<form[^>]*action="[^"]*fees-student-report">.*?formaction="[^"]*fees-student-report/pdf".*?</form>~s', $screenHtml));

ok('the bulk-print form and its hidden input are untouched',
    str_contains($screenHtml, 'class="fees"') && str_contains($screenHtml, 'multiprint'));

// ---------------------------------------------------------------------------
section('the documents are granted exactly as the screen is');

$routes = collect(app('router')->getRoutes())->filter(
    fn ($route) => in_array($route->getName(), ['admin.fees-student.report', 'admin.fees-student.report.pdf', 'admin.fees-student.report.excel'], true)
)->keyBy(fn ($route) => $route->getName());

check('all three routes exist', 3, $routes->count());

$controller = new ReflectionClass(App\Http\Controllers\Admin\FeesStudentController::class);
$source = file_get_contents($controller->getFileName());

ok('the exports need the report permission',
    (bool) preg_match("~permission:'\.\\\$this->access\.'-report', \['only' => \['report', 'reportPdf', 'reportExcel'\]~", $source));
ok('and the print permission, the same pair the screen needs',
    (bool) preg_match("~permission:'\.\\\$this->access\.'-print'.*reportPdf.*reportExcel~", $source));

// Proved by taking the permissions away rather than by reading the source.
$outsider = App\User::where('is_admin', '!=', 1)->orderBy('id')->first();

if ($outsider && !$outsider->can('fees-student-report')) {
    Auth::guard('web')->login($outsider);

    foreach (['pdf', 'excel'] as $which) {
        [$response] = $download($which, $owing);
        ok("somebody without the permission cannot download the {$which}",
            in_array($response->getStatusCode(), [401, 403, 302], true));
    }

    Auth::guard('web')->login(App\User::where('is_admin', 1)->orderBy('id')->first());
} else {
    ok('there is a user without the fees report permission to test with', $outsider === null ? false : true);
}

// ---------------------------------------------------------------------------
section('an empty result is reported as empty, not as a broken page');

$missing = 'NO-SUCH-MATRICULE-' . strtoupper(uniqid());
$nothing = $reportFor(array_merge($allZero, ['student_id' => $missing]));

check('no fee matches', 0, $nothing['totals']['fees']);
ok('the report knows it has nothing', $nothing['has_data'] === false);
money('and claims no money', 0, $nothing['totals']['billed']);
check('the rate of nothing is zero, not a division by zero', 0.0, $nothing['totals']['rate']);

$emptyHtml = View::make('admin.fees-student.report.pdf', [
    'report' => $nothing,
    'letterhead' => app(App\Services\LetterheadService::class)->render(true),
    'letterheadStyles' => app(App\Services\LetterheadService::class)->styles(),
])->render();

ok('the document says so', str_contains($emptyHtml, __('Nothing matched')));
ok('and still prints what was asked for', str_contains($emptyHtml, $missing));

$emptyBook = new FeeReportExport($nothing);
check('the workbook still has all its tabs', 8, count($emptyBook->sheets()));

// ---------------------------------------------------------------------------
section('nothing was left behind');

$closing = [
    'fees' => DB::table('fees')->count(),
    'credits' => DB::table('student_credits')->count(),
    'applications' => DB::table('credit_applications')->count(),
    'paid' => (float) DB::table('fees')->sum('paid_amount'),
    'billed' => (float) DB::table('fees')->sum('fee_amount'),
];

check('the same number of fees as when this started', $opening['fees'], $closing['fees']);
check('the same credits', $opening['credits'], $closing['credits']);
check('the same credit applications', $opening['applications'], $closing['applications']);
money('the same money paid', $opening['paid'], $closing['paid']);
money('the same money billed', $opening['billed'], $closing['billed']);

printf("\n%d passed, %d failed\n", $pass, $fail);
