<?php
/**
 * Payment plan agreements, end to end.
 *
 * The bursar prepares one agreement for a student's first and second
 * instalment together. It is printed for the bursar's and the student's
 * signatures and taken to the Vice Chancellor, who approves it here. Until
 * then, no money can reach the fees it covers by any route. An approved
 * agreement that changes — revised by the bursar, or because one of its fees
 * changed underneath it — goes back for approval, saying exactly what changed.
 *
 * Driven through the real routes, with the real permissions: the Vice
 * Chancellor approves as the VC account, and someone without the approve
 * permission is turned away. Everything happens inside transactions that are
 * rolled back.
 *
 * Usage: php scripts/payment_plan_agreement_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\FeesStudentController;
use App\Http\Controllers\Admin\PaymentPlanController;
use App\Models\Fee;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanAgreement;
use App\Models\PaymentPlanAgreementEvent;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanPayment;
use App\Models\PaymentReceipt;
use App\Models\Student;
use App\Services\FeeAssignmentAmount;
use App\Services\PaymentPlanAgreements;
use App\Services\PlanPaymentDistribution;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

app('session.store')->start();
$bursar = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
$vc = User::whereHas('roles', fn ($q) => $q->where('name', 'VC'))->where('status', 1)->first();
$outsider = User::where('status', 1)->get()->first(fn ($u) => !$u->can('payment-plan.approve') && $u->id !== optional($bursar)->id);

$service = app(PaymentPlanAgreements::class);
$plans = app(PaymentPlanController::class);
$fees = app(FeesStudentController::class);

$as = function (User $user) {
    Auth::guard('web')->login($user);
};
$http = function (string $method, string $path, array $data = []) use ($kernel) {
    $request = Request::create($path, $method, $data + ($method === 'POST' ? ['_token' => csrf_token()] : []));
    $request->setLaravelSession(app('session.store'));
    return $kernel->handle($request);
};
$location = fn ($response) => method_exists($response, 'getTargetUrl') ? $response->getTargetUrl() : '';

echo "\n== Ground ==\n";
check('agreements and their history have tables', Schema::hasTable('payment_plan_agreements') && Schema::hasTable('payment_plan_agreement_events'));
check('a plan belongs to an agreement', Schema::hasColumn('payment_plans', 'agreement_id'));
check('every plan that existed was carried over to an agreement', PaymentPlan::whereNull('agreement_id')->doesntExist());
check('and the ones in force were carried over approved', PaymentPlan::whereIn('status', ['active', 'completed'])
    ->whereHas('agreement', fn ($q) => $q->where('status', '!=', 'approved'))->doesntExist());
check('a VC account to approve with', $vc !== null && $vc->can('payment-plan.approve'), optional($vc)->name ?? 'none');
check('and someone without the approve permission', $outsider !== null, optional($outsider)->name ?? 'none');

// A student who owes both a first and a second instalment.
$student = null;
foreach (Student::where('status', 1)->get() as $candidate) {
    $eligible = $service->eligibleFees($candidate->id);
    if ($eligible->contains(fn ($f) => optional($f->category)->is_first_installment)
        && $eligible->contains(fn ($f) => optional($f->category)->is_second_installment)) {
        $student = $candidate;
        break;
    }
}
check('a student who owes both instalments', $student !== null);
if (!$student || !$vc || !$outsider) {
    echo "\n$passed passed, $failed failed\n";
    exit(1);
}

$eligible = $service->eligibleFees($student->id);
$first = $eligible->first(fn ($f) => $f->category->is_first_installment);
$second = $eligible->first(fn ($f) => $f->category->is_second_installment);
$firstDue = $service->balance($first);
$secondDue = $service->balance($second);

/** Both instalments, two payments each, a month apart. */
$schedule = function () use ($first, $second, $firstDue, $secondDue): array {
    $split = fn ($total) => [round($total / 2, 2), round($total - round($total / 2, 2), 2)];
    [$a, $b] = $split($firstDue);
    [$c, $d] = $split($secondDue);
    return [
        $first->id => ['include' => 1, 'installments' => [
            ['amount' => $a, 'due_date' => now()->addMonths(1)->toDateString()],
            ['amount' => $b, 'due_date' => now()->addMonths(2)->toDateString()],
        ]],
        $second->id => ['include' => 1, 'installments' => [
            ['amount' => $c, 'due_date' => now()->addMonths(3)->toDateString()],
            ['amount' => $d, 'due_date' => now()->addMonths(4)->toDateString()],
        ]],
    ];
};
$prepare = function () use ($http, $student, $schedule, $as, $bursar) {
    $as($bursar);
    $response = $http('POST', '/admin/payment-plan', ['student_id' => $student->id, 'notes' => 'ZZ agreement suite', 'plans' => $schedule()]);
    return [$response, PaymentPlanAgreement::where('student_id', $student->id)->latest('id')->first()];
};
$payPlan = function (PaymentPlan $plan, float $amount, int $number = 1) use ($plans) {
    $installment = $plan->installments()->where('installment_number', $number)->first();
    return $plans->processPayment(Request::create('/x', 'POST', [
        'payment_plan_id' => $plan->id, 'installment_id' => $installment->id, 'amount' => $amount,
        'payment_method' => 1, 'payment_date' => date('Y-m-d'), 'note' => 'ZZ agreement suite',
    ]));
};
$plansOf = fn (PaymentPlanAgreement $a) => $a->plans()->with('installments', 'fee')->get();
$planFor = fn (PaymentPlanAgreement $a, Fee $fee) => $a->plans()->where('fee_id', $fee->id)->with('installments')->first();

