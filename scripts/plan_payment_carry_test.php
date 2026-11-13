<?php
/**
 * Paying a payment plan the way a student actually pays.
 *
 * Two things are proved here.
 *
 * First, that a fee on a plan can be paid from the fees report, through the
 * plan's own modal, without opening the plan: the report carries the modal,
 * aimed at the earliest instalment that still has a balance, with the
 * schedule in it.
 *
 * Second, that money beyond one instalment is carried, in the order the
 * student owes it — this instalment, the plan's later instalments, then the
 * student's other instalment fees — with every leg recorded exactly as its
 * own screen records it, and that money beyond everything owed is refused
 * rather than handed over without reaching the books.
 *
 * Everything is built and paid inside transactions that are rolled back.
 *
 * Usage: php scripts/plan_payment_carry_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\PaymentPlanController;
use App\Models\Fee;
use App\Models\PaymentAccount;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanPayment;
use App\Models\PaymentReceipt;
use App\Services\FeePaymentRecorder;
use App\Services\PlanPaymentDistribution;
use App\Support\FeesPaymentMethod;
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

$admin = User::whereHas('roles')->first() ?: User::first();
session()->start();
Auth::guard('web')->login($admin);

$distributor = app(PlanPaymentDistribution::class);
$recorder = app(FeePaymentRecorder::class);
$controller = app(PaymentPlanController::class);
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

/**
 * A first-instalment fee nothing has been paid on, whose student also owes a
 * second instalment that is not on a plan: the case where a payment can run
 * off the end of the plan and onto another fee.
 */
$first = Fee::with(['category', 'studentEnroll.student'])
    ->whereHas('studentEnroll.student')
    ->whereHas('category', fn ($q) => $q->where('is_first_installment', 1))
    ->where('paid_amount', 0)->where('status', '0')->whereNull('payment_plan_id')
    ->orderByDesc('id')->get()
    ->first(function (Fee $fee) use ($recorder) {
        if ($recorder->outstanding($fee) < 30000) {
            return false;
        }
        return app(\App\Services\FeePaymentDistribution::class)->targets($fee)->count() > 1;
    });

if (!$first) {
    echo "No unpaid first instalment with another instalment fee behind it to test on.\n";
    exit(1);
}

$studentId = (int) $first->studentEnroll->student_id;
$feeDue = round($recorder->outstanding($first), 2);
$others = app(\App\Services\FeePaymentDistribution::class)->targets($first)->slice(1)->values();
$second = $others->first();
$secondDue = round($recorder->outstanding($second), 2);
$othersDue = round($others->sum(fn ($f) => $recorder->outstanding($f)), 2);

/** A three-instalment plan on the fee, as the create screen builds one. */
$makePlan = function () use ($first, $studentId, $feeDue, $admin): PaymentPlan {
    $a = round($feeDue / 3, 2);
    $amounts = [$a, $a, round($feeDue - 2 * $a, 2)];

    $plan = PaymentPlan::create([
        'student_id' => $studentId, 'fee_id' => $first->id, 'total_amount' => $feeDue,
        'installments_count' => 3, 'late_fee_percentage' => 0, 'grace_period_days' => 7,
        'created_by' => $admin->id, 'approved_by' => $admin->id, 'approved_at' => now(),
        'status' => 'active', 'notes' => 'ZZ carry suite',
    ]);

    foreach ($amounts as $i => $amount) {
        PaymentPlanInstallment::create([
            'payment_plan_id' => $plan->id, 'installment_number' => $i + 1, 'amount' => $amount,
            'due_date' => now()->addDays(30 * ($i + 1))->toDateString(),
            'grace_period_ends' => now()->addDays(30 * ($i + 1) + 7)->toDateString(),
            'status' => 'pending',
        ]);
    }

    Fee::whereKey($first->id)->update(['payment_plan_id' => $plan->id]);

    return $plan->fresh(['installments', 'fee']);
};

