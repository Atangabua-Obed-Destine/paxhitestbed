<?php

/**
 * Correcting the amount on a fee that is already assigned.
 *
 * A fee's amount is copied onto the student's row at enrolment from the
 * programme's fee structure, and nothing reads that structure again. A figure
 * configured wrongly therefore stays wrong on every student raised from it, and
 * a fee with payments cannot be deleted and re-raised. This is the only way to
 * correct one.
 *
 * The rule is one-directional on purpose: the charge may go up, because a
 * student who has paid is asked for the difference; it may not come down once
 * money has been taken against the old figure, because that would turn their
 * payment into an overpayment and issue credit the school never agreed to.
 *
 * The check that matters most is not whether the number changed — it is whether
 * STATUS followed it. A fee raised above what was paid that still reads "fully
 * paid" is a debt the school cannot see, which is worse than never having
 * allowed the correction.
 *
 * Every write happens inside a transaction that is rolled back.
 *
 *   php scripts/fee_assignment_amount_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Fee;
use App\Models\PaymentReceipt;
use App\Models\StudentCredit;
use App\Services\FeeAssignmentAmount;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

$service = app(FeeAssignmentAmount::class);

/** A fee nobody has paid against, so it can be moved in either direction. */
$untouched = Fee::with(['paymentPlan', 'resitRequest'])->get()
    ->first(fn ($f) => (float) $f->paid_amount <= 0.005
        && !$f->resitRequest
        && !$f->hasActivePaymentPlan()
        && !$f->paymentReceipts()->exists());

/** A fee with money against it: the case the rule exists for. */
$paidFee = Fee::with(['paymentPlan', 'resitRequest'])->get()
    ->first(fn ($f) => (float) $f->paid_amount > 0.005
        && !$f->resitRequest
        && !$f->hasActivePaymentPlan());

if (!$paidFee) {
    echo "no fee with payments to test the rule against\n";
    exit(0);
}

// Read from the database, not from the model: apply() mutates the instance it
// is given, and a rolled-back transaction does not undo that. Comparing against
// the in-memory object would compare against a figure that was never saved.
$startingAmount = (float) Fee::find($paidFee->id)->fee_amount;

echo 'paid fee #' . $paidFee->id . ' — charge ' . number_format($paidFee->fee_amount, 2)
    . ', paid ' . number_format($paidFee->paid_amount, 2) . ', status ' . $paidFee->status . "\n";
echo $untouched
    ? 'untouched fee #' . $untouched->id . ' — charge ' . number_format($untouched->fee_amount, 2) . "\n"
    : "no untouched fee found\n";

// ---------------------------------------------------------------------------
section('Which way the amount may move');
// ---------------------------------------------------------------------------
check('raising a fee that has been paid against is allowed',
    $service->canChange($paidFee, (float) $paidFee->fee_amount + 50000),
    implode(' ', $service->blockers($paidFee, (float) $paidFee->fee_amount + 50000)));

$lowerReasons = $service->blockers($paidFee, (float) $paidFee->fee_amount - 1000);

check('lowering it is refused',
    $lowerReasons !== [],
    'a student who has paid would be turned into an overpayment');

check('and the refusal says how much has been paid',
    (bool) preg_grep('/already been paid/i', $lowerReasons),
    'a bursar needs to know why, not just that — ' . implode(' ', $lowerReasons));

check('setting it to the amount it already is is refused',
    $service->blockers($paidFee, (float) $paidFee->fee_amount) !== [],
    'nothing to do, and it would still write a note');

check('zero is refused',
    $service->blockers($paidFee, 0) !== []);

if (!$untouched) {
    skip('no untouched fee, so lowering-when-unpaid cannot be checked');
} else {
    check('lowering a fee nobody has paid against is allowed',
        $service->canChange($untouched, max(1, (float) $untouched->fee_amount - 1000)),
        'a plain typo must still be correctable before anyone pays — '
            . implode(' ', $service->blockers($untouched, max(1, (float) $untouched->fee_amount - 1000))));
}

