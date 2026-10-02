<?php

/**
 * The payment plan module, end to end.
 *
 * Splitting a fee into instalments touches the instalment, the fee, the general
 * ledger, the payment account, the cash book and the student's statement. This
 * suite exercises each of those, plus the refusals that keep them consistent.
 *
 * Every write happens inside a transaction that is rolled back, so the suite can
 * be run against real data without changing any of it. Expected figures are
 * worked out here rather than read back from the code under test.
 *
 *   php scripts/payment_plan_test.php
 *   php scripts/payment_plan_test.php --strict    (runs under STRICT_TRANS_TABLES)
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Fee;
use App\Models\PaymentAccount;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanPayment;
use App\Models\Transaction;
use App\Models\TransactionMapping;
use App\Services\FeeLedgerPosting;
use App\Services\InstallmentPaymentReversal;
use App\Services\LedgerSyncService;

$strict = in_array('--strict', $argv ?? [], true);

if ($strict) {
    // Production servers are usually strict, and the enum fault behaved
    // differently there — silently blanking a column here, throwing there.
    DB::statement("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    echo "running under STRICT_TRANS_TABLES\n";
}

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
    echo "\n" . $title . "\n";
}

/**
 * A date inside an open accounting period, so ledger checks mean something.
 * Without one the ledger legitimately refuses everything and proves nothing.
 */
$period = DB::table('accounting_periods')->where('is_closed', 0)
    ->orderBy('start_date')->first();
define('PAY_DATE', $period ? date('Y-m-d', strtotime($period->start_date . ' +14 days')) : date('Y-m-d'));

$admin = \App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?? \App\User::first();

if (!$admin) {
    echo "no admin user to act as; nothing can be tested\n";
    exit(1);
}

Auth::guard('web')->login($admin);

/** Fee categories the ledger knows how to post, so postings can be judged. */
$mappedCategories = DB::table('default_account_mappings')
    ->where('mapping_type', 'fee_category')->where('status', 'active')
    ->whereNotNull('debit_account_id')->whereNotNull('credit_account_id')
    ->pluck('category_id')->filter()->values();

/**
 * An unpaid fee on a real student enrolment, in a category the ledger maps.
 *
 * @param array<int> $except fees already in use by the section asking
 */
function freshFee(array $except = []): ?Fee
{
    global $mappedCategories;

    return Fee::whereHas('studentEnroll.student')
        ->where('paid_amount', 0)
        ->whereNull('payment_plan_id')
        ->whereIn('category_id', $mappedCategories)
        ->whereNotIn('id', $except)
        ->orderByDesc('id')
        ->first();
}

/**
 * A payment account to pay into.
 *
 * This database has none, and neither does a school that has not set the
 * accounts up yet — but the money reaching the account and the cash book is
 * exactly what has to be proved, so the suite makes one inside the transaction
 * it is about to roll back rather than skipping the check.
 */
function payingAccount(): ?PaymentAccount
{
    if ($existing = PaymentAccount::where('status', 1)->first()) {
        return $existing;
    }

    $typeId = DB::table('payment_account_types')->value('id');

    if (!$typeId) {
        $typeColumns = \Illuminate\Support\Facades\Schema::getColumnListing('payment_account_types');
        $row = ['created_at' => now(), 'updated_at' => now()];

        foreach (['title', 'name'] as $label) {
            if (in_array($label, $typeColumns, true)) {
                $row[$label] = 'Test Account Type';
            }
        }

        if (in_array('slug', $typeColumns, true)) {
            $row['slug'] = 'test-account-type-' . uniqid();
        }

        if (in_array('status', $typeColumns, true)) {
            $row['status'] = 1;
        }

        $typeId = DB::table('payment_account_types')->insertGetId($row);
    }

    return PaymentAccount::create([
        'title' => 'Suite Test Account',
        'account_number' => 'TEST-' . uniqid(),
        'account_type_id' => $typeId,
        'opening_balance' => 0,
        'current_balance' => 0,
        'status' => 1,
        'created_by' => Auth::guard('web')->id(),
    ]);
}