/** A payment account to pay into, made inside the transaction if there is none. */
$account = function (): PaymentAccount {
    if ($existing = PaymentAccount::where('status', 1)->first()) {
        return $existing;
    }

    $typeId = DB::table('payment_account_types')->value('id');
    if (!$typeId) {
        $columns = Schema::getColumnListing('payment_account_types');
        $row = ['created_at' => now(), 'updated_at' => now()];
        foreach (['title', 'name'] as $label) {
            if (in_array($label, $columns, true)) { $row[$label] = 'ZZ Type'; }
        }
        if (in_array('slug', $columns, true)) { $row['slug'] = 'zz-type-' . uniqid(); }
        if (in_array('status', $columns, true)) { $row['status'] = 1; }
        $typeId = DB::table('payment_account_types')->insertGetId($row);
    }

    return PaymentAccount::create([
        'title' => 'ZZ Carry Suite Account', 'account_number' => 'ZZ-' . uniqid(),
        'account_type_id' => $typeId, 'opening_balance' => 0, 'current_balance' => 0,
        'status' => 1, 'created_by' => Auth::guard('web')->id(),
    ]);
};

$pay = function (PaymentPlan $plan, PaymentPlanInstallment $installment, float $amount, array $extra = []) use ($controller) {
    return $controller->processPayment(Request::create('/x', 'POST', array_merge([
        'payment_plan_id' => $plan->id, 'installment_id' => $installment->id,
        'amount' => $amount, 'payment_method' => 3, 'payment_date' => date('Y-m-d'),
        'note' => 'ZZ carry suite',
    ], $extra)));
};

$inst = fn (PaymentPlan $plan, int $number) => PaymentPlanInstallment::where('payment_plan_id', $plan->id)
    ->where('installment_number', $number)->first();
$planPaid = fn (PaymentPlan $plan) => round((float) PaymentPlanPayment::whereIn('installment_id',
    PaymentPlanInstallment::where('payment_plan_id', $plan->id)->pluck('id'))->where('status', 'active')->sum('amount'), 2);
$get = function (string $path) use ($kernel) {
    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));
    return $kernel->handle($request);
};

echo "\n== Ground ==\n";
check('a plan fee with another instalment fee behind it', $second !== null,
    $first->category->title . ' ' . number_format($feeDue) . ', then ' . optional($second->category)->title . ' ' . number_format($secondDue));

echo "\n== How the money would be spread ==\n";

DB::beginTransaction();
try {
    $plan = $makePlan();
    [$i1, $i2, $i3] = [$inst($plan, 1), $inst($plan, 2), $inst($plan, 3)];

    check('the next instalment to pay is the first with a balance', (int) $distributor->nextInstallment($plan)->id === (int) $i1->id);

    $within = $distributor->propose($i1, (float) $i1->amount - 1000);
    check('less than the instalment stays on the instalment', count($within['legs']) === 1 && $within['legs'][0]['is_origin'] && $within['leftover'] == 0.0);

    $spill = $distributor->propose($i1, (float) $i1->amount + 5000);
    check('more than the instalment is carried onto the next one', count($spill['legs']) === 2
        && (int) $spill['legs'][1]['id'] === (int) $i2->id && abs($spill['legs'][1]['applied'] - 5000) < 0.005 && $spill['leftover'] == 0.0);

    $beyondPlan = $distributor->propose($i1, $feeDue + 10000);
    $types = array_column($beyondPlan['legs'], 'type');
    check('more than the whole plan settles all three instalments', array_slice($types, 0, 3) === ['installment', 'installment', 'installment']
        && abs(array_sum(array_column(array_slice($beyondPlan['legs'], 0, 3), 'applied')) - $feeDue) < 0.005);
    check('and the rest goes to the student\'s next instalment fee', ($beyondPlan['legs'][3]['type'] ?? '') === 'fee'
        && (int) $beyondPlan['legs'][3]['id'] === (int) $second->id && abs($beyondPlan['legs'][3]['applied'] - 10000) < 0.005);
    check('the plan\'s own fee is never offered as a second line', !collect($beyondPlan['legs'])->contains(fn ($l) => $l['type'] === 'fee' && (int) $l['id'] === (int) $first->id));
    check('what the student owes is the plan plus the other instalment fees', abs($beyondPlan['owed'] - ($feeDue + $othersDue)) < 0.01,
        number_format($beyondPlan['owed'], 2) . ' vs ' . number_format($feeDue + $othersDue, 2));

    $over = $distributor->propose($i1, $feeDue + $othersDue + 50000);
    check('money beyond everything owed is reported as leftover', abs($over['leftover'] - 50000) < 0.005, 'leftover ' . $over['leftover']);
    check('and such a spread cannot be recorded', $distributor->blockers($i1, $over['legs'], $feeDue + $othersDue + 50000) !== []);

    // Opened on the second instalment: that one first, then the others by number.
    $fromSecond = $distributor->propose($i2, (float) $i2->amount + (float) $i1->amount);
    check('the instalment that was opened is filled first', (int) $fromSecond['legs'][0]['id'] === (int) $i2->id
        && (int) $fromSecond['legs'][1]['id'] === (int) $i1->id);

    // The server's answer to the browser.
    $json = json_decode($get('/admin/payment-plan/installment/' . $i1->id . '/split?amount=' . ((float) $i1->amount + 5000))->getContent(), true);
    check('the split endpoint answers with the same legs', is_array($json) && count($json['legs'] ?? []) === 2
        && abs(($json['legs'][1]['applied'] ?? 0) - 5000) < 0.005 && abs(($json['outstanding'] ?? 0) - (float) $i1->amount) < 0.005,
        json_encode($json));
} finally {
    DB::rollBack();
}

