<?php
/**
 * Correcting the payment deadline on a fee that has already been assigned.
 *
 * Every write happens inside a transaction that is rolled back, so this leaves
 * the database exactly as it found it — the last section proves that rather
 * than asserting it.
 *
 * What is actually at stake here is not the date but the late fee it decides,
 * so the fine arithmetic is checked against an independently computed figure,
 * not against the service's own answer.
 *
 * Run: php scripts/fee_assignment_due_date_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use App\Models\FeesFine;
use App\Services\FeeAssignmentDueDate;
use Carbon\Carbon;
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

$service = app(FeeAssignmentDueDate::class);

// Subjects, found by what they ARE rather than by anything the service says.
$open = Fee::whereRaw('COALESCE(paid_amount, 0) < (fee_amount + COALESCE(fine_amount,0) - COALESCE(discount_amount,0))')
    ->whereDoesntHave('resitRequest')
    ->whereNull('payment_plan_id')
    ->whereDoesntHave('paymentReceipts', fn ($q) => $q->where('verification_status', 'pending'))
    ->first();

$settled = Fee::whereRaw('COALESCE(paid_amount, 0) >= (fee_amount + COALESCE(fine_amount,0) - COALESCE(discount_amount,0))')
    ->where('paid_amount', '>', 0)
    ->first();

$onPlan = Fee::whereNotNull('payment_plan_id')
    ->whereHas('paymentPlan', fn ($q) => $q->where('status', 'active'))
    ->first();

$resit = Fee::whereHas('resitRequest')->first();

echo "Subjects: open #" . ($open->id ?? '-')
    . ', settled #' . ($settled->id ?? '-')
    . ', on a plan #' . ($onPlan->id ?? '-')
    . ', resit #' . ($resit->id ?? '-') . "\n";

// ---------------------------------------------------------------------------
section('A deadline can be moved, and the move is what was asked for');
// ---------------------------------------------------------------------------
if (!$open) {
    skip('no open fee to move');
} else {
    DB::beginTransaction();

    try {
        $fee = Fee::find($open->id);
        $before = Carbon::parse($fee->due_date)->format('Y-m-d');
        $target = Carbon::parse($before)->addDays(45)->format('Y-m-d');

        $result = $service->apply($fee, $target, 'the date was wrong on the fee structure', null);

        $stored = Fee::find($open->id);

        check('it says it changed', $result['changed'], implode(' ', $result['reasons']));
        check('the stored deadline is the one asked for',
            Carbon::parse($stored->due_date)->format('Y-m-d') === $target,
            'stored ' . $stored->due_date . ', asked ' . $target);
        check('the old date is reported back', $result['old'] === $before,
            'got ' . var_export($result['old'], true) . ', was ' . $before);
    } finally {
        DB::rollBack();
    }

    // Backwards too: a deadline wrongly set far in the future has to come back.
    DB::beginTransaction();

    try {
        $fee = Fee::find($open->id);
        $assigned = $fee->assign_date ? Carbon::parse($fee->assign_date) : Carbon::parse($fee->due_date)->subYear();
        $target = $assigned->copy()->addDays(1)->format('Y-m-d');

        $result = $service->apply($fee, $target, 'pulled back to the real deadline', null);

        check('a deadline can be pulled earlier as well as pushed later',
            $result['changed'] && Carbon::parse(Fee::find($open->id)->due_date)->format('Y-m-d') === $target,
            implode(' ', $result['reasons']));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('What a deadline must not be');
// ---------------------------------------------------------------------------
if (!$open) {
    skip('no open fee to refuse');
} else {
    $fee = Fee::find($open->id);
    $current = Carbon::parse($fee->due_date)->format('Y-m-d');

    check('the same date is refused',
        !$service->canChange($fee, $current),
        'moving a deadline to where it already is is a no-op dressed as a change');

    check('an empty date is refused', !$service->canChange($fee, null));
    check('a date that is not a date is refused', !$service->canChange($fee, 'next Thursday-ish'));

    if ($fee->assign_date) {
        $before = Carbon::parse($fee->assign_date)->subDay()->format('Y-m-d');

        check('a deadline before the fee was assigned is refused',
            !$service->canChange($fee, $before),
            'a fee cannot fall due before it was charged');

        check('and the refusal names the assignment date',
            (bool) array_filter($service->blockers($fee, $before),
                fn ($r) => str_contains($r, Carbon::parse($fee->assign_date)->format('d M Y'))),
            implode(' ', $service->blockers($fee, $before)));
    } else {
        skip('that fee has no assignment date to compare against');
    }
}

// ---------------------------------------------------------------------------
section('The guards hold');
// ---------------------------------------------------------------------------
$far = Carbon::today()->addYears(2)->format('Y-m-d');

if (!$settled) {
    skip('no settled fee');
} else {
    $reasons = $service->blockers(Fee::find($settled->id), $far);
    check('a settled fee refuses', $reasons !== []);
    check('and says it is settled',
        (bool) array_filter($reasons, fn ($r) => str_contains(strtolower($r), 'settled')),
        implode(' ', $reasons));
}

if (!$onPlan) {
    skip('no fee on an active payment plan');
} else {
    $reasons = $service->blockers(Fee::find($onPlan->id), $far);
    check('a fee on an active plan refuses', $reasons !== []);
    check('and names the plan',
        (bool) array_filter($reasons, fn ($r) => str_contains($r, (string) $onPlan->payment_plan_id)),
        implode(' ', $reasons));
}

if (!$resit) {
    skip('no resit fee');
} else {
    check('a resit fee refuses',
        $service->blockers(Fee::find($resit->id), $far) !== [],
        'resit dates come from the resit configuration');
}

$pending = Fee::whereHas('paymentReceipts', fn ($q) => $q->where('verification_status', 'pending'))->first();

if (!$pending) {
    skip('no fee with a receipt awaiting verification');
} else {
    check('a fee with a receipt mid-flight refuses',
        $service->blockers(Fee::find($pending->id), $far) !== [],
        'the late fee is settled when that receipt is verified');
}

// ---------------------------------------------------------------------------
section('A refusal writes nothing');
// ---------------------------------------------------------------------------
if (!$settled) {
    skip('no settled fee to refuse');
} else {
    DB::beginTransaction();

    try {
        $fee = Fee::find($settled->id);
        $before = (string) $fee->due_date;
        $noteBefore = (string) $fee->note;

        $result = $service->apply($fee, $far, 'should be refused', null);
        $after = Fee::find($settled->id);

        check('apply() reports the refusal', !$result['changed']);
        check('the deadline is untouched', (string) $after->due_date === $before,
            'was ' . $before . ', now ' . $after->due_date);
        check('and no note was appended', (string) $after->note === $noteBefore);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('The late fee, which is the part that is money');
// ---------------------------------------------------------------------------
// This school has no fine bands configured, so one is created here, inside the
// transaction, to exercise the arithmetic at all. The expectation is computed
// from the band by hand - not by calling the thing under test.
if (!$open) {
    skip('no open fee to charge a fine against');
} else {
    DB::beginTransaction();

    try {
        $fee = Fee::with('category')->find($open->id);

        if (!$fee->category) {
            skip('that fee has no category to hang a fine on');
        } else {
            $band = FeesFine::create([
                'start_day' => 1,
                'end_day' => 400,
                'amount' => 5000,
                'type' => '1',
                'status' => '1',
            ]);
            DB::table('fees_category_fees_fine')->insert([
                'fees_category_id' => $fee->category_id,
                'fees_fine_id' => $band->id,
            ]);

            $fee = Fee::with('category.fines')->find($open->id);

            $overdue = Carbon::today()->subDays(30)->format('Y-m-d');
            $future = Carbon::today()->addDays(30)->format('Y-m-d');

            check('a deadline in the future carries no late fee',
                abs($service->fineIfPaidToday($fee, $future)) < 0.005,
                'got ' . $service->fineIfPaidToday($fee, $future));

            check('a flat band 30 days overdue charges the flat amount',
                abs($service->fineIfPaidToday($fee, $overdue) - 5000.0) < 0.005,
                'got ' . $service->fineIfPaidToday($fee, $overdue) . ', expected 5000.00');

            // Outside the band's days: the fine stops applying.
            $band->update(['start_day' => 60, 'end_day' => 90]);
            $fee = Fee::with('category.fines')->find($open->id);

            check('a day count outside the band charges nothing',
                abs($service->fineIfPaidToday($fee, $overdue)) < 0.005,
                'got ' . $service->fineIfPaidToday($fee, $overdue) . ' for 30 days against a 60-90 band');

            // A percentage band, computed by hand from the fee's own amount.
            $band->update(['start_day' => 1, 'end_day' => 400, 'type' => '2', 'amount' => 10]);
            $fee = Fee::with('category.fines')->find($open->id);
            $expected = round(((float) $fee->fee_amount / 100) * 10, 2);

            check('a percentage band charges that percentage of the fee',
                abs($service->fineIfPaidToday($fee, $overdue) - $expected) < 0.01,
                'got ' . $service->fineIfPaidToday($fee, $overdue) . ', expected ' . $expected);

            // And the whole point: moving the deadline changes it.
            $fee->due_date = $overdue;
            $fee->save();
            $fee = Fee::with('category.fines')->find($open->id);

            $result = $service->apply($fee, $future, 'moved past today', null);

            check('moving the deadline past today clears the late fee',
                $result['changed']
                    && $result['fine_before'] > 0.005
                    && abs($result['fine_after']) < 0.005,
                'before ' . $result['fine_before'] . ', after ' . $result['fine_after']
                    . ' ' . implode(' ', $result['reasons']));
        }
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Nothing else moves when a deadline does');
// ---------------------------------------------------------------------------
if (!$open) {
    skip('no open fee');
} else {
    DB::beginTransaction();

    try {
        $before = DB::table('fees')->where('id', $open->id)
            ->select('fee_amount', 'paid_amount', 'fine_amount', 'discount_amount', 'status', 'pay_date')
            ->first();
        $receiptsBefore = DB::table('payment_receipts')->where('fee_id', $open->id)->count();

        $fee = Fee::find($open->id);
        $service->apply($fee, Carbon::parse($fee->due_date)->addDays(10)->format('Y-m-d'), 'a routine correction', null);

        $after = DB::table('fees')->where('id', $open->id)
            ->select('fee_amount', 'paid_amount', 'fine_amount', 'discount_amount', 'status', 'pay_date')
            ->first();

        check('the amount is untouched', (float) $before->fee_amount === (float) $after->fee_amount);
        check('what was paid is untouched', (float) $before->paid_amount === (float) $after->paid_amount);
        check('the fine already charged is untouched',
            (float) $before->fine_amount === (float) $after->fine_amount,
            'a fine charged at the till stays charged; this only decides the next one');
        check('the discount is untouched', (float) $before->discount_amount === (float) $after->discount_amount);
        check('the status is untouched', (string) $before->status === (string) $after->status);
        check('no receipt was created or removed',
            DB::table('payment_receipts')->where('fee_id', $open->id)->count() === $receiptsBefore);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A correction does not change what the row IS');
// ---------------------------------------------------------------------------
// deriveOrigin() reads the fee's note. The bursar writes the reason in their
// own words, and a reason mentioning a transfer used to reclassify the row -
// which also hid the very buttons that made the correction.
if (!$open) {
    skip('no open fee');
} else {
    DB::beginTransaction();

    try {
        $controller = new App\Http\Controllers\Admin\FeeAssignmentsHistoryController();
        $method = new ReflectionMethod($controller, 'deriveOrigin');
        $method->setAccessible(true);

        // The subject needs a note that matches NO branch, otherwise an
        // earlier branch wins before and after and the check cannot tell the
        // difference - which is exactly what it failed to tell the first time.
        $fee = Fee::with('resitRequest')->find($open->id);
        $fee->note = 'Raised at the counter';
        $fee->save();

        $fee = Fee::with('resitRequest')->find($open->id);
        $originBefore = $method->invoke($controller, $fee, collect());

        $service->apply($fee, Carbon::parse($fee->due_date)->addDays(10)->format('Y-m-d'),
            'agreed with the transfer office, see the programme semester fee sheet', null);

        $fee = Fee::with('resitRequest')->find($open->id);
        $originAfter = $method->invoke($controller, $fee, collect());

        check('the origin survives a reason that mentions a transfer',
            $originAfter === $originBefore,
            'was ' . $originBefore . ', now ' . $originAfter);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Through the screens, not just the service');
// ---------------------------------------------------------------------------
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin || !$open) {
    skip('no Super Admin or no open fee to act as');
} else {
    Illuminate\Support\Facades\Auth::guard('web')->login($admin);

    $call = function (string $url, string $method = 'GET', array $data = []) use ($kernel) {
        $request = Illuminate\Http\Request::create($url, $method,
            array_merge($data, ['_token' => csrf_token()]));
        $request->setLaravelSession(app('session.store'));

        return $kernel->handle($request);
    };

    DB::beginTransaction();

    try {
        $fee = Fee::find($open->id);
        $target = Carbon::parse($fee->due_date)->addDays(21)->format('Y-m-d');

        $call('/admin/fee-assignments-history/' . $fee->id . '/due-date', 'POST', [
            'due_date' => $target,
            'reason' => 'the deadline was wrong on the fee structure',
        ]);

        check('the row action moves the deadline',
            Carbon::parse(Fee::find($fee->id)->due_date)->format('Y-m-d') === $target,
            'got ' . Fee::find($fee->id)->due_date);
    } finally {
        DB::rollBack();
    }

    // A refusal must refuse on the server, not only in the modal.
    if (!$settled) {
        skip('no settled fee to refuse through the screen');
    } else {
        DB::beginTransaction();

        try {
            $before = (string) Fee::find($settled->id)->due_date;

            $call('/admin/fee-assignments-history/' . $settled->id . '/due-date', 'POST', [
                'due_date' => $far,
                'reason' => 'should be refused on the server',
            ]);

            check('a settled fee is refused through the screen too',
                (string) Fee::find($settled->id)->due_date === $before,
                'the guard has to hold on the server, not only in the modal');
        } finally {
            DB::rollBack();
        }
    }

    // The cohort action, and the two agreeing.
    DB::beginTransaction();

    try {
        $fee = Fee::find($open->id);
        $target = Carbon::parse($fee->due_date)->addDays(33)->format('Y-m-d');
        $other = Fee::where('id', '!=', $fee->id)->first();
        $otherBefore = (string) $other->due_date;

        $call('/admin/fee-assignments-correct', 'POST', [
            'fees' => [$fee->id],
            'due_date' => $target,
            'reason' => 'the deadline was wrong for the whole intake',
        ]);

        check('the cohort action moves the ticked deadline',
            Carbon::parse(Fee::find($fee->id)->due_date)->format('Y-m-d') === $target,
            'got ' . Fee::find($fee->id)->due_date);

        check('and leaves a fee that was not ticked alone',
            (string) Fee::find($other->id)->due_date === $otherBefore,
            'a cohort tool that moves rows nobody selected is the worst kind');
    } finally {
        DB::rollBack();
    }

    if (!$settled) {
        skip('no blocked fee to compare the two routes on');
    } else {
        DB::beginTransaction();

        try {
            $before = (string) Fee::find($settled->id)->due_date;

            $call('/admin/fee-assignments-history/' . $settled->id . '/due-date', 'POST',
                ['due_date' => $far, 'reason' => 'refused on both routes']);
            $afterRow = (string) Fee::find($settled->id)->due_date;

            $call('/admin/fee-assignments-correct', 'POST',
                ['fees' => [$settled->id], 'due_date' => $far, 'reason' => 'refused on both routes']);
            $afterCohort = (string) Fee::find($settled->id)->due_date;

            check('a fee blocked one way is blocked the other way too',
                $afterRow === $before && $afterCohort === $before,
                'row left it at ' . $afterRow . ', cohort at ' . $afterCohort
                    . ' — one rule reached two ways must give one answer');
        } finally {
            DB::rollBack();
        }
    }

    // Correcting only the amount must not quietly move the deadline, and the
    // other way round. The two travel together on the cohort screen.
    DB::beginTransaction();

    try {
        $fee = Fee::find($open->id);
        $dueBefore = (string) $fee->due_date;

        $call('/admin/fee-assignments-correct', 'POST', [
            'fees' => [$fee->id],
            'fee_amount' => (float) $fee->fee_amount + 1000,
            'reason' => 'amount only, no deadline given',
        ]);

        check('correcting only the amount leaves the deadline alone',
            (string) Fee::find($fee->id)->due_date === $dueBefore,
            'was ' . $dueBefore . ', now ' . Fee::find($fee->id)->due_date);
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        $fee = Fee::find($open->id);
        $amountBefore = (float) $fee->fee_amount;

        $call('/admin/fee-assignments-correct', 'POST', [
            'fees' => [$fee->id],
            'due_date' => Carbon::parse($fee->due_date)->addDays(7)->format('Y-m-d'),
            'reason' => 'deadline only, no amount given',
        ]);

        check('correcting only the deadline leaves the amount alone',
            abs((float) Fee::find($fee->id)->fee_amount - $amountBefore) < 0.005,
            'was ' . $amountBefore . ', now ' . Fee::find($fee->id)->fee_amount);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('The button on the screen can actually work');
// ---------------------------------------------------------------------------
// The modal is opened with jQuery, and Bootstrap's jQuery plugin is loaded in
// the footer. A handler that runs before it gets "$(...).modal is not a
// function" and the button does nothing at all - which is exactly what
// happened with the amount button.
if (!isset($admin) || !$admin) {
    skip('no Super Admin to render the screen as');
} else {
    $request = Illuminate\Http\Request::create('/admin/fee-assignments-history', 'GET');
    $request->setLaravelSession(app('session.store'));
    $page = $kernel->handle($request)->getContent();

    check('the deadline button is on the page',
        str_contains($page, 'btn-correct-due-date'));
    check('and the modal it opens is too',
        str_contains($page, 'correctDueDateModal'));

    $bootstrap = strpos($page, 'bootstrap.min.js');
    $handler = strpos($page, "btn-correct-due-date'");

    check('the handler runs after Bootstrap is loaded',
        $bootstrap !== false && $handler !== false && $handler > $bootstrap,
        'before Bootstrap, $(...).modal is not a function and the button does nothing — '
            . 'bootstrap at ' . var_export($bootstrap, true) . ', handler at ' . var_export($handler, true));

    $cohort = Illuminate\Http\Request::create('/admin/fee-assignments-correct', 'GET');
    $cohort->setLaravelSession(app('session.store'));

    check('the cohort screen offers a deadline too',
        str_contains($kernel->handle($cohort)->getContent(), 'new_due_date'));
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('no fee carries a deadline-correction note',
    Fee::where('note', 'like', '%' . FeeAssignmentDueDate::MARKER . '%')->count() === 0,
    Fee::where('note', 'like', '%' . FeeAssignmentDueDate::MARKER . '%')->count() . ' do');

check('no stray fine band was left behind',
    FeesFine::count() === 0,
    FeesFine::count() . ' exist — the suite creates one inside a transaction and rolls it back');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