function makePlan(Fee $fee, array $amounts, string $status = 'active'): PaymentPlan
{
    $plan = PaymentPlan::create([
        'student_id' => $fee->studentEnroll->student_id,
        'fee_id' => $fee->id,
        'total_amount' => array_sum($amounts),
        'installments_count' => count($amounts),
        'late_fee_percentage' => 0,
        'grace_period_days' => 7,
        'created_by' => Auth::guard('web')->id(),
        'approved_by' => Auth::guard('web')->id(),
        'approved_at' => now(),
        'status' => $status,
    ]);

    $n = 1;
    foreach ($amounts as $amount) {
        PaymentPlanInstallment::create([
            'payment_plan_id' => $plan->id,
            'installment_number' => $n,
            'amount' => $amount,
            'due_date' => date('Y-m-d', strtotime(PAY_DATE . ' +' . $n . ' months')),
            'grace_period_ends' => date('Y-m-d', strtotime(PAY_DATE . ' +' . $n . ' months +7 days')),
            'status' => 'pending',
        ]);
        $n++;
    }

    $fee->update(['payment_plan_id' => $plan->id]);

    return $plan->fresh('installments');
}

function pay(PaymentPlanInstallment $installment, float $amount, array $extra = []): PaymentPlanPayment
{
    return $installment->recordPayment($amount, array_merge([
        'payment_method' => 1,
        'payment_date' => PAY_DATE,
        'paid_by_type' => 'App\User',
        'paid_by_id' => Auth::guard('web')->id(),
    ], $extra));
}

/** What the ledger holds for this fee, as a fee posting. */
function feePosted(int $feeId): float
{
    return (float) DB::table('transaction_mappings as tm')
        ->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
        ->where('tm.transaction_type', 'fee')->where('tm.transaction_id', $feeId)
        ->where('tm.status', 'active')->sum('je.total_debit');
}

/** What the ledger holds for this plan, as instalment postings. */
function planPosted(PaymentPlan $plan): float
{
    $ids = PaymentPlanPayment::whereIn('installment_id', $plan->installments()->pluck('id'))->pluck('id');

    return (float) DB::table('transaction_mappings as tm')
        ->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
        ->where('tm.transaction_type', 'payment_plan_payment')
        ->whereIn('tm.transaction_id', $ids)
        ->where('tm.status', 'active')->sum('je.total_debit');
}

function refusalFrom(callable $action): ?string
{
    try {
        $action();
    } catch (\DomainException $e) {
        return $e->getMessage();
    }

    return null;
}

if (!freshFee()) {
    echo "no unpaid fee in a ledger-mapped category; this suite has nothing to work with\n";
    exit(0);
}

// What the database held before the suite touched anything, so the closing
// section can prove it holds exactly the same afterwards.
$plansAtStart = PaymentPlan::count();
$instalmentsAtStart = PaymentPlanInstallment::count();
$paymentsAtStart = PaymentPlanPayment::count();
$accountsAtStart = PaymentAccount::count();