echo "\n== One instalment, paid as before ==\n";

DB::beginTransaction();
try {
    $plan = $makePlan();
    $i1 = $inst($plan, 1);
    $response = $pay($plan, $i1, (float) $i1->amount);

    check('exactly the instalment settles that instalment', $inst($plan, 1)->status === 'paid' && $inst($plan, 2)->status === 'pending');
    check('with one payment on record', PaymentPlanPayment::whereIn('installment_id', $plan->installments->pluck('id'))->count() === 1);
    check('the fee carries the money', abs((float) Fee::find($first->id)->paid_amount - (float) $i1->amount) < 0.005);
    check('from the plan\'s page it returns to the plan', str_contains($response->getTargetUrl(), '/admin/payment-plan/' . $plan->id));
} finally {
    DB::rollBack();
}

echo "\n== More than the instalment, carried along the plan ==\n";

DB::beginTransaction();
try {
    $plan = $makePlan();
    [$i1, $i2, $i3] = [$inst($plan, 1), $inst($plan, 2), $inst($plan, 3)];
    $acct = $account();
    $before = (float) $acct->current_balance;
    $statement = (float) DB::table('transactions')->sum('amount');
    $handed = round((float) $i1->amount + (float) $i2->amount + 2000, 2);

    // No allocation sent: the server proposes, as it does when the bursar
    // changes nothing in the table.
    $pay($plan, $i1, $handed, ['payment_account_id' => $acct->id]);

    check('the first instalment is settled', $inst($plan, 1)->status === 'paid');
    check('the second is settled by the same handover', $inst($plan, 2)->status === 'paid');
    check('and the third takes the remainder as a part payment', $inst($plan, 3)->status === 'partial' && abs((float) $inst($plan, 3)->paid_amount - 2000) < 0.005);
    check('one payment row per instalment touched', PaymentPlanPayment::whereIn('installment_id', $plan->installments->pluck('id'))->count() === 3);
    check('the plan payments add up to what was handed over', abs($planPaid($plan) - $handed) < 0.005, $planPaid($plan) . ' vs ' . $handed);
    check('the fee carries all of it', abs((float) Fee::find($first->id)->paid_amount - $handed) < 0.005);
    check('the fee is partly paid and still on its plan', (int) Fee::find($first->id)->status === 2 && (int) Fee::find($first->id)->payment_plan_id === (int) $plan->id);
    check('the plan is still active', PaymentPlan::find($plan->id)->status === 'active');
    check('the payment account received every franc', abs((float) $acct->fresh()->current_balance - $before - $handed) < 0.005);
    check('and so did the student\'s statement', abs((float) DB::table('transactions')->sum('amount') - $statement - $handed) < 0.005);
    check('nothing reached another fee', abs((float) Fee::find($second->id)->paid_amount - (float) $second->paid_amount) < 0.005);
} finally {
    DB::rollBack();
}

