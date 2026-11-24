<?php

/**
 * Money posts to the cash, bank or mobile-money account it actually went
 * through, not to the one cash account a category mapping names.
 *
 * Through the real screens: incomes, expenses, a fee and a payroll paid by
 * each method, a payment account linked to its own ledger account, and
 * transfers between payment accounts (posted, edited, deleted). Expected
 * accounts are read from the settings made here; the postings are read back
 * from the raw journal lines.
 *
 * Everything runs inside one transaction that is rolled back.
 *
 *   php scripts/cash_accounts_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $what . (!$ok && $detail !== '' ? " — $detail" : '') . "\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

config(['mail.default' => 'array']);
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
Auth::guard('web')->login($admin);

$call = function (string $method, string $path, array $data = []) use ($kernel) {
    app('session.store')->forget('errors');
    if ($method !== 'GET') {
        $data['_token'] = csrf_token();
    }
    $request = Request::create($path, $method, $data);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/dashboard');

    return $kernel->handle($request);
};
$errors = fn () => session('errors') ? implode(' | ', session('errors')->all()) : '';

$acct = DB::table('chart_of_accounts')->pluck('id', 'account_code');
$codeOf = DB::table('chart_of_accounts')->pluck('account_code', 'id');
$today = date('Y-m-d');

/** The class-5 account a source's live entry posted to, and on which side. */
$cashSide = function (string $type, int $id) use ($codeOf) {
    $entry = JournalEntry::where('reference_type', $type)->where('reference_id', $id)->where('is_reversed', false)->whereNull('deleted_at')->latest('id')->first();
    if (!$entry) {
        return 'no entry';
    }
    foreach ($entry->lines as $l) {
        $code = (string) $codeOf[$l->account_id];
        if ($code[0] === '5') {
            return $code . ($l->debit > 0 ? ' Dr' : ' Cr');
        }
    }

    return 'no cash line';
};
$tb = function () {
    $t = DB::selectOne('SELECT COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c FROM journal_entry_lines l JOIN journal_entries je ON je.id = l.journal_entry_id AND je.is_posted = 1 AND je.deleted_at IS NULL');

    return abs($t->d - $t->c) < 0.01;
};

DB::beginTransaction();
$countsBefore = [DB::table('journal_entries')->count(), DB::table('payment_accounts')->count(), DB::table('ledger_cash_settings')->count()];

