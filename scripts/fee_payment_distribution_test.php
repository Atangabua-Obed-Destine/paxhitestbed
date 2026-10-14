<?php
/**
 * Spreading one handover of money across a student's instalments.
 *
 * Every write happens inside a transaction that is rolled back; the last section
 * proves the database was left as it was found rather than asserting it.
 *
 * The figures here are worked out independently of the thing under test — a
 * suite that asks the service what the answer should be proves nothing.
 *
 * Run: php scripts/fee_payment_distribution_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use App\Models\PaymentReceipt;
use App\Models\StudentCredit;
use App\Services\FeePaymentDistribution;
use App\Services\FeePaymentRecorder;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

function check(string $what, bool $ok, string $why = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  " . $what . "\n";
    } else {
        $failed++;
        echo "  FAIL  " . $what . ($why ? "\n        " . $why : '') . "\n";
    }
}

function skip(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  " . $what . "\n";
}

$distribution = app(FeePaymentDistribution::class);
$recorder = app(FeePaymentRecorder::class);

// A student carrying both instalments, neither of them touched, so every figure
// below is unambiguous. Found by what the fees ARE, not by asking the service.
$first = null;

foreach (Fee::with(['category', 'studentEnroll.student'])
    ->whereHas('category', fn ($q) => $q->where('is_first_installment', 1))
    ->whereRaw('COALESCE(paid_amount, 0) = 0')
    ->whereNull('payment_plan_id')
    ->whereDoesntHave('resitRequest')
    ->whereHas('studentEnroll.student')
    ->orderBy('id')->get() as $candidate) {

    $studentId = $candidate->studentEnroll->student_id;

    $second = Fee::whereHas('studentEnroll', fn ($q) => $q->where('student_id', $studentId))
        ->whereHas('category', fn ($q) => $q->where('is_second_installment', 1))
        ->whereRaw('COALESCE(paid_amount, 0) = 0')
        ->whereNull('payment_plan_id')
        ->first();

    if ($second) {
        $first = $candidate;
        break;
    }
}

if (!$first) {
    echo "No student carries two untouched instalments.\n";
    exit(1);
}

$studentId = $first->studentEnroll->student_id;
$second = Fee::with('category')
    ->whereHas('studentEnroll', fn ($q) => $q->where('student_id', $studentId))
    ->whereHas('category', fn ($q) => $q->where('is_second_installment', 1))
    ->whereRaw('COALESCE(paid_amount, 0) = 0')
    ->whereNull('payment_plan_id')
    ->first();

$firstDue = round((float) $first->fee_amount, 2);
$secondDue = round((float) $second->fee_amount, 2);
$both = round($firstDue + $secondDue, 2);

echo "Subject: student " . $first->studentEnroll->student->student_id
    . " — first #{$first->id} (" . number_format($firstDue, 2) . "), "
    . "second #{$second->id} (" . number_format($secondDue, 2) . ")\n";

// ---------------------------------------------------------------------------
section('What may absorb the money');
// ---------------------------------------------------------------------------
$targets = $distribution->targets($first);

check('the fee being paid comes first',
    $targets->first()->id === $first->id,
    'got #' . optional($targets->first())->id . ' — the money was handed over for this one');

check('the student\'s other instalment is offered',
    $targets->contains(fn ($t) => $t->id === $second->id),
    'offered: ' . $targets->pluck('id')->implode(', '));

check('nothing belonging to another student is offered',
    $targets->every(fn ($t) => optional($t->studentEnroll)->student_id === $studentId),
    'one payment must never touch another student\'s fees');

check('only instalments are offered',
    $targets->every(fn ($t) => optional($t->category)->is_first_installment
        || optional($t->category)->is_second_installment),
    'an admission or resit charge is settled on purpose, not swept up by tuition');

// Both of these are constructed on the student's OWN second instalment. Looking
// for somebody else's plan-backed fee proves nothing: the student filter would
// have excluded it whatever the rule said.
DB::beginTransaction();

try {
    $plan = \App\Models\PaymentPlan::create([
        'fee_id' => $second->id,
        'student_id' => $studentId,
        'total_amount' => $secondDue,
        'installments_count' => 2,
        'status' => 'active',
    ]);
    Fee::where('id', $second->id)->update(['payment_plan_id' => $plan->id]);

    check('a fee on an active payment plan is never offered',
        !$distribution->targets(Fee::find($first->id))->contains(fn ($t) => $t->id === $second->id),
        'a plan carries its own instalment dates and its own screen');
} finally {
    DB::rollBack();
}

DB::beginTransaction();

try {
    $anySubject = \App\Models\Subject::first();

    if (!$anySubject) {
        skip('no subject to hang a resit request on');
    } else {
        \App\Models\ResitRequest::create([
            'student_enroll_id' => $second->student_enroll_id,
            'subject_id' => $anySubject->id,
            'session_id' => optional($second->studentEnroll)->session_id ?? 1,
            'fee_amount' => $secondDue,
            'fee_id' => $second->id,
        ]);

        check('a fee tied to a resit request is never offered',
            !$distribution->targets(Fee::find($first->id))->contains(fn ($t) => $t->id === $second->id),
            'resit charges must be paid in full, deliberately, on their own');
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The cohort whose instalments live on different enrolments');
// ---------------------------------------------------------------------------
// For the 2026/2027 intake both instalments sit on one enrolment. For 2025/2026
// the second was raised months later against the second-semester enrolment. 62
// students are shaped that way, and looking by enrolment instead of by student
// finds nothing at all for any of them.
$crossFirst = null;
$crossSecond = null;

foreach (Fee::with(['category', 'studentEnroll'])
    ->whereHas('category', fn ($q) => $q->where('is_first_installment', 1))
    ->whereHas('studentEnroll.student')
    ->orderBy('id')->get() as $candidate) {

    $sibling = Fee::whereHas('studentEnroll', fn ($q) => $q->where('student_id', $candidate->studentEnroll->student_id))
        ->whereHas('category', fn ($q) => $q->where('is_second_installment', 1))
        ->where('student_enroll_id', '!=', $candidate->student_enroll_id)
        ->whereRaw('COALESCE(paid_amount, 0) < fee_amount')
        ->whereNull('payment_plan_id')
        ->first();

    if ($sibling) {
        $crossFirst = $candidate;
        $crossSecond = $sibling;
        break;
    }
}

if (!$crossFirst) {
    skip('no student carries instalments on two different enrolments');
} else {
    check('the second instalment is found even on another enrolment',
        $distribution->targets($crossFirst)->contains(fn ($t) => $t->id === $crossSecond->id),
        'first #' . $crossFirst->id . ' on enrolment ' . $crossFirst->student_enroll_id
            . ', second #' . $crossSecond->id . ' on enrolment ' . $crossSecond->student_enroll_id
            . ' - looked up by enrolment this finds nothing, and 62 students are shaped this way');
}

// ---------------------------------------------------------------------------
section('How an amount is spread');
// ---------------------------------------------------------------------------
$within = $distribution->propose($first, $firstDue);

check('an amount within the first instalment stays on it',
    count($within['legs']) === 1 && abs($within['legs'][0]['applied'] - $firstDue) < 0.005,
    count($within['legs']) . ' legs');

check('and leaves nothing over',
    abs($within['leftover']) < 0.005,
    'leftover ' . $within['leftover']);

$part = $distribution->propose($first, round($firstDue / 2, 2));

check('a part payment touches only the fee it was given for',
    count($part['legs']) === 1,
    count($part['legs']) . ' legs — a part payment must not reach the next instalment');

$spill = round($firstDue + 50000, 2);
$spread = $distribution->propose($first, $spill);

check('more than the first instalment reaches the second',
    count($spread['legs']) === 2,
    count($spread['legs']) . ' legs');

if (count($spread['legs']) === 2) {
    check('the first instalment is filled before the second is touched',
        abs($spread['legs'][0]['applied'] - $firstDue) < 0.005
            && abs($spread['legs'][1]['applied'] - 50000) < 0.005,
        $spread['legs'][0]['applied'] . ' then ' . $spread['legs'][1]['applied']);

    check('and the second leg is the second instalment',
        $spread['legs'][1]['fee_id'] === $second->id,
        'got fee #' . $spread['legs'][1]['fee_id']);
}

// A limitation worth stating rather than hiding: no student here carries more
// than two outstanding instalments, so the ordering of targets BEYOND the fee
// being paid is never exercised. First-before-second is correct by reading, not
// by measurement.
check('the legs account for every franc',
    abs(collect($spread['legs'])->sum('applied') + $spread['leftover'] - $spill) < 0.005,
    'legs ' . collect($spread['legs'])->sum('applied') . ' + leftover ' . $spread['leftover']
        . ' against ' . $spill);

$beyond = round($both + 50000, 2);
$over = $distribution->propose($first, $beyond);

check('money beyond every instalment is reported as leftover',
    abs($over['leftover'] - 50000) < 0.005,
    'leftover ' . $over['leftover'] . ', expected 50000.00');

check('and no leg is given more than its fee needs',
    collect($over['legs'])->every(fn ($leg) => $leg['applied'] <= $leg['outstanding'] + 0.005),
    json_encode($over['legs']));

// ---------------------------------------------------------------------------
section('A split the bursar edited is checked again');
// ---------------------------------------------------------------------------
check('allocating more to a fee than it needs is refused',
    $distribution->blockers($first, [
        ['fee_id' => $first->id, 'applied' => $firstDue + 1000],
    ], $firstDue + 1000) !== [],
    'the browser\'s figures are not the figures money moves against');

check('allocating to another student\'s fee is refused',
    (function () use ($distribution, $first, $studentId) {
        $stranger = Fee::whereHas('studentEnroll', fn ($q) => $q->where('student_id', '!=', $studentId))->first();

        return $stranger
            ? $distribution->blockers($first, [['fee_id' => $stranger->id, 'applied' => 1000]], 1000) !== []
            : true;
    })());

check('allocating more than was handed over is refused',
    $distribution->blockers($first, [
        ['fee_id' => $first->id, 'applied' => $firstDue],
        ['fee_id' => $second->id, 'applied' => $secondDue],
    ], $firstDue) !== [],
    'the split must never exceed the money received');

check('the same fee twice in one split is refused',
    $distribution->blockers($first, [
        ['fee_id' => $first->id, 'applied' => 1000],
        ['fee_id' => $first->id, 'applied' => 1000],
    ], 2000) !== []);

check('a sound split is allowed',
    $distribution->blockers($first, [
        ['fee_id' => $first->id, 'applied' => $firstDue],
        ['fee_id' => $second->id, 'applied' => 50000],
    ], $spill) === [],
    implode(' ', $distribution->blockers($first, [
        ['fee_id' => $first->id, 'applied' => $firstDue],
        ['fee_id' => $second->id, 'applied' => 50000],
    ], $spill)));

// ---------------------------------------------------------------------------
section('Through the screen, where the money actually moves');
// ---------------------------------------------------------------------------
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    skip('no Super Admin to act as');
} else {
    Illuminate\Support\Facades\Auth::guard('web')->login($admin);

    $pay = function (array $data) use ($kernel) {
        $request = Illuminate\Http\Request::create('/admin/fees-student-pay', 'POST',
            array_merge($data, ['_token' => csrf_token()]));
        $request->setLaravelSession(app('session.store'));

        return $kernel->handle($request);
    };

    // --- the ordinary split ------------------------------------------------
    DB::beginTransaction();

    try {
        $creditsBefore = StudentCredit::count();

        $pay([
            'fee_id' => $first->id,
            'paid_amount' => $spill,
            'pay_date' => date('Y-m-d'),
            'payment_method' => '1',
            'allow_overpayment' => '1',
            'note' => 'distribution suite',
        ]);

        $firstAfter = Fee::find($first->id);
        $secondAfter = Fee::find($second->id);

        check('the first instalment is settled',
            abs((float) $firstAfter->paid_amount - $firstDue) < 0.005,
            'got ' . $firstAfter->paid_amount);

        check('the second instalment received the rest',
            abs((float) $secondAfter->paid_amount - 50000) < 0.005,
            'got ' . $secondAfter->paid_amount);

        check('the student was credited with exactly what they handed over',
            abs(((float) $firstAfter->paid_amount + (float) $secondAfter->paid_amount) - $spill) < 0.005,
            'total ' . ((float) $firstAfter->paid_amount + (float) $secondAfter->paid_amount)
                . ' against ' . $spill . ' — not a franc more, not a franc less');

        check('no credit was raised',
            StudentCredit::count() === $creditsBefore,
            'the hand-applied credit is exactly what overstated the ledger');

        $group = PaymentReceipt::whereIn('fee_id', [$first->id, $second->id])
            ->whereNotNull('payment_group')->pluck('payment_group')->unique();

        check('both receipts belong to one handover',
            $group->count() === 1,
            $group->count() . ' groups — the receipt cannot show both instalments without this');
    } finally {
        DB::rollBack();
    }

    // --- the bursar moves money between the instalments --------------------
    DB::beginTransaction();

    try {
        $pay([
            'fee_id' => $first->id,
            'paid_amount' => $spill,
            'pay_date' => date('Y-m-d'),
            'payment_method' => '1',
            'allow_overpayment' => '1',
            'note' => 'distribution suite, edited split',
            'allocation' => [
                ['fee_id' => $first->id, 'applied' => 100000],
                ['fee_id' => $second->id, 'applied' => $spill - 100000],
            ],
        ]);

        check('an edited split is honoured as the bursar entered it',
            abs((float) Fee::find($first->id)->paid_amount - 100000) < 0.005
                && abs((float) Fee::find($second->id)->paid_amount - ($spill - 100000)) < 0.005,
            'first ' . Fee::find($first->id)->paid_amount
                . ', second ' . Fee::find($second->id)->paid_amount);

        check('and a part-paid instalment is left partly paid, not settled',
            (string) Fee::find($first->id)->status === '2',
            'status ' . Fee::find($first->id)->status);
    } finally {
        DB::rollBack();
    }

    // --- an edited split that does not add up ------------------------------
    DB::beginTransaction();

    try {
        $pay([
            'fee_id' => $first->id,
            'paid_amount' => 100000,
            'pay_date' => date('Y-m-d'),
            'payment_method' => '1',
            'allow_overpayment' => '1',
            'note' => 'distribution suite, impossible split',
            'allocation' => [
                ['fee_id' => $first->id, 'applied' => 100000],
                ['fee_id' => $second->id, 'applied' => 100000],
            ],
        ]);

        check('a split that comes to more than was received writes nothing',
            abs((float) Fee::find($first->id)->paid_amount) < 0.005
                && abs((float) Fee::find($second->id)->paid_amount) < 0.005,
            'first ' . Fee::find($first->id)->paid_amount
                . ', second ' . Fee::find($second->id)->paid_amount);
    } finally {
        DB::rollBack();
    }

    // --- the leftover gate -------------------------------------------------
    DB::beginTransaction();

    try {
        $pay([
            'fee_id' => $first->id,
            'paid_amount' => $beyond,
            'pay_date' => date('Y-m-d'),
            'payment_method' => '1',
            'allow_overpayment' => '1',
            'note' => 'distribution suite, no tick',
        ]);

        check('money beyond every instalment is refused without the tick box',
            abs((float) Fee::find($first->id)->paid_amount) < 0.005,
            'paid ' . Fee::find($first->id)->paid_amount
                . ' — holding money as credit must be a decision, not a side effect');
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        $creditsBefore = StudentCredit::count();

        $pay([
            'fee_id' => $first->id,
            'paid_amount' => $beyond,
            'pay_date' => date('Y-m-d'),
            'payment_method' => '1',
            'allow_overpayment' => '1',
            'allow_leftover_credit' => '1',
            'note' => 'distribution suite, ticked',
        ]);

        check('with the tick box both instalments are settled',
            abs((float) Fee::find($first->id)->paid_amount - $firstDue) < 0.005
                && abs((float) Fee::find($second->id)->paid_amount - $secondDue) < 0.005,
            'first ' . Fee::find($first->id)->paid_amount
                . ', second ' . Fee::find($second->id)->paid_amount);

        $raised = StudentCredit::orderByDesc('id')->first();

        check('and exactly one credit is raised, for the remainder only',
            StudentCredit::count() === $creditsBefore + 1
                && abs((float) $raised->original_amount - 50000) < 0.005,
            (StudentCredit::count() - $creditsBefore) . ' credits, newest '
                . optional($raised)->original_amount);
    } finally {
        DB::rollBack();
    }

    // --- the receipt -------------------------------------------------------
    DB::beginTransaction();

    try {
        $pay([
            'fee_id' => $first->id,
            'paid_amount' => $spill,
            'pay_date' => date('Y-m-d'),
            'payment_method' => '1',
            'allow_overpayment' => '1',
            'note' => 'distribution suite, receipt',
        ]);

        $request = Illuminate\Http\Request::create('/admin/fees-student-print/' . $first->id, 'GET');
        $request->setLaravelSession(app('session.store'));
        $receipt = $kernel->handle($request);
        $html = $receipt->getContent();

        check('the receipt renders', $receipt->getStatusCode() === 200,
            'status ' . $receipt->getStatusCode());

        check('it says what this payment settled',
            str_contains($html, __('This payment settled')),
            'one handover settling two instalments must not print as one line');

        check('it shows the student\'s whole instalment position',
            str_contains($html, __('Your instalments')),
            'the student should see where they stand from one sheet');

        check('both instalment amounts appear on it',
            str_contains($html, number_format($firstDue, 2, '.', ''))
                && str_contains($html, number_format($secondDue, 2, '.', '')),
            'first ' . number_format($firstDue, 2, '.', '')
                . ', second ' . number_format($secondDue, 2, '.', ''));
    } finally {
        DB::rollBack();
    }

    // --- an overpayment is no longer hidden on the receipt -----------------
    DB::beginTransaction();

    try {
        // Forced straight onto the fee, so the receipt has an overpayment to
        // print whether or not a split could have avoided it.
        DB::table('fees')->where('id', $first->id)->update([
            'paid_amount' => $firstDue + 25000,
            'status' => '1',
            'pay_date' => date('Y-m-d'),
        ]);

        $request = Illuminate\Http\Request::create('/admin/fees-student-print/' . $first->id, 'GET');
        $request->setLaravelSession(app('session.store'));
        $html = $kernel->handle($request)->getContent();

        check('an overpayment is printed, not clamped away',
            str_contains($html, '25000.00') || str_contains($html, '25,000.00'),
            'the receipt used to print min(paid, total) and max(0, balance), '
                . 'so money the student handed over vanished from it');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('The screen tells one story, not two');
// ---------------------------------------------------------------------------
// The split table said the extra went to the Second Instalment while the
// confirmation step still said it was held as credit. Both were on screen at
// once. A bursar reading contradictory sentences about the same money will
// believe the wrong one.
if (!isset($admin) || !$admin) {
    skip('no Super Admin to render the screen as');
} else {
    $request = Illuminate\Http\Request::create(
        '/admin/fees-student-report?faculty=0&program=0&session=0&semester=0&section=0&student_id=', 'GET');
    $request->setLaravelSession(app('session.store'));
    $page = $kernel->handle($request)->getContent();

    check('the modal no longer claims an overpayment is simply held as credit',
        !str_contains($page, 'This is more than the balance due. The extra is held as credit for this student.'),
        'that sentence contradicts the split table directly above it');

    check('the confirmation names the instalments the payment is spread over',
        str_contains($page, 'This payment is spread across the student'),
        'the confirmation must describe the same split the table shows');

    check('credit is only mentioned for money beyond every instalment',
        str_contains($page, 'This is more than every instalment the student owes. The remainder is held as credit.'));

    check('the confirmation reads the split rather than recomputing it',
        str_contains($page, "splitRows.querySelectorAll('tr')"),
        'two independent descriptions of one payment is how they drift apart');
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('the first instalment is untouched',
    abs((float) Fee::find($first->id)->paid_amount) < 0.005,
    'paid ' . Fee::find($first->id)->paid_amount);

check('the second instalment is untouched',
    abs((float) Fee::find($second->id)->paid_amount) < 0.005,
    'paid ' . Fee::find($second->id)->paid_amount);

check('no receipt carries a payment group',
    PaymentReceipt::whereNotNull('payment_group')->count() === 0,
    PaymentReceipt::whereNotNull('payment_group')->count() . ' do');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