echo "\n== More than the whole plan, carried onto the next fee ==\n";

DB::beginTransaction();
try {
    $plan = $makePlan();
    $i1 = $inst($plan, 1);
    $acct = $account();
    $before = (float) $acct->current_balance;
    $extra = min(25000, $secondDue);
    $handed = round($feeDue + $extra, 2);
    $secondPaidBefore = (float) $second->paid_amount;

    $pay($plan, $i1, $handed, ['payment_account_id' => $acct->id]);

    check('every instalment is settled', PaymentPlanInstallment::where('payment_plan_id', $plan->id)->where('status', '!=', 'paid')->count() === 0);
    check('the plan is completed', PaymentPlan::find($plan->id)->status === 'completed');
    $fee = Fee::find($first->id);
    check('the plan\'s fee is fully paid and off the plan', (int) $fee->status === 1 && $fee->payment_plan_id === null && abs((float) $fee->paid_amount - $feeDue) < 0.005);
    check('it is not overpaid', (float) $fee->paid_amount <= (float) $fee->total_amount + 0.005);

    $secondNow = Fee::find($second->id);
    check('the rest landed on the next instalment fee', abs((float) $secondNow->paid_amount - $secondPaidBefore - $extra) < 0.005,
        'paid ' . $secondNow->paid_amount . ', expected ' . ($secondPaidBefore + $extra));
    $receipt = PaymentReceipt::where('fee_id', $second->id)->orderByDesc('id')->first();
    check('with a receipt of its own, as the fee screen writes one', $receipt && abs((float) $receipt->amount - $extra) < 0.005 && $receipt->verification_status === 'approved');
    check('in the fee\'s numbering for Bank Transfer, not the plan\'s', $receipt && (int) $receipt->payment_method === 4 && (int) $secondNow->payment_method === 4,
        'receipt ' . optional($receipt)->payment_method . ', fee ' . $secondNow->payment_method);
    check('which both screens read as the same words', FeesPaymentMethod::label(4) === __('payment_method_bank'));
    check('plan payments and fee receipt together are what was handed over', abs($planPaid($plan) + (float) optional($receipt)->amount - $handed) < 0.005);
    check('the payment account received every franc', abs((float) $acct->fresh()->current_balance - $before - $handed) < 0.005);
} finally {
    DB::rollBack();
}

echo "\n== The bursar moves money between the lines ==\n";

DB::beginTransaction();
try {
    $plan = $makePlan();
    [$i1, $i2, $i3] = [$inst($plan, 1), $inst($plan, 2), $inst($plan, 3)];
    $handed = round((float) $i1->amount + 6000, 2);

    // Less on the opened instalment, more on the third: their decision.
    $pay($plan, $i1, $handed, ['allocation' => [
        ['type' => 'installment', 'id' => $i1->id, 'applied' => (float) $i1->amount - 4000],
        ['type' => 'installment', 'id' => $i3->id, 'applied' => 10000],
    ]]);

    check('the split is recorded as they set it', abs((float) $inst($plan, 1)->paid_amount - ((float) $i1->amount - 4000)) < 0.005
        && abs((float) $inst($plan, 3)->paid_amount - 10000) < 0.005 && (float) $inst($plan, 2)->paid_amount == 0.0);
    check('and still adds up', abs($planPaid($plan) - $handed) < 0.005);
} finally {
    DB::rollBack();
}

echo "\n== What must be refused, with nothing written ==\n";

$refused = function (string $label, callable $attempt) use ($makePlan, $inst, $planPaid, $first, $second) {
    DB::beginTransaction();
    try {
        $plan = $makePlan();
        $secondBefore = (float) Fee::find($second->id)->paid_amount;
        $credits = DB::table('student_credits')->count();
        $attempt($plan);
        check($label, $planPaid($plan) == 0.0
            && (float) Fee::find($first->id)->paid_amount == 0.0
            && abs((float) Fee::find($second->id)->paid_amount - $secondBefore) < 0.005
            && DB::table('student_credits')->count() === $credits
            && PaymentPlan::find($plan->id)->status === 'active',
            'plan paid ' . $planPaid($plan));
    } finally {
        DB::rollBack();
    }
};