echo "\n== Preparing one agreement for both instalments ==\n";

DB::beginTransaction();
try {
    [$response, $agreement] = $prepare();

    check('the form is accepted and opens the agreement for printing', $agreement && str_contains($location($response), '/payment-plan/agreement/' . $agreement->id . '/print'),
        $location($response));
    check('one agreement, awaiting approval', $agreement && $agreement->status === 'pending' && $agreement->version === 1);
    check('with a reference', (bool) preg_match('/^PPA-\d{4}-\d{4}$/', (string) $agreement->reference), (string) $agreement->reference);
    check('prepared by the bursar', (int) $agreement->created_by === (int) $bursar->id);
    $all = $plansOf($agreement);
    check('a plan for each fee', $all->count() === 2 && $all->pluck('fee_id')->sort()->values()->all() === collect([$first->id, $second->id])->sort()->values()->all());
    check('each awaiting approval, approved by nobody', $all->every(fn ($p) => $p->status === 'pending' && $p->approved_by === null));
    check('each with its own two instalments', $all->every(fn ($p) => $p->installments->count() === 2));
    check('adding up to each fee\'s balance', abs($planFor($agreement, $first)->total_amount - $firstDue) < 0.01 && abs($planFor($agreement, $second)->total_amount - $secondDue) < 0.01);
    check('both fees are held by the agreement', Fee::find($first->id)->hasPendingPaymentPlan() && Fee::find($second->id)->hasPendingPaymentPlan());
    check('the preparation is in its history, with the schedule', ($e = $agreement->events()->first()) && $e->event === 'created' && count($e->snapshot['fees'] ?? []) === 2);

    echo "\n== While it awaits approval, no money reaches those fees ==\n";

    $paidBefore = (float) Fee::find($first->id)->paid_amount;
    $fees->pay(Request::create('/x', 'POST', ['fee_id' => $first->id, 'paid_amount' => 1000, 'pay_date' => date('Y-m-d'), 'payment_method' => '2']));
    check('not directly from the fees screen', abs((float) Fee::find($first->id)->paid_amount - $paidBefore) < 0.005);

    $payPlan($planFor($agreement, $first), 1000);
    check('not through the plan\'s instalments', PaymentPlanPayment::whereIn('installment_id', $planFor($agreement, $first)->installments->pluck('id'))->doesntExist());
    check('the fees report offers nothing to pay on them', app(PlanPaymentDistribution::class)->nextInstallment($planFor($agreement, $first)) === null);

    $html = $http('GET', '/admin/fees-student-report?faculty=0&program=0&session=0&semester=0&section=0&payment_status%5B%5D=4&student_id=' . urlencode($student->student_id))->getContent();
    check('the report marks them awaiting approval, with no pay button', str_contains($html, 'fa-hourglass-half')
        && !str_contains($html, 'data-bs-target="#planPayModal-') && !str_contains($html, 'data-bs-target="#payModal-' . $first->id . '"'));

    $pending = PaymentReceipt::create([
        'fee_id' => $first->id, 'student_id' => $student->id, 'payment_reference' => 'ZZ-AGREE',
        'payment_date' => date('Y-m-d'), 'amount' => 5000, 'payment_method' => 2, 'verification_status' => 'pending',
    ]);
    $http('POST', '/admin/payment-verification/fee/' . $pending->id . '/approve', ['verification_note' => 'ZZ']);
    check('not by approving a receipt the student uploaded', $pending->fresh()->verification_status === 'pending'
        && abs((float) Fee::find($first->id)->paid_amount - $paidBefore) < 0.005, (string) $pending->fresh()->verification_status);
    $pending->delete();

    Auth::guard('student')->login($student);
    $upload = app(\App\Http\Controllers\Student\ManualPaymentController::class)->store(Request::create('/x', 'POST', [
        'fee_id' => $first->id, 'payment_date' => date('Y-m-d'), 'amount' => 5000, 'payment_method' => 2,
    ], [], ['receipt_file' => Illuminate\Http\UploadedFile::fake()->create('r.pdf', 20, 'application/pdf')]));
    check('not by the student uploading a receipt for it', PaymentReceipt::where('fee_id', $first->id)->where('payment_reference', '!=', 'ZZ-AGREE')
        ->where('created_at', '>=', now()->subMinute())->doesntExist());
    $instalmentPage = app(\App\Http\Controllers\Student\InstallmentPaymentController::class)->create($planFor($agreement, $first)->installments->first()->id);
    check('not by the student paying an instalment', method_exists($instalmentPage, 'getTargetUrl'));
    $studentPage = $http('GET', '/student/payment-plan/' . $planFor($agreement, $first)->id)->getContent();
    check('the student is told it awaits the Vice Chancellor', str_contains($studentPage, e(__("Your payment plan agreement is waiting for the Vice Chancellor's approval. You can pay its instalments once it is approved."))));
    check('and offered no pay button', !str_contains($studentPage, route('student.installment-payment.create', $planFor($agreement, $first)->installments->first()->id)));
    Auth::guard('student')->logout();

    $as($bursar);
    check('neither fee can go on a second agreement', !$service->eligibleFees($student->id)->pluck('id')->intersect([$first->id, $second->id])->count());

    $dueDate = app(\App\Services\FeeAssignmentDueDate::class);
    check('a held fee\'s due date cannot be moved under it', method_exists($dueDate, 'blockers')
        ? $dueDate->blockers(Fee::find($first->id), now()->addYear()->toDateString()) !== [] : true);

    echo "\n== The printed agreement ==\n";

    $doc = $http('GET', '/admin/payment-plan/agreement/' . $agreement->id . '/print')->getContent();
    $name = trim($student->first_name . ' ' . $student->last_name);
    check('it prints', str_contains($doc, e(__('Payment Plan Agreement'))) && str_contains($doc, $agreement->reference));
    check('stamped awaiting approval', str_contains($doc, 'class="stamp"') && str_contains($doc, e(__('Awaiting approval'))));
    check('both fees are on it', str_contains($doc, e($first->category->title)) && str_contains($doc, e($second->category->title)));
    check('one schedule of four payments, in date order', preg_match_all('/<tr>\s*<td>\d+<\/td>\s*<td>\d{2} \w{3} \d{4}<\/td>/', $doc) === 4);
    check('with the student\'s undertaking in their name', (bool) preg_match('/id="undertaking">\s*' . preg_quote(e(__('I, :name, agree to pay the amounts above on or before the dates shown. If I miss a payment, the full balance becomes due.', ['name' => $name])), '/') . '/', $doc));
    check('the bursar signs as the one who prepared it', (bool) preg_match('/id="sig-bursar">.*?' . preg_quote(e($bursar->name), '/') . '/s', $doc));
    check('the student signs', (bool) preg_match('/id="sig-student">.*?' . preg_quote(e($name), '/') . '/s', $doc));
    check('and the Vice Chancellor\'s place is left blank to sign', (bool) preg_match('/id="sig-vc">.*?<div class="who-signs"><\/div>/s', $doc));
    preg_match('/verify-plan-agreement\/' . $agreement->id . '\?code=(\w+)/', $doc, $code);
    $firstCode = $code[1] ?? '';
    check('it carries a verification code', $firstCode !== '');
    $verify = fn ($c) => $http('GET', '/verify-plan-agreement/' . $agreement->id . '?code=' . $c)->getContent();
    check('which verifies as genuine', str_contains($verify($firstCode), e(__('This agreement is genuine'))));
    check('and a wrong one does not', str_contains($verify('AAAAAAAAAAAAAAAA'), e(__('This agreement could not be verified'))));

    echo "\n== Only someone with the approve permission can decide ==\n";

    $as($outsider);
    $denied = $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/approve');
    check('someone without it is turned away', in_array($denied->getStatusCode(), [302, 403], true) && $agreement->fresh()->status === 'pending',
        'status ' . $denied->getStatusCode() . ', agreement ' . $agreement->fresh()->status);

    $as($vc);
    $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/approve');
    $agreement->refresh();
    check('the Vice Chancellor approves it', $agreement->status === 'approved' && (int) $agreement->approved_by === (int) $vc->id && $agreement->approved_version === 1);
    check('its plans are now active, approved by the VC', $plansOf($agreement)->every(fn ($p) => $p->status === 'active' && (int) $p->approved_by === (int) $vc->id));

    $as($bursar);
    $doc = $http('GET', '/admin/payment-plan/agreement/' . $agreement->id . '/print')->getContent();
    check('reprinted, it says approved and names the Vice Chancellor', str_contains($doc, 'class="stamp ok"')
        && (bool) preg_match('/id="sig-vc">.*?' . preg_quote(e($vc->name), '/') . '/s', $doc));
    check('and still verifies with the same code: the schedule did not change', str_contains($verify($firstCode), e(__('This agreement is genuine'))));

    echo "\n== Once approved, it is paid like any plan ==\n";

    $firstPlan = $planFor($agreement, $first);
    $secondPlan = $planFor($agreement, $second);
    $payPlan($firstPlan, (float) $firstPlan->installments->first()->amount);
    check('an instalment can be paid', (float) Fee::find($first->id)->paid_amount - $paidBefore >= (float) $firstPlan->installments->first()->amount - 0.005);

    // More than the first instalment's plan needs carries onto the second fee's plan.
    $rest = round((float) $firstPlan->fresh('installments')->installments->sum(fn ($i) => $i->remaining_balance), 2);
    $payPlan($firstPlan->fresh(), $rest + 1000, 2);
    $secondPaid = (float) PaymentPlanInstallment::where('payment_plan_id', $secondPlan->id)->sum('paid_amount');
    check('money beyond one fee\'s plan carries onto the other fee on the agreement', abs($secondPaid - 1000) < 0.005, 'second plan took ' . $secondPaid);
    check('the first plan is complete', $firstPlan->fresh()->status === 'completed');

    echo "\n== Revising sends it back, saying what changed ==\n";

    $openSecond = $secondPlan->fresh('installments')->installments->sortBy('installment_number');
    $toSchedule = round($openSecond->sum(fn ($i) => (float) $i->amount), 2);
    $locked = $service->lockedInstallments($secondPlan->fresh());
    $unlockedTotal = round($toSchedule - $locked->sum(fn ($i) => (float) $i->amount), 2);
    $revise = function (array $rows, string $reason = 'ZZ spread over three months') use ($http, $agreement, $secondPlan) {
        return $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/revise', [
            'reason' => $reason,
            'plans' => [$secondPlan->id => ['present' => 1, 'installments' => $rows]],
        ]);
    };

    $revise([['amount' => $unlockedTotal - 1, 'due_date' => now()->addMonths(5)->toDateString()]]);
    check('a revision that does not add up is refused', $agreement->fresh()->status === 'approved' && $agreement->fresh()->version === 1);

    $third = round($unlockedTotal / 3, 2);
    $response = $revise([
        ['amount' => $third, 'due_date' => now()->addMonths(5)->toDateString()],
        ['amount' => $third, 'due_date' => now()->addMonths(6)->toDateString()],
        ['amount' => round($unlockedTotal - 2 * $third, 2), 'due_date' => now()->addMonths(7)->toDateString()],
    ]);
    $agreement->refresh();
    check('a revision that adds up is saved', $agreement->version === 2, 'version ' . $agreement->version);
    check('and goes back for approval', $agreement->status === 'pending' && $agreement->wasApproved());
    check('opening the new version for printing', str_contains($location($response), '/agreement/' . $agreement->id . '/print'));
    check('the instalment that took money did not move', $locked->every(fn ($i) => PaymentPlanInstallment::whereKey($i->id)
        ->where('amount', $i->amount)->where('paid_amount', $i->paid_amount)->exists()));
    check('the plan still open is held again', $secondPlan->fresh()->status === 'pending' && $firstPlan->fresh()->status === 'completed');
    $paid = PaymentPlanPayment::count();
    $payPlan($secondPlan->fresh(), 100, (int) $secondPlan->fresh('installments')->installments->firstWhere('paid_amount', 0)->installment_number);
    check('no payment is taken while it waits', PaymentPlanPayment::count() === $paid);

    $changes = $service->changesSinceApproval($agreement);
    check('what changed is written down', count($changes) > 0 && collect($changes)->contains(fn ($l) => str_contains($l, $second->category->title) && str_contains($l, 'schedule')),
        implode(' | ', $changes));
    $page = $http('GET', '/admin/payment-plan/agreement/' . $agreement->id)->getContent();
    check('and shown on the agreement', str_contains($page, 'id="changes-since-approval"') && str_contains($page, 'ZZ spread over three months'));
    $doc = $http('GET', '/admin/payment-plan/agreement/' . $agreement->id . '/print')->getContent();
    check('and printed on the new version for the Vice Chancellor', str_contains($doc, 'id="changes"') && str_contains($doc, e(__('Awaiting re-approval')))
        && str_contains($doc, e(__('Revision :n', ['n' => 2]))));
    check('the copy printed before is genuine but out of date', str_contains($verify($firstCode), e(__('Genuine, but out of date'))));

    $as($vc);
    $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/approve');
    check('the Vice Chancellor approves the revision', $agreement->fresh()->status === 'approved' && $agreement->fresh()->approved_version === 2);
    check('and it takes money again', $secondPlan->fresh()->status === 'active');

    echo "\n== A fee changing underneath sends it back ==\n";

    $as($bursar);
    $secondFee = Fee::find($second->id);
    $newAmount = round((float) $secondFee->fee_amount + 25000, 2);
    $amounts = app(FeeAssignmentAmount::class);
    check('raising a fee on an approved agreement is allowed', $amounts->blockers($secondFee, $newAmount) === [], implode(' ', $amounts->blockers($secondFee, $newAmount)));
    $amounts->apply($secondFee, $newAmount, 'ZZ fee corrected', $bursar->id);
    $agreement->refresh();
    check('and sends the agreement back for approval', $agreement->status === 'pending' && $agreement->version === 3);
    $returned = $agreement->events()->reorder('id', 'desc')->first();
    check('saying which fee changed, and from what to what', $returned->event === 'returned'
        && collect($returned->changes)->contains(fn ($l) => str_contains($l, $second->category->title) && str_contains($l, number_format($newAmount, 2))),
        implode(' | ', (array) $returned->changes));
    check('its plan is held: no money until it is approved again', $secondPlan->fresh()->status === 'pending');
    check('it cannot be approved while the schedule falls short of the fee', $service->approvalBlockers($agreement->fresh('plans.installments', 'plans.fee')) !== []);
    $as($vc);
    $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/approve');
    check('so approving it is refused', $agreement->fresh()->status === 'pending');

    $as($bursar);
    $open = $secondPlan->fresh('installments')->installments->whereNotIn('id', $service->lockedInstallments($secondPlan->fresh())->pluck('id'))->sortBy('due_date')->values();
    $rows = $open->map(fn ($i) => ['amount' => (float) $i->amount, 'due_date' => $i->due_date->toDateString()])->all();
    $rows[count($rows) - 1]['amount'] = round($rows[count($rows) - 1]['amount'] + 25000, 2);
    $revise($rows, 'ZZ fee raised by 25,000; added to the last payment');
    check('once the bursar revises it to match', $service->approvalBlockers($agreement->fresh('plans.installments', 'plans.fee')) === []);
    $as($vc);
    $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/approve');
    check('the Vice Chancellor can approve it again', $agreement->fresh()->status === 'approved' && $agreement->fresh()->approved_version === 4);

    // A plan charging its own fee is not a change to the fee.
    PaymentPlanAgreements::quietly(fn () => Fee::find($second->id)->update(['fine_amount' => 500]));
    check('a plan\'s own late fee does not send it back', $agreement->fresh()->status === 'approved');
} finally {
    DB::rollBack();
}