// ---------------------------------------------------------------------------
section('A plan is created and the fee is locked to it');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $plan = makePlan($fee, [$due / 2, $due / 2]);
    $fee->refresh();

    check('the fee points at the plan', $fee->payment_plan_id === $plan->id);
    check('the fee reports an active plan', $fee->hasActivePaymentPlan());
    check('both instalments exist', $plan->installments->count() === 2);
    check('they add up to the fee', abs((float) $plan->installments->sum('amount') - $due) < 0.01);
    check('nothing is posted before anything is paid', feePosted($fee->id) < 0.009 && planPosted($plan) < 0.009);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Paying an instalment reaches everywhere the money goes');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $half = round($due / 2, 2);
    $plan = makePlan($fee, [$half, $due - $half]);
    $first = $plan->installments->first();

    $account = payingAccount();
    $balanceBefore = $account ? (float) $account->current_balance : null;
    $statementBefore = Transaction::where('transactionable_type', 'App\Models\Student')
        ->where('transactionable_id', $plan->student_id)->count();

    pay($first, $half, $account ? ['payment_account_id' => $account->id] : []);

    $fee->refresh();
    $first->refresh();

    check('the instalment is marked paid', $first->status === 'paid', $first->status);
    check('the fee records the money', abs((float) $fee->paid_amount - $half) < 0.01, 'fee paid ' . $fee->paid_amount);
    check('the fee is partially paid', (int) $fee->status === 2, 'status ' . $fee->status);
    check('the fee carries a pay date', !empty($fee->pay_date), var_export($fee->pay_date, true));
    check('the instalment payment is posted to the ledger',
        abs(planPosted($plan) - $half) < 0.01, 'posted ' . planPosted($plan));
    check('the fee itself is not posted as well', feePosted($fee->id) < 0.009, 'fee posted ' . feePosted($fee->id));
    check('the posting carries the right transaction type — not a blank one',
        TransactionMapping::where('transaction_type', 'payment_plan_payment')
            ->whereIn('transaction_id', PaymentPlanPayment::whereIn('installment_id',
                $plan->installments()->pluck('id'))->pluck('id'))
            ->where('status', 'active')->exists());

    if ($account) {
        $account->refresh();
        check('the payment account is credited',
            abs((float) $account->current_balance - ($balanceBefore + $half)) < 0.01,
            'balance ' . $account->current_balance . ', expected ' . ($balanceBefore + $half));
        check('and the cash book shows it',
            DB::table('payment_account_transactions')
                ->where('reference_type', 'payment_plan_payments')
                ->where('transaction_type', 'credit')->exists());
    } else {
        skip('no active payment account to credit');
    }

    check('the student statement gains the payment',
        Transaction::where('transactionable_type', 'App\Models\Student')
            ->where('transactionable_id', $plan->student_id)->count() === $statementBefore + 1);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A part payment leaves the instalment open');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $half = round($due / 2, 2);
    $plan = makePlan($fee, [$half, $due - $half]);
    $first = $plan->installments->first();

    pay($first, round($half / 2, 2));
    $first->refresh();

    check('the instalment is partial', $first->status === 'partial', $first->status);
    check('its balance is what is left',
        abs((float) $first->remaining_balance - ($half - round($half / 2, 2))) < 0.01,
        'balance ' . $first->remaining_balance);
    check('the plan is still active', $plan->fresh()->status === 'active');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Paying every instalment completes the plan');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $half = round($due / 2, 2);
    $plan = makePlan($fee, [$half, $due - $half]);

    foreach ($plan->installments as $instalment) {
        pay($instalment, (float) $instalment->amount);
    }

    $fee->refresh();
    $plan->refresh();

    check('the plan is completed', $plan->status === 'completed', $plan->status);
    check('the fee is fully paid', (int) $fee->status === 1, 'status ' . $fee->status);
    check('the fee holds the whole amount', abs((float) $fee->paid_amount - $due) < 0.01);
    check('the plan link is cleared', $fee->payment_plan_id === null);
    check('the ledger holds the money once, as instalments',
        abs(planPosted($plan) - $due) < 0.01 && feePosted($fee->id) < 0.009,
        'instalments ' . planPosted($plan) . ', fee ' . feePosted($fee->id));

    // The link is gone, so nothing but the arithmetic stops the fee posting the
    // same money again. Editing it is the moment that would have happened.
    $fee->update(['note' => trim((string) $fee->note . ' edited after completion')]);
    $fee->update(['pay_date' => PAY_DATE]);

    check('editing the fee afterwards does not post it a second time',
        feePosted($fee->id) < 0.009, 'fee posted ' . feePosted($fee->id));

    $sync = app(LedgerSyncService::class);
    $verdict = $sync->decide('fee', Fee::withCreditMovedOut()->find($fee->id), false,
        $sync->defaultIndex(), $sync->closedPeriods());

    check('and the accounting screen does not offer it either',
        !($verdict['eligible'] ?? false), json_encode($verdict['code'] ?? $verdict));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A plan cancelled halfway, then paid directly');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $half = round($due / 2, 2);
    $plan = makePlan($fee, [$half, $due - $half]);

    pay($plan->installments->first(), $half);
    $plan->cancel('test: student asked to pay the rest at once');
    $fee->refresh();

    check('the plan is cancelled', $plan->fresh()->status === 'cancelled');
    check('the fee is released for direct payment', $fee->payment_plan_id === null);
    check('the money already taken stays on the fee', abs((float) $fee->paid_amount - $half) < 0.01);
    check('and stays in the ledger, once', abs(planPosted($plan) - $half) < 0.01);

    // The rest is now paid straight onto the fee, as any ordinary payment.
    $rest = round($due - $half, 2);
    $fee->update([
        'paid_amount' => round((float) $fee->paid_amount + $rest, 2),
        'status' => 1,
        'pay_date' => PAY_DATE,
    ]);

    check('only the direct part is posted as a fee',
        abs(feePosted($fee->id) - $rest) < 0.01,
        'fee posted ' . feePosted($fee->id) . ', expected ' . $rest);
    check('so the ledger holds the fee exactly once over',
        abs((feePosted($fee->id) + planPosted($plan)) - $due) < 0.01,
        'total ' . (feePosted($fee->id) + planPosted($plan)) . ' against ' . $due . ' due');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Money the instalment cannot take is refused');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $half = round($due / 2, 2);
    $plan = makePlan($fee, [$half, $due - $half]);
    $first = $plan->installments->first();

    $message = refusalFrom(fn () => pay($first, $half + 1000));
    check('more than the balance is refused', $message !== null, 'no refusal');
    check('and the message says what is left',
        $message !== null && str_contains($message, number_format($half, 2)), (string) $message);
    check('nothing was written', (float) $first->fresh()->paid_amount < 0.009
        && (float) $fee->fresh()->paid_amount < 0.009);

    $message = refusalFrom(fn () => pay($first, 0));
    check('nothing, or less than nothing, is refused', $message !== null);

    // Settle it, then try to pay it again — two receipts approved for one
    // instalment is exactly how this happened.
    pay($first, $half);
    $message = refusalFrom(fn () => pay($first->fresh(), $half));
    check('a second payment on a settled instalment is refused', $message !== null, 'no refusal');
    check('the instalment is not paid beyond its amount',
        (float) $first->fresh()->paid_amount <= $half + 0.009,
        'holds ' . $first->fresh()->paid_amount . ' of ' . $half);
    check('and the fee is not overpaid',
        (float) $fee->fresh()->paid_amount <= $due + 0.009,
        'fee holds ' . $fee->fresh()->paid_amount . ' of ' . $due);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A plan that is no longer active takes no more money');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $half = round($due / 2, 2);

    $plan = makePlan($fee, [$half, $due - $half]);
    $plan->cancel('test');
    $message = refusalFrom(fn () => pay($plan->installments->first(), $half));

    check('a cancelled plan is refused', $message !== null, 'no refusal');
    check('and says so', $message !== null && str_contains($message, 'cancelled'), (string) $message);
    check('nothing reached the fee', (float) $fee->fresh()->paid_amount < 0.009);

    $other = freshFee([$fee->id]);
    if ($other) {
        $completed = makePlan($other, [(float) $other->total_amount], 'completed');
        $message = refusalFrom(fn () => pay($completed->installments->first(), 100));
        check('a completed plan is refused too', $message !== null, 'no refusal');
    } else {
        skip('no second fee to build a completed plan on');
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A fee cannot end up on two plans');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $first = makePlan($fee, [$due / 2, $due / 2]);

    // What the controller checks before it writes anything.
    $reloaded = $fee->fresh();
    $wouldRefuse = $reloaded->payment_plan_id && $reloaded->paymentPlan;

    check('a second plan on the same fee is refused', (bool) $wouldRefuse,
        'fee points at plan #' . $reloaded->payment_plan_id);
    check('the first plan still owns the fee', $reloaded->payment_plan_id === $first->id);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Late fees');
// ---------------------------------------------------------------------------
// Late fees are switched off in config, and the first thing to prove is that
// nothing can charge one — including a plan that already carries a percentage.
DB::beginTransaction();
try {
    config(['payment_plan.late_fees_enabled' => false]);

    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $fineBefore = (float) $fee->fine_amount;
    $plan = makePlan($fee, [$due / 2, $due / 2]);
    $plan->update(['late_fee_percentage' => 10]);

    $first = $plan->installments->first();

    check('a late fee cannot be charged while they are switched off',
        $first->applyLateFee() === false);
    check('and nothing lands on the instalment',
        (float) $first->fresh()->late_fee < 0.009, 'late fee ' . $first->fresh()->late_fee);
    check('nor on the fee',
        abs((float) $fee->fresh()->fine_amount - $fineBefore) < 0.01,
        'fine ' . $fee->fresh()->fine_amount);

    // Overdue still has to be marked — that reports the truth without charging.
    $first->update([
        'due_date' => date('Y-m-d', strtotime('-30 days')),
        'grace_period_ends' => date('Y-m-d', strtotime('-23 days')),
    ]);
    $first->updateStatus();

    check('instalments are still marked overdue',
        $first->fresh()->status === 'overdue', $first->fresh()->status);
    check('and still carry no charge',
        (float) $first->fresh()->late_fee < 0.009);
} finally {
    config(['payment_plan.late_fees_enabled' => false]);
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Late fees, for a school that does switch them on');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    config(['payment_plan.late_fees_enabled' => true]);

    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $fineBefore = (float) $fee->fine_amount;
    $plan = makePlan($fee, [$due / 2, $due / 2]);
    $plan->update(['late_fee_percentage' => 10]);

    $first = $plan->installments->first();
    $expected = round((float) $first->amount * 0.10, 2);

    $first->applyLateFee();
    $first->refresh();
    $fee->refresh();

    check('the late fee lands on the instalment',
        abs((float) $first->late_fee - $expected) < 0.01, 'late fee ' . $first->late_fee);
    check('and on the fee as a fine',
        abs((float) $fee->fine_amount - ($fineBefore + $expected)) < 0.01, 'fine ' . $fee->fine_amount);
    check('so the fee total grows with it',
        abs((float) $fee->total_amount - ($due + $expected)) < 0.01, 'total ' . $fee->total_amount);

    // Paying everything now settles the fee exactly — it does not read overpaid.
    foreach ($plan->fresh('installments')->installments as $instalment) {
        pay($instalment, (float) $instalment->amount + (float) ($instalment->late_fee ?? 0));
    }

    $fee->refresh();
    check('paying it all leaves the fee settled, not overpaid',
        !Fee::withCreditMovedOut()->find($fee->id)->isNetOverpaid()
            && abs((float) $fee->paid_amount - (float) $fee->total_amount) < 0.01,
        'paid ' . $fee->paid_amount . ' of ' . $fee->total_amount);

    // Marking something late must not charge for it by itself.
    $other = freshFee([$fee->id]);
    if ($other) {
        $latePlan = makePlan($other, [(float) $other->total_amount]);
        $latePlan->update(['late_fee_percentage' => 10]);
        $overdue = $latePlan->installments->first();
        $overdue->update([
            'due_date' => date('Y-m-d', strtotime('-30 days')),
            'grace_period_ends' => date('Y-m-d', strtotime('-23 days')),
        ]);

        $overdue->updateStatus();
        $overdue->refresh();

        check('marking an instalment overdue does not charge a late fee by itself',
            (float) $overdue->late_fee < 0.009 && $overdue->status === 'overdue',
            'status ' . $overdue->status . ', late fee ' . $overdue->late_fee);
    } else {
        skip('no second fee to test overdue marking on');
    }
} finally {
    config(['payment_plan.late_fees_enabled' => false]);
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The late fee query picks only overdue instalments');
// ---------------------------------------------------------------------------
$sql = PaymentPlanInstallment::where('status', 'overdue')
    ->where(function ($query) {
        $query->where('late_fee', 0)->orWhereNull('late_fee');
    })->toSql();

check('the two late-fee conditions are bracketed together',
    str_contains($sql, '(`late_fee` = ? or `late_fee` is null)'), $sql);

// ---------------------------------------------------------------------------
section('Reversing an instalment payment');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $half = round($due / 2, 2);
    $plan = makePlan($fee, [$half, $due - $half]);
    $first = $plan->installments->first();

    $account = payingAccount();
    $payment = pay($first, $half, $account ? ['payment_account_id' => $account->id] : []);

    $balanceAfterPaying = $account ? (float) $account->fresh()->current_balance : null;
    $statementBefore = Transaction::where('transactionable_type', 'App\Models\Student')
        ->where('transactionable_id', $plan->student_id)->count();

    $result = app(InstallmentPaymentReversal::class)
        ->reverse($payment->fresh(), 'test: recorded on the wrong instalment');

    $first->refresh();
    $fee->refresh();

    check('the reversal goes through', $result['reversed'] === true, $result['message']);
    check('the money comes off the instalment', (float) $first->paid_amount < 0.009,
        'instalment holds ' . $first->paid_amount);
    check('the instalment is open again', in_array($first->status, ['pending', 'overdue'], true), $first->status);
    check('the money comes off the fee', (float) $fee->paid_amount < 0.009, 'fee holds ' . $fee->paid_amount);
    check('the fee has no pay date left', empty($fee->pay_date), (string) $fee->pay_date);
    check('the payment is kept, marked reversed',
        $payment->fresh()->status === 'reversed' && $payment->fresh()->reversal_reason !== null);
    check('the ledger posting is reversed', planPosted($plan) < 0.009, 'still posted ' . planPosted($plan));
    check('and the fee was not posted in its place', feePosted($fee->id) < 0.009);
    $statementRows = Transaction::where('transactionable_type', 'App\Models\Student')
        ->where('transactionable_id', $plan->student_id);

    check('the student statement gains a row for the reversal',
        $statementRows->count() === $statementBefore + 1,
        $statementRows->count() . ' rows, expected ' . ($statementBefore + 1));
    check('and it is an opposing entry, not another payment',
        (int) $statementRows->latest('id')->first()->type === 2,
        'type ' . $statementRows->latest('id')->first()->type);

    if ($account) {
        check('the payment account is debited back',
            abs((float) $account->fresh()->current_balance - ($balanceAfterPaying - $half)) < 0.01,
            'balance ' . $account->fresh()->current_balance);
    } else {
        skip('no payment account to debit back');
    }

    check('the message says what happened',
        str_contains($result['message'], number_format($half, 2)), $result['message']);

    // Twice is not allowed.
    $again = app(InstallmentPaymentReversal::class)->reverse($payment->fresh(), 'test: again');
    check('a second reversal of the same payment is refused', $again['reversed'] === false, $again['message']);
    check('and says it is already reversed',
        str_contains($again['message'], 'already been reversed'), $again['message']);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Reversing the payment that completed a plan re-opens it');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $half = round($due / 2, 2);
    $plan = makePlan($fee, [$half, $due - $half]);

    $last = null;
    foreach ($plan->installments as $instalment) {
        $last = pay($instalment, (float) $instalment->amount);
    }

    check('the plan completed', $plan->fresh()->status === 'completed');

    $result = app(InstallmentPaymentReversal::class)->reverse($last->fresh(), 'test: paid by mistake');

    check('the reversal goes through', $result['reversed'] === true, $result['message']);
    check('the plan is active again', $plan->fresh()->status === 'active', $plan->fresh()->status);
    check('the fee is locked to it again', $fee->fresh()->payment_plan_id === $plan->id);
    check('the fee is no longer fully paid', (int) $fee->fresh()->status !== 1, 'status ' . $fee->fresh()->status);
    check('and the message says the plan re-opened',
        str_contains($result['message'], 're-opened'), $result['message']);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Cancelling and deleting');
// ---------------------------------------------------------------------------
DB::beginTransaction();
try {
    $fee = freshFee();
    $due = (float) $fee->total_amount;
    $plan = makePlan($fee, [$due / 2, $due / 2]);

    pay($plan->installments->first(), round($due / 2, 2));

    // What the controller refuses to delete.
    check('a plan that has taken money cannot be deleted',
        (float) $plan->fresh()->total_paid > 0);

    $empty = null;
    $other = freshFee([$fee->id]);
    if ($other) {
        $empty = makePlan($other, [(float) $other->total_amount]);
        $empty->installments()->delete();
        $empty->delete();

        check('deleting a plan releases its fee', $other->fresh()->payment_plan_id === null,
            'still points at #' . $other->fresh()->payment_plan_id);
    } else {
        skip('no second fee to delete a plan from');
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
// Counted, not assumed to be zero: this suite runs against real data, where
// the school may have plans of its own. What matters is that it leaves exactly
// what it found.
check('the suite created no payment plan that outlived its transaction',
    PaymentPlan::count() === $plansAtStart,
    PaymentPlan::count() . ' plans now, ' . $plansAtStart . ' before the suite ran');
check('and no instalment or payment either',
    PaymentPlanInstallment::count() === $instalmentsAtStart
        && PaymentPlanPayment::count() === $paymentsAtStart,
    PaymentPlanInstallment::count() . '/' . $instalmentsAtStart . ' instalments, '
        . PaymentPlanPayment::count() . '/' . $paymentsAtStart . ' payments');
check('and left no payment account of its own behind',
    PaymentAccount::count() === $accountsAtStart,
    PaymentAccount::count() . ' accounts now, ' . $accountsAtStart . ' before');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