$refused('money beyond everything the student owes', fn ($plan) => $pay($plan, $inst($plan, 1), $feeDue + $othersDue + 50000));

$refused('a split that comes to more than was handed over', fn ($plan) => $pay($plan, $inst($plan, 1), 20000, ['allocation' => [
    ['type' => 'installment', 'id' => $inst($plan, 1)->id, 'applied' => 15000],
    ['type' => 'installment', 'id' => $inst($plan, 2)->id, 'applied' => 15000],
]]));

$refused('a split that leaves money on no line', fn ($plan) => $pay($plan, $inst($plan, 1), 20000, ['allocation' => [
    ['type' => 'installment', 'id' => $inst($plan, 1)->id, 'applied' => 15000],
]]));

$refused('more on one instalment than it needs', fn ($plan) => $pay($plan, $inst($plan, 1), (float) $inst($plan, 1)->amount + 5000, ['allocation' => [
    ['type' => 'installment', 'id' => $inst($plan, 1)->id, 'applied' => (float) $inst($plan, 1)->amount + 5000],
]]));

$refused('the same line twice', fn ($plan) => $pay($plan, $inst($plan, 1), 20000, ['allocation' => [
    ['type' => 'installment', 'id' => $inst($plan, 1)->id, 'applied' => 10000],
    ['type' => 'installment', 'id' => $inst($plan, 1)->id, 'applied' => 10000],
]]));

$foreignInstalment = PaymentPlanInstallment::whereHas('paymentPlan', fn ($q) => $q->where('status', 'active'))->orderBy('id')->first();
if ($foreignInstalment) {
    $refused('an instalment of somebody else\'s plan', fn ($plan) => $pay($plan, $inst($plan, 1), 20000, ['allocation' => [
        ['type' => 'installment', 'id' => $inst($plan, 1)->id, 'applied' => 10000],
        ['type' => 'installment', 'id' => $foreignInstalment->id, 'applied' => 10000],
    ]]));
}

$foreignFee = Fee::whereHas('studentEnroll', fn ($q) => $q->where('student_id', '!=', $studentId))
    ->where('status', '0')->whereNull('payment_plan_id')->first();
if ($foreignFee) {
    $refused('another student\'s fee', fn ($plan) => $pay($plan, $inst($plan, 1), 20000, ['allocation' => [
        ['type' => 'installment', 'id' => $inst($plan, 1)->id, 'applied' => 10000],
        ['type' => 'fee', 'id' => $foreignFee->id, 'applied' => 10000],
    ]]));
}

$refused('the plan\'s own fee as a direct line', fn ($plan) => $pay($plan, $inst($plan, 1), 20000, ['allocation' => [
    ['type' => 'installment', 'id' => $inst($plan, 1)->id, 'applied' => 10000],
    ['type' => 'fee', 'id' => $first->id, 'applied' => 10000],
]]));

DB::beginTransaction();
try {
    $plan = $makePlan();
    PaymentPlan::whereKey($plan->id)->update(['status' => 'cancelled']);
    $pay($plan, $inst($plan, 1), 10000);
    check('a plan that is no longer active', $planPaid($plan) == 0.0);
} finally {
    DB::rollBack();
}

echo "\n== From the fees report ==\n";