echo "\n== Rejecting and cancelling ==\n";

DB::beginTransaction();
try {
    [, $agreement] = $prepare();
    $as($vc);
    $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/reject', []);
    check('a rejection needs a reason', $agreement->fresh()->status === 'pending');
    $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/reject', ['reason' => 'ZZ the student must pay half first']);
    $agreement->refresh();
    check('rejected, with the reason and who decided', $agreement->status === 'rejected' && (int) $agreement->rejected_by === (int) $vc->id
        && $agreement->rejection_reason === 'ZZ the student must pay half first');
    check('its plans are rejected and its fees released', $plansOf($agreement)->every(fn ($p) => $p->status === 'rejected')
        && Fee::find($first->id)->payment_plan_id === null && Fee::find($second->id)->payment_plan_id === null);
    $as($bursar);
    $before = (float) Fee::find($first->id)->paid_amount;
    $fees->pay(Request::create('/x', 'POST', ['fee_id' => $first->id, 'paid_amount' => 1000, 'pay_date' => date('Y-m-d'), 'payment_method' => '2']));
    check('the fee can be paid in the ordinary way again', abs((float) Fee::find($first->id)->paid_amount - $before - 1000) < 0.005);
    check('and a rejected agreement shows on no receipt', app(\App\Services\FeeReceipt::class)->planFor(Fee::find($first->id)) === null);
} finally {
    DB::rollBack();
}

