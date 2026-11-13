<?php
/**
 * A payment plan of one instalment.
 *
 * A student pays part of a fee and agrees to clear the balance on a later
 * date than the fee's own due date. That is a plan with one instalment, and
 * the system refused it: the form, the browser check and the store action all
 * insisted on two. This suite proves one is accepted, that it is for the
 * balance only when the fee is part-paid, and that zero is still refused.
 *
 * Usage: php scripts/payment_plan_single_installment_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\PaymentPlanController;
use App\Models\Fee;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
Auth::guard('web')->login($admin);
$controller = app(PaymentPlanController::class);

$fee = Fee::whereHas('studentEnroll.student')->whereNull('payment_plan_id')
    ->whereIn('status', [0, 2])->orderByDesc('id')->first();
if (!$fee) {
    echo "No fee without a plan to test on.\n";
    exit(1);
}
$studentId = $fee->studentEnroll->student_id;

$store = function (int $count, float $total, array $amounts, ?string $due = null) use ($controller, $fee, $studentId) {
    $installments = [];
    foreach ($amounts as $i => $amount) {
        $installments[$i + 1] = ['amount' => $amount, 'due_date' => $due ?? now()->addDays(60)->toDateString()];
    }
    return $controller->store(Request::create('/x', 'POST', [
        'student_id' => $studentId, 'fee_id' => $fee->id, 'total_amount' => $total,
        'installments_count' => $count, 'grace_period_days' => 7, 'notes' => 'ZZ single',
        'installments' => $installments,
    ]));
};

echo "\n== One instalment, for a fee nothing has been paid on ==\n";

DB::beginTransaction();
try {
    $fee->update(['paid_amount' => 0, 'status' => 0]);
    $balance = (float) $fee->fresh()->remaining_balance;
    $due = now()->addDays(45)->toDateString();

    $response = $store(1, $balance, [$balance], $due);
    $plan = PaymentPlan::where('fee_id', $fee->id)->where('notes', 'ZZ single')->first();

    check('the plan is created', $plan !== null, $response instanceof \Illuminate\Http\RedirectResponse ? $response->getTargetUrl() : '');
    check('with one instalment', $plan && (int) $plan->installments_count === 1 && PaymentPlanInstallment::where('payment_plan_id', $plan->id)->count() === 1);
    $inst = $plan ? PaymentPlanInstallment::where('payment_plan_id', $plan->id)->first() : null;
    check('carrying the whole balance', $inst && abs((float) $inst->amount - $balance) < 0.01);
    check('due on the agreed date', $inst && $inst->due_date->toDateString() === $due);
    check('and the fee is now on the plan', (int) $fee->fresh()->payment_plan_id === (int) ($plan->id ?? 0));
    check('the plan is active', $plan && $plan->status === 'active');
} finally {
    DB::rollBack();
}

echo "\n== One instalment, for a fee that is part-paid ==\n";

DB::beginTransaction();
try {
    $total = (float) $fee->total_amount;
    $paid = round($total * 0.4, 2);
    $fee->update(['paid_amount' => $paid, 'status' => 2]);
    $balance = (float) $fee->fresh()->remaining_balance;
    check('the balance is what remains', abs($balance - ($total - $paid)) < 0.01, number_format($balance) . ' vs ' . number_format($total - $paid));

    // What the create page offers: the fee list carries the remaining balance.
    $list = json_decode($controller->getStudentFees(Request::create('/x', 'GET', ['student_id' => $studentId]))->getContent(), true);
    $offered = collect($list['fees'] ?? [])->firstWhere('id', $fee->id);
    check('the create page offers the fee as partially paid', $offered && $offered['status_label'] === 'Partially Paid');
    check('with the remaining balance to plan', $offered && abs((float) $offered['remaining_balance'] - $balance) < 0.01);

    $store(1, $balance, [$balance]);
    $plan = PaymentPlan::where('fee_id', $fee->id)->where('notes', 'ZZ single')->first();
    check('a one-instalment plan is created for the balance only', $plan && abs((float) $plan->total_amount - $balance) < 0.01);
    $inst = $plan ? PaymentPlanInstallment::where('payment_plan_id', $plan->id)->first() : null;
    check('and its single instalment is that balance', $inst && abs((float) $inst->amount - $balance) < 0.01);
} finally {
    DB::rollBack();
}

echo "\n== What is still refused ==\n";

DB::beginTransaction();
try {
    $fee->update(['paid_amount' => 0, 'status' => 0]);
    $balance = (float) $fee->fresh()->remaining_balance;

    $refused = false;
    try {
        $store(0, $balance, []);
    } catch (ValidationException $e) {
        $refused = isset($e->errors()['installments_count']);
    }
    check('zero instalments', $refused && PaymentPlan::where('notes', 'ZZ single')->doesntExist());

    $refused = false;
    try {
        $store(13, $balance, array_fill(0, 13, round($balance / 13, 2)));
    } catch (ValidationException $e) {
        $refused = isset($e->errors()['installments_count']);
    }
    check('thirteen instalments', $refused && PaymentPlan::where('notes', 'ZZ single')->doesntExist());

    $store(1, $balance, [$balance - 1000]);
    check('one instalment that does not add up to the balance', PaymentPlan::where('notes', 'ZZ single')->doesntExist());
} finally {
    DB::rollBack();
}

echo "\n== The screen ==\n";

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$request = Request::create('/admin/payment-plan/create', 'GET');
$request->setLaravelSession(app('session.store'));
$html = $kernel->handle($request)->getContent();
check('the create page renders', strpos($html, 'id="installments_count"') !== false);
check('the box accepts one', preg_match('/id="installments_count"[^>]*min="1" max="12"/', $html) === 1);
check('the browser check accepts one', strpos($html, 'installmentsCount >= 1 && installmentsCount <= 12') !== false);
check('and says so', strpos($html, 'between 1 and 12') !== false && strpos($html, 'between 2 and 12') === false);
check('and explains what one instalment means', strpos($html, __('One instalment moves the whole balance to a later date.')) !== false);

echo "\n== Nothing left behind ==\n";
check('no suite plan survived', PaymentPlan::where('notes', 'ZZ single')->doesntExist());
check('no transaction left open', DB::transactionLevel() === 0);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