try {
    section('1. Before any setting, postings go where the mapping says');
    $call('POST', '/admin/account/income', ['category' => 2, 'title' => 'TEST before', 'amount' => 1000, 'date' => $today, 'payment_method' => 2]);
    $inc0 = App\Models\Income::where('title', 'TEST before')->latest('id')->first();
    check('an income by bank transfer still posts to 571 (no default set yet)', $cashSide('income', $inc0->id) === '571 Dr', $cashSide('income', $inc0->id) . ' ' . $errors());

    section('2. The settings page');
    check('the page opens', $call('GET', '/admin/accounting/cash-accounts')->getStatusCode() === 200);
    $type = DB::table('payment_account_types')->value('id');
    $call('POST', '/admin/payment-account/store', ['title' => 'TEST Wallet', 'account_type_id' => $type, 'opening_balance' => 0, 'chart_of_account_id' => $acct['53']]);
    $wallet = App\Models\PaymentAccount::where('title', 'TEST Wallet')->first();
    check('a payment account can be created linked to 53 Mobile Money', $wallet && (int) $wallet->chart_of_account_id === (int) $acct['53'], $errors());
    $call('POST', '/admin/payment-account/store', ['title' => 'TEST Bad', 'account_type_id' => $type, 'opening_balance' => 0, 'chart_of_account_id' => $acct['628']]);
    check('linking a payment account to a non-cash account is refused', !App\Models\PaymentAccount::where('title', 'TEST Bad')->exists(), 'it was created');
    $call('POST', '/admin/payment-account/store', ['title' => 'TEST Cash box', 'account_type_id' => $type, 'opening_balance' => 500000]);
    $box = App\Models\PaymentAccount::where('title', 'TEST Cash box')->first();
    $call('POST', '/admin/payment-account/store', ['title' => 'TEST Bank', 'account_type_id' => $type, 'opening_balance' => 0]);
    $bank = App\Models\PaymentAccount::where('title', 'TEST Bank')->first();

    $call('POST', '/admin/accounting/cash-accounts', [
        'cash_account_id' => $acct['571'], 'bank_account_id' => $acct['521'], 'mobile_money_account_id' => $acct['53'],
        'links' => [$box->id => $acct['571'], $bank->id => $acct['521'], $wallet->id => $acct['53']],
    ]);
    $s = App\Models\LedgerCashSetting::current();
    check('defaults saved: cash 571, bank 521, mobile money 53', (int) $s->cash_account_id === (int) $acct['571'] && (int) $s->bank_account_id === (int) $acct['521'] && (int) $s->mobile_money_account_id === (int) $acct['53'], $errors());
    check('payment accounts linked from the same page', (int) $box->fresh()->chart_of_account_id === (int) $acct['571'] && (int) $bank->fresh()->chart_of_account_id === (int) $acct['521']);
    $call('POST', '/admin/accounting/cash-accounts', ['cash_account_id' => $acct['628']]);
    check('a default that is not a cash account is refused', (int) App\Models\LedgerCashSetting::current()->cash_account_id === (int) $acct['571']);

    section('3. Incomes and expenses (current method numbers: 1 cash, 2 bank, 3/4 mobile money)');
    foreach ([[1, '571'], [2, '521'], [3, '53'], [4, '53']] as [$method, $code]) {
        $call('POST', '/admin/account/income', ['category' => 2, 'title' => 'TEST income m' . $method, 'amount' => 1000, 'date' => $today, 'payment_method' => $method]);
        $row = App\Models\Income::where('title', 'TEST income m' . $method)->latest('id')->first();
        check("income paid by method $method posts to $code", $row && $cashSide('income', $row->id) === "$code Dr", $row ? $cashSide('income', $row->id) : $errors());
    }
    $call('POST', '/admin/account/income', ['category' => 2, 'title' => 'TEST income via wallet', 'amount' => 1000, 'date' => $today, 'payment_method' => 1, 'payment_account_id' => $wallet->id]);
    $viaWallet = App\Models\Income::where('title', 'TEST income via wallet')->latest('id')->first();
    check('a payment account named on the income wins over its method (wallet → 53)', $cashSide('income', $viaWallet->id) === '53 Dr', $cashSide('income', $viaWallet->id));
    foreach ([[1, '571'], [2, '521']] as [$method, $code]) {
        $call('POST', '/admin/account/expense', ['category' => 2, 'title' => 'TEST expense m' . $method, 'amount' => 700, 'date' => $today, 'payment_method' => $method]);
        $row = App\Models\Expense::where('title', 'TEST expense m' . $method)->latest('id')->first();
        check("expense paid by method $method is credited to $code", $row && $cashSide('expense', $row->id) === "$code Cr", $row ? $cashSide('expense', $row->id) : $errors());
    }
    $call('POST', '/admin/account/expense', ['category' => 20, 'title' => 'TEST equipment by bank', 'amount' => 900, 'date' => $today, 'payment_method' => 2]);
    $eq = App\Models\Expense::where('title', 'TEST equipment by bank')->latest('id')->first();
    check('equipment paid by bank: Dr 241, Cr 521', $eq && $cashSide('expense', $eq->id) === '521 Cr'
        && JournalEntry::where('reference_type', 'expense')->where('reference_id', $eq->id)->first()->lines->contains(fn ($l) => $codeOf[$l->account_id] == '241' && $l->debit > 0));

    // Changing only the method moves the posting.
    $m1 = App\Models\Income::where('title', 'TEST income m1')->latest('id')->first();
    $m1->update(['payment_method' => 2]);
    check('changing an income from cash to bank moves its posting to 521', $cashSide('income', $m1->id) === '521 Dr', $cashSide('income', $m1->id));
    check('by a reversal of the 571 posting, not an edit', JournalEntry::where('reference_type', 'income_reversal')->where('reference_id', $m1->id)->exists());

    section('4. Fees (older method numbers: 1/2 cash, 3 MTN, 4 bank)');
    $enroll = App\Models\StudentEnroll::whereHas('student', fn ($q) => $q->where('student_id', 'E2E-STU-01'))->first()
        ?? App\Models\StudentEnroll::where('status', 1)->first();
    foreach ([[4, '521'], [3, '53'], [2, '571']] as [$method, $code]) {
        $before = (int) DB::table('fees')->max('id');
        $call('POST', '/admin/fees-student-quick-assign', ['student' => $enroll->id, 'category' => 5, 'amount' => 5000, 'type' => 1, 'assign_date' => $today, 'due_date' => $today]);
        $fee = App\Models\Fee::where('id', '>', $before)->first();
        $call('POST', '/admin/fees-student-pay', ['fee_id' => $fee->id, 'pay_date' => $today, 'payment_method' => $method, 'paid_amount' => 5000]);
        check("fee paid by method $method posts to $code", $cashSide('fee', $fee->id) === "$code Dr", $cashSide('fee', $fee->id) . ' ' . $errors());
    }

    section('5. Payroll (older method numbers: 4 bank)');
    $staff = App\User::where('staff_id', 'E2E-STF-02')->first();
    if ($staff) {
        $month = date('Y-m-01', strtotime('first day of next month'));
        $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => (int) date('m', strtotime($month)), 'year' => (int) date('Y', strtotime($month)), 'users' => [$staff->id]]);
        $payroll = App\Models\Payroll::where('user_id', $staff->id)->where('salary_month', $month)->first();
        $bankAccount = App\Models\StaffBankAccount::where('user_id', $staff->id)->value('id');
        if ($payroll) {
            if (App\Models\PayrollSetting::current()->require_approval) {
                $call('POST', '/admin/staff/payroll-approve/' . $payroll->id);
            }
            $call('POST', '/admin/staff/payroll-pay/' . $payroll->id, ['pay_date' => $today, 'payment_method' => 4, 'bank_account_id' => $bankAccount]);
            check('a salary paid by bank transfer is credited to 521', $cashSide('payroll', $payroll->id) === '521 Cr', $cashSide('payroll', $payroll->id) . ' ' . $errors());
        } else {
            check('a payroll was generated to pay', false, json_encode(session('payroll_bulk_result')));
        }
    } else {
        check('the E2E staff member exists to pay', false);
    }

    section('6. Transfers between payment accounts');
    $call('POST', '/admin/payment-account-transfer/store', ['from_account_id' => $box->id, 'to_account_id' => $bank->id, 'amount' => 200000, 'transfer_date' => $today]);
    $transfer = App\Models\PaymentAccountTransfer::where('from_account_id', $box->id)->latest('id')->first();
    $entry = $transfer ? JournalEntry::where('reference_type', 'payment_transfer')->where('reference_id', $transfer->id)->first() : null;
    $lines = $entry ? $entry->lines->mapWithKeys(fn ($l) => [$codeOf[$l->account_id] => $l->debit - $l->credit]) : collect();
    check('a transfer from the cash box to the bank posts Dr 521 / Cr 571, 200,000', $entry && $entry->is_posted && (float) ($lines['521'] ?? 0) === 200000.0 && (float) ($lines['571'] ?? 0) === -200000.0,
        $entry ? json_encode($lines) : 'no entry ' . $errors());
    $call('PUT', '/admin/payment-account-transfer/' . $transfer->id . '/update', ['from_account_id' => $box->id, 'to_account_id' => $bank->id, 'amount' => 150000, 'transfer_date' => $today]);
    $live = JournalEntry::where('reference_type', 'payment_transfer')->where('reference_id', $transfer->id)->where('is_reversed', false)->get();
    check('editing it reverses the old posting and posts 150,000', $live->count() === 1 && (float) $live->first()->total_debit === 150000.0
        && JournalEntry::where('reference_type', 'payment_transfer_reversal')->where('reference_id', $transfer->id)->count() === 1, $live->pluck('total_debit')->implode(','));
    $call('DELETE', '/admin/payment-account-transfer/' . $transfer->id . '/delete');
    check('deleting it reverses the posting too', JournalEntry::where('reference_type', 'payment_transfer')->where('reference_id', $transfer->id)->where('is_reversed', false)->count() === 0
        && JournalEntry::where('reference_type', 'payment_transfer_reversal')->where('reference_id', $transfer->id)->count() === 2);
    $call('POST', '/admin/payment-account-transfer/store', ['from_account_id' => $box->id, 'to_account_id' => $bank->id, 'amount' => 99999999, 'transfer_date' => $today]);
    check('a transfer beyond the balance is refused and leaves no transaction open', DB::transactionLevel() === 1, 'transaction level ' . DB::transactionLevel());

    section('7. The books beside the ledger');
    $cmp = app(App\Services\Accounting\CashAccounts::class)->comparison();
    check('the comparison lists each linked ledger account', $cmp->pluck('account_code')->map(fn ($c) => (string) $c)->sort(fn ($a, $b) => strcmp($a, $b))->values()->all() === ['521', '53', '571'], $cmp->pluck('account_code')->implode(','));
    check('and the page shows it', str_contains((string) $call('GET', '/admin/accounting/cash-accounts')->getContent(), 'Books beside the ledger'));

    section('8. The ledger still balances');
    check('debits equal credits after all of it', $tb());
} catch (Throwable $e) {
    check('no exception', false, get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}

section('Nothing left behind');
check('entries, payment accounts and settings are as they were', [DB::table('journal_entries')->count(), DB::table('payment_accounts')->count(), DB::table('ledger_cash_settings')->count()] == $countsBefore);

echo "\ncash_accounts_test: passed $passed, failed $failed\n";
exit($failed ? 1 : 0);