DB::beginTransaction();
try {
    [, $agreement] = $prepare();
    $as($vc);
    $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/approve');
    $as($bursar);
    $payPlan($planFor($agreement, $first), 2000);
    $http('POST', '/admin/payment-plan/agreement/' . $agreement->id . '/cancel', ['reason' => 'ZZ student withdrew']);
    $agreement->refresh();
    check('an approved agreement can be cancelled', $agreement->status === 'cancelled');
    check('its plans end and release their fees', $plansOf($agreement)->every(fn ($p) => $p->status === 'cancelled')
        && Fee::find($first->id)->payment_plan_id === null);
    check('money already paid stays on the fee', (float) Fee::find($first->id)->paid_amount >= 2000 - 0.005);
} finally {
    DB::rollBack();
}

echo "\n== The older one-fee form still works ==\n";

DB::beginTransaction();
try {
    $as($bursar);
    $plans->store(Request::create('/x', 'POST', [
        'student_id' => $student->id, 'fee_id' => $first->id, 'installments_count' => 1,
        'installments' => [1 => ['amount' => $firstDue, 'due_date' => now()->addMonth()->toDateString()]],
        'notes' => 'ZZ one fee',
    ]));
    $agreement = PaymentPlanAgreement::where('student_id', $student->id)->latest('id')->first();
    check('it prepares an agreement for that fee, awaiting approval', $agreement && $agreement->notes === 'ZZ one fee'
        && $agreement->status === 'pending' && $agreement->plans()->count() === 1);
} finally {
    DB::rollBack();
}

echo "\n== Nothing left behind ==\n";
check('no suite agreement survived', PaymentPlanAgreement::where('notes', 'like', 'ZZ %')->doesntExist());
check('no suite history survived', PaymentPlanAgreementEvent::where('reason', 'like', 'ZZ %')->doesntExist());
check('the fees are as they were', Fee::find($first->id)->payment_plan_id === null && abs((float) Fee::find($first->id)->paid_amount - (float) $first->paid_amount) < 0.005);
check('no transaction left open', DB::transactionLevel() === 0);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