DB::beginTransaction();
try {
    $plan = $makePlan();
    [$i1, $i2] = [$inst($plan, 1), $inst($plan, 2)];

    // Instalment 1 already settled: the report must aim at instalment 2.
    $pay($plan, $i1, (float) $i1->amount);

    $html = $get('/admin/fees-student-report?faculty=0&program=0&session=0&semester=0&section=0&payment_status%5B%5D=4&student_id=' . urlencode($first->studentEnroll->student->student_id))->getContent();

    check('the report renders with the plan fee on it', str_contains($html, 'data-bs-target="#planPayModal-' . $i2->id . '"'),
        'no pay button aimed at instalment ' . $i2->id);
    check('the modal is on the page, aimed at the next unpaid instalment', str_contains($html, 'id="planPayModal-' . $i2->id . '"')
        && !str_contains($html, 'id="planPayModal-' . $i1->id . '"'));
    check('it posts to the plan, for that instalment', preg_match('/id="pp-form-' . $i2->id . '".*?name="installment_id" value="' . $i2->id . '"/s', $html) === 1
        && str_contains($html, 'name="payment_plan_id" value="' . $plan->id . '"'));
    check('and says it came from the report', preg_match('/id="pp-form-' . $i2->id . '".*?name="return_to" value="report"/s', $html) === 1);
    check('it shows the whole schedule', preg_match_all('/<tr class="[^"]*" data-installment="\d+">/', $html) === 3
        && str_contains($html, __('this instalment')));
    check('with the instalment already paid marked so', preg_match('/data-installment="' . $i1->id . '">.*?badge-success/s', $html) === 1);
    check('the amount starts at this instalment\'s balance', preg_match('/id="pp-amount-' . $i2->id . '"\s+value="' . preg_quote(number_format((float) $i2->amount, 2, '.', ''), '/') . '"/', $html) === 1);
    check('and is not capped at it', preg_match('/id="pp-amount-' . $i2->id . '"[^>]*max=/', $html) === 0);
    check('it asks the server how to spread the money', str_contains($html, json_encode(route('admin.payment-plan.split', $i2->id))));
    check('the way to the full plan is still there', str_contains($html, route('admin.payment-plan.show', $plan->id)));

    // No two elements may share an id: this modal sits beside fee modals.
    preg_match_all('/\sid="([^"]+)"/', $html, $ids);
    $dupes = array_keys(array_filter(array_count_values($ids[1]), fn ($n) => $n > 1));
    $ours = array_values(array_filter($dupes, fn ($id) => str_starts_with($id, 'pp-') || str_starts_with($id, 'planPay')));
    check('none of the modal\'s ids collide with anything on the page', $ours === [], implode(', ', array_slice($ours, 0, 5)));

    // Paying from there ends on the fee's receipt, as an ordinary fee does.
    $response = $pay($plan, $i2, (float) $i2->amount + 3000, ['return_to' => 'report']);
    check('paid from the report, it goes to the fee\'s receipt', str_contains($response->getTargetUrl(), '/admin/fees-student-print/' . $first->id), $response->getTargetUrl());
    check('the second instalment is settled and the third started', $inst($plan, 2)->status === 'paid' && abs((float) $inst($plan, 3)->paid_amount - 3000) < 0.005);

    $receipt = $get('/admin/fees-student-print/' . $first->id);
    check('and that receipt renders', $receipt->getStatusCode() === 200, 'status ' . $receipt->getStatusCode());

    $html = $get('/admin/fees-student-report?faculty=0&program=0&session=0&semester=0&section=0&payment_status%5B%5D=4&student_id=' . urlencode($first->studentEnroll->student->student_id))->getContent();
    check('back on the report, the modal has moved on to the third instalment', str_contains($html, 'id="planPayModal-' . $inst($plan, 3)->id . '"'));
} finally {
    DB::rollBack();
}

echo "\n== The plan's own page ==\n";

DB::beginTransaction();
try {
    $plan = $makePlan();
    $html = $get('/admin/payment-plan/' . $plan->id)->getContent();
    check('the plan page renders', str_contains($html, 'planPayModal-'));
    check('with a modal for each unpaid instalment', substr_count($html, 'class="modal fade" tabindex="-1" role="dialog" aria-labelledby="planPayLabel-') === 3);
    check('each going back to the plan', substr_count($html, 'name="return_to" value="plan"') === 3);
    check('and its buttons open them', substr_count($html, 'data-bs-target="#planPayModal-') === 3 && !str_contains($html, 'data-bs-target="#payModal-'));
} finally {
    DB::rollBack();
}

echo "\n== Nothing left behind ==\n";
check('no suite plan survived', PaymentPlan::where('notes', 'ZZ carry suite')->doesntExist());
check('the fee is as it was', (float) Fee::find($first->id)->paid_amount == 0.0 && Fee::find($first->id)->payment_plan_id === null);
check('no suite account survived', PaymentAccount::where('title', 'ZZ Carry Suite Account')->doesntExist());
check('no transaction left open', DB::transactionLevel() === 0);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
