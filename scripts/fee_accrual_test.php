<?php
/**
 * Fees on accrual: what students owe is on 411.
 *
 * Assign, change, cancel, delete and pay fees with the real models and
 * services, and read the ledger back directly after each step. Applicants'
 * fees stay on a cash basis. Everything runs in a transaction that is rolled
 * back.
 *
 * Usage: php scripts/fee_accrual_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use App\Services\Accounting\FeeAccrual;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . (!$ok && $detail !== '' ? "\n          $detail" : '') . "\n";
}

Auth::guard('web')->login(App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first());

echo "\n== Set-up ==\n";
check('fees are on accrual on this install', FeeAccrual::enabled());
$receivable = FeeAccrual::receivableAccountId();
check('the receivable account is 411', DB::table('chart_of_accounts')->where('id', $receivable)->value('account_code') === '411');
$tie = app(FeeAccrual::class)->tieOut();
check('before anything, 411 equals what the fee records say is owed', $tie['agrees'], json_encode($tie));

DB::beginTransaction();
try {
    $start = (int) DB::table('journal_entries')->max('id');
    $new = fn () => DB::table('journal_entries')->where('id', '>', $start)->pluck('id')->all();
    $bal = fn (int $account) => round((float) DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
        ->whereIn('e.id', $new() ?: [0])->where('e.is_posted', 1)->where('l.account_id', $account)->sum(DB::raw('l.debit - l.credit')), 2);
    $billing = fn (int $feeId) => DB::table('fee_billing_postings')->where('fee_id', $feeId)->where('status', 'active')->first();

    $enroll = App\Models\StudentEnroll::whereNotNull('student_id')->orderByDesc('id')->first();
    $map = DB::table('default_account_mappings')->where('mapping_type', 'fee_category')->where('category_id', 5)->first();
    $income = (int) $map->credit_account_id;
    $today = now()->toDateString();

    echo "\n== Assigning a fee bills it ==\n";
    $fee = Fee::create(['student_enroll_id' => $enroll->id, 'category_id' => 5, 'fee_amount' => 120000, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 0, 'assign_date' => $today, 'due_date' => $today, 'status' => 0]);
    $b = $billing($fee->id);
    check('a billing of 120,000 is posted', $b && (float) $b->amount === 120000.0);
    check('Dr 411 / Cr the category\'s income account', $b && (int) $b->debit_account_id === $receivable && (int) $b->credit_account_id === $income);
    check('411 rises by 120,000', $bal($receivable) === 120000.0);

    echo "\n== Changing what it asks re-bills it ==\n";
    $fee->discount_amount = 20000;
    $fee->save();
    $b2 = $billing($fee->id);
    check('the bill follows a discount: now 100,000', $b2 && (float) $b2->amount === 100000.0);
    check('the first bill was reversed, not deleted', DB::table('fee_billing_postings')->where('fee_id', $fee->id)->where('status', 'reversed')->whereNotNull('reversal_entry_id')->exists());
    check('411 now shows 100,000 owed', $bal($receivable) === 100000.0);

    echo "\n== A payment settles 411, it earns nothing twice ==\n";
    $incomeBefore = $bal($income);
    $fee->paid_amount = 60000;
    $fee->pay_date = $today;
    $fee->payment_method = 1;
    $fee->status = 2;
    $fee->save();
    $cashEntry = DB::table('transaction_mappings')->where('transaction_type', 'fee')->where('transaction_id', $fee->id)->where('status', 'active')->first();
    check('the payment posts Dr cash / Cr 411', $cashEntry && (int) $cashEntry->credit_account_id === $receivable);
    check('income does not move when the fee is paid', $bal($income) === $incomeBefore);
    check('411 shows the 40,000 still owed', $bal($receivable) === 40000.0);

    echo "\n== Cancelling a fee takes the bill back ==\n";
    $other = Fee::create(['student_enroll_id' => $enroll->id, 'category_id' => 5, 'fee_amount' => 50000, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 0, 'assign_date' => $today, 'due_date' => $today, 'status' => 0]);
    check('a second fee is billed', (bool) $billing($other->id));
    $other->status = 3;
    $other->save();
    check('cancelled: its bill is reversed', !$billing($other->id));

    $third = Fee::create(['student_enroll_id' => $enroll->id, 'category_id' => 5, 'fee_amount' => 30000, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 0, 'assign_date' => $today, 'due_date' => $today, 'status' => 0]);
    $third->delete();
    check('deleted: its bill is reversed', !$billing($third->id));
    check('411 is back to the 40,000 owed on the first fee', $bal($receivable) === 40000.0);

    echo "\n== An applicant's admission fee is not billed ==\n";
    $applicantFee = Fee::where('applicant_id', '!=', null)->first();
    check('applicants owe nothing until they come: no bill', $applicantFee === null || !FeeAccrual::bills($applicantFee));

    echo "\n== The books agree ==\n";
    $tie = app(FeeAccrual::class)->tieOut();
    check('411 equals what the fee records say is owed', $tie['agrees'], json_encode($tie));
    $unbalanced = DB::table('journal_entries')->whereIn('id', $new() ?: [0])->whereRaw('ABS(total_debit - total_credit) > 0.009')->count();
    check('every entry balances', $unbalanced === 0);
    $again = app(FeeAccrual::class)->sync($fee->id);
    check('syncing again changes nothing', $again === 'unchanged', $again);
} finally {
    DB::rollBack();
}

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