// ---------------------------------------------------------------------------
section('Raising a paid fee leaves it honest');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    // Chosen to land above what was paid, so the fee must stop reading as settled.
    $target = (float) $paidFee->paid_amount + 75000;
    $before = $paidFee->replicate();

    $result = $service->apply($paidFee, $target, 'wrong amount on the programme fee structure', null);
    $after = Fee::find($paidFee->id);

    check('the amount is changed', abs((float) $after->fee_amount - $target) < 0.01,
        $after->fee_amount . ' vs ' . $target);

    check('and the status follows it',
        (string) $after->status === '2',
        'status ' . $after->status . ' — a fee raised above what was paid that still '
            . 'reads as settled is a debt the school cannot see');

    check('the balance now shows what is owed',
        $after->remaining_balance > 0.005,
        'balance ' . $after->remaining_balance);

    check('what the student paid is untouched',
        abs((float) $after->paid_amount - (float) $before->paid_amount) < 0.005
            || $result['credit_applied'] > 0,
        'paid went from ' . $before->paid_amount . ' to ' . $after->paid_amount
            . ' with ' . $result['credit_applied'] . ' of credit applied');

    check('the correction is written on the record',
        str_contains((string) $after->note, FeeAssignmentAmount::MARKER)
            && str_contains((string) $after->note, 'wrong amount on the programme fee structure'),
        'a money correction nobody can trace afterwards is not a correction');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Money is not moved by changing a charge');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $student = optional($paidFee->studentEnroll)->student_id;
    $receiptsBefore = PaymentReceipt::where('fee_id', $paidFee->id)->count();
    $creditsBefore = $student ? StudentCredit::where('student_id', $student)->sum('remaining_amount') : null;

    $service->apply($paidFee, (float) $paidFee->fee_amount + 25000, null, null);

    check('no payment receipt is added or removed',
        PaymentReceipt::where('fee_id', $paidFee->id)->count() === $receiptsBefore);

    if ($student === null) {
        skip('this fee has no student to check a credit balance for');
    } else {
        $creditsAfter = StudentCredit::where('student_id', $student)->sum('remaining_amount');

        // Credit may legitimately be consumed to settle the increase, but it
        // must never grow: raising a charge cannot create money.
        check('the student\'s credit balance does not grow',
            (float) $creditsAfter <= (float) $creditsBefore + 0.005,
            'went from ' . $creditsBefore . ' to ' . $creditsAfter);
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The guards hold');
// ---------------------------------------------------------------------------
$onPlan = Fee::with('paymentPlan')->get()->first(fn ($f) => $f->hasActivePaymentPlan());

if (!$onPlan) {
    skip('no fee is on an active payment plan');
} else {
    $reasons = $service->blockers($onPlan, (float) $onPlan->fee_amount + 1000);

    check('a fee on an active payment plan is refused',
        (bool) preg_grep('/payment plan/i', $reasons),
        'the plan total is a snapshot; the fee would outgrow what the student agreed to');

    check('and the plan is named in the refusal',
        (bool) preg_grep('/#' . $onPlan->payment_plan_id . '/', $reasons),
        implode(' ', $reasons));
}

$resitFee = Fee::with('resitRequest')->get()->first(fn ($f) => $f->resitRequest !== null);

if (!$resitFee) {
    skip('no resit fee to check');
} else {
    check('a resit fee is refused',
        (bool) preg_grep('/resit/i', $service->blockers($resitFee, (float) $resitFee->fee_amount + 1000)),
        'resit fees come from the resit configuration');
}

$pending = Fee::whereHas('paymentReceipts', fn ($q) => $q->where('verification_status', 'pending'))->first();

if (!$pending) {
    skip('no fee has a receipt waiting to be verified');
} else {
    check('a fee with a receipt awaiting verification is refused',
        (bool) preg_grep('/verif/i', $service->blockers($pending, (float) $pending->fee_amount + 1000)),
        'the amount would move under a payment being checked');
}

// ---------------------------------------------------------------------------
section('A refusal refuses');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    // A fresh instance: the sections above handed apply() this fee and it wrote
    // to the object, so the one in memory no longer matches the row.
    $subject = Fee::find($paidFee->id);
    $before = (float) $subject->fee_amount;
    $result = $service->apply($subject, $before - 1000, null, null);

    check('apply() reports it did not change anything', $result['changed'] === false);

    check('and the fee is exactly as it was',
        abs((float) Fee::find($subject->id)->fee_amount - $before) < 0.005,
        'a guard that reports a refusal but writes anyway is worse than none');

    check('with the reason carried back for the screen',
        $result['reasons'] !== []);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Through the screens, not just the service');
// ---------------------------------------------------------------------------
// The rule lives in one class, but it is reached two ways. Both are exercised,
// and against the same fee, because the whole reason for one class is that the
// two cannot disagree.
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    skip('no Super Admin to act as');
} else {
    Illuminate\Support\Facades\Auth::guard('web')->login($admin);

    $post = function (string $url, array $data) use ($kernel) {
        $request = Illuminate\Http\Request::create($url, 'POST', array_merge(['_token' => csrf_token()], $data));
        $request->setLaravelSession(app('session.store'));

        return $kernel->handle($request);
    };

    // --- the row action --------------------------------------------------
    DB::beginTransaction();

    try {
        $subject = Fee::find($paidFee->id);
        $target = (float) $subject->fee_amount + 30000;

        $post('/admin/fee-assignments-history/' . $subject->id . '/amount', [
            'fee_amount' => $target,
            'reason' => 'wrong amount on the programme fee structure',
        ]);

        check('the row action changes the fee',
            abs((float) Fee::find($subject->id)->fee_amount - $target) < 0.01,
            'got ' . Fee::find($subject->id)->fee_amount);
    } finally {
        DB::rollBack();
    }

    // --- a refusal through the screen must still refuse ------------------
    DB::beginTransaction();

    try {
        $subject = Fee::find($paidFee->id);
        $before = (float) $subject->fee_amount;

        $post('/admin/fee-assignments-history/' . $subject->id . '/amount', [
            'fee_amount' => $before - 5000,
            'reason' => 'trying to lower a fee that has been paid',
        ]);

        check('lowering a paid fee through the screen writes nothing',
            abs((float) Fee::find($subject->id)->fee_amount - $before) < 0.005,
            'the guard has to hold on the server, not only in the modal');
    } finally {
        DB::rollBack();
    }

    // --- the cohort action -------------------------------------------------
    DB::beginTransaction();

    try {
        $subject = Fee::find($paidFee->id);
        $target = (float) $subject->fee_amount + 40000;

        // A second fee that must NOT move, because it was not ticked.
        $untouchedByThis = Fee::where('id', '!=', $subject->id)->first();
        $otherBefore = (float) $untouchedByThis->fee_amount;

        $post('/admin/fee-assignments-correct', [
            'fees' => [$subject->id],
            'fee_amount' => $target,
            'reason' => 'wrong amount on the programme fee structure',
        ]);

        check('the cohort action changes the ticked fee',
            abs((float) Fee::find($subject->id)->fee_amount - $target) < 0.01,
            'got ' . Fee::find($subject->id)->fee_amount);

        check('and leaves a fee that was not ticked alone',
            abs((float) Fee::find($untouchedByThis->id)->fee_amount - $otherBefore) < 0.005,
            'a cohort tool that moves rows nobody selected is the worst kind');
    } finally {
        DB::rollBack();
    }

    // --- the two agree -----------------------------------------------------
    if (!$onPlan) {
        skip('no blocked fee to compare the two routes on');
    } else {
        DB::beginTransaction();

        try {
            $before = (float) Fee::find($onPlan->id)->fee_amount;

            $post('/admin/fee-assignments-history/' . $onPlan->id . '/amount', [
                'fee_amount' => $before + 1000,
                'reason' => 'should be refused on both routes',
            ]);
            $afterRow = (float) Fee::find($onPlan->id)->fee_amount;

            $post('/admin/fee-assignments-correct', [
                'fees' => [$onPlan->id],
                'fee_amount' => $before + 1000,
                'reason' => 'should be refused on both routes',
            ]);
            $afterCohort = (float) Fee::find($onPlan->id)->fee_amount;

            check('a fee blocked one way is blocked the other way too',
                abs($afterRow - $before) < 0.005 && abs($afterCohort - $before) < 0.005,
                'row left it at ' . $afterRow . ', cohort at ' . $afterCohort
                    . ' — one rule reached two ways must give one answer');
        } finally {
            DB::rollBack();
        }
    }
}

// ---------------------------------------------------------------------------
section('The button on the screen can actually work');
// ---------------------------------------------------------------------------
// The handler opens the modal with $(...).modal(), which is Bootstrap's jQuery
// plugin. jQuery itself is in the head, but BOOTSTRAP is loaded in the footer:
// inside the content section the script ran before it and threw
// "$(...).modal is not a function", so the button looked right and did nothing.
// Measured against bootstrap, not jQuery - the first version of this check
// compared against jQuery and passed under a sabotage that broke the button.
if (!$admin) {
    skip('no Super Admin to render the screen as');
} else {
    $request = Illuminate\Http\Request::create('/admin/fee-assignments-history', 'GET');
    $request->setLaravelSession(app('session.store'));
    $page = $kernel->handle($request)->getContent();

    $bootstrap = strpos($page, 'bootstrap.min.js');
    $handler = strpos($page, "btn-correct-amount'");
    $modal = strpos($page, 'id="correctAmountModal"');

    check('the correction modal is on the page', $modal !== false);

    check('and its handler is registered', $handler !== false,
        'no handler means the button does nothing when pressed');

    check('the handler runs after Bootstrap is loaded',
        $bootstrap !== false && $handler !== false && $handler > $bootstrap,
        'before Bootstrap, $(...).modal is not a function and the button does nothing — '
            . 'bootstrap at ' . var_export($bootstrap, true) . ', handler at ' . var_export($handler, true));

    check('the confirm button starts disabled',
        (bool) preg_match('/id="ca-submit"[^>]*disabled/', $page),
        'the tick box is what arms it');
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('the fee is at the amount it started with',
    abs((float) Fee::find($paidFee->id)->fee_amount - $startingAmount) < 0.005,
    'this suite must not change a real charge — now '
        . Fee::find($paidFee->id)->fee_amount . ', started at ' . $startingAmount);

check('and carries no correction note from this run',
    !str_contains((string) Fee::find($paidFee->id)->note, FeeAssignmentAmount::MARKER),
    'a rolled-back transaction should leave no trace at all');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
