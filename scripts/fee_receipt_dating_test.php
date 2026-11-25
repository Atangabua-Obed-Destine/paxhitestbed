<?php
/**
 * Fee cash on the day it came in (fee posting plan, step 3).
 *
 * A fee paid in October and again in November: the October money must be in
 * October in the ledger, the daybook and the budget sheet, and the three must
 * still agree. Recorded the way the payment screen records it - the fee is
 * saved first, then its receipt - to show that order makes no extra entries.
 * Everything runs in a transaction that is rolled back.
 *
 * Usage: php scripts/fee_receipt_dating_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use App\Models\PaymentReceipt;
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

DB::beginTransaction();
try {
    $start = (int) DB::table('journal_entries')->max('id');
    $octUnexplained = fn () => collect(app(App\Services\BudgetReconciliationService::class)->reconcile('2026-10-01', '2026-10-31')['sections'])->firstWhere('section', 'income')['unexplained'];
    $unexplainedBefore = $octUnexplained();
    $entries = fn () => DB::table('journal_entries')->where('id', '>', $start)->whereNull('deleted_at')->get();
    $enroll = App\Models\StudentEnroll::whereNotNull('student_id')->orderByDesc('id')->first();
    $cash = (int) DB::table('default_account_mappings')->where('mapping_type', 'fee_category')->where('category_id', 5)->value('debit_account_id');
    $oct = '2026-10-10';
    $nov = '2026-11-20';

    $pay = function (Fee $fee, float $amount, string $date) {
        // As FeePaymentRecorder does it: the fee first, then its receipt.
        $fee->paid_amount = (float) $fee->paid_amount + $amount;
        $fee->pay_date = $date;
        $fee->payment_method = 1;
        $fee->status = $fee->paid_amount >= $fee->total_amount ? 1 : 2;
        $fee->save();
        PaymentReceipt::create(['fee_id' => $fee->id, 'student_id' => $fee->studentEnroll->student_id, 'payment_date' => $date,
            'amount' => $amount, 'payment_method' => 1, 'verification_status' => 'approved', 'payment_reference' => 'E2E-' . $date]);
    };

    $fee = Fee::create(['student_enroll_id' => $enroll->id, 'category_id' => 5, 'fee_amount' => 150000, 'fine_amount' => 0, 'discount_amount' => 0,
        'paid_amount' => 0, 'assign_date' => '2026-10-01', 'due_date' => '2026-12-31', 'status' => 0]);

    echo "\n== Paid 100,000 in October ==\n";
    $pay($fee->fresh(), 100000, $oct);
    $own = DB::table('transaction_mappings')->where('transaction_type', 'fee')->where('transaction_id', $fee->id)->where('status', 'active')->first();
    check('one entry, 100,000, dated in October', $own && (float) $own->amount === 100000.0 && $own->transaction_date === $oct);
    check('no earlier-dated part yet', !DB::table('fee_cash_parts')->where('fee_id', $fee->id)->where('status', 'active')->exists());

    echo "\n== Paid the other 50,000 in November ==\n";
    $before = $entries()->count();
    $pay($fee->fresh(), 50000, $nov);
    $part = DB::table('fee_cash_parts')->where('fee_id', $fee->id)->where('status', 'active')->first();
    $own = DB::table('transaction_mappings')->where('transaction_type', 'fee')->where('transaction_id', $fee->id)->where('status', 'active')->first();
    check('October\'s 100,000 is a part dated in October', $part && (float) $part->amount === 100000.0 && substr($part->entry_date, 0, 10) === $oct);
    check('the fee\'s own entry carries November\'s 50,000, dated in November', $own && (float) $own->amount === 50000.0 && $own->transaction_date === $nov);
    $made = $entries()->count() - $before;
    check('saving the fee, then the receipt, made no churn: the October part, plus the November reversal and repost', $made === 3, "$made entries");

    $receiptAgain = PaymentReceipt::where('fee_id', $fee->id)->orderByDesc('id')->first();
    $receiptAgain->payment_reference = 'E2E-touched';
    $receiptAgain->save();
    check('touching a receipt without changing its money changes nothing', $entries()->count() - $before === 3);

    echo "\n== Every view dates the cash the same way ==\n";
    $ledgerMonth = fn (string $from, string $to) => round((float) DB::table('journal_entry_lines as l')
        ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
        ->where('e.id', '>', $start)->where('e.is_posted', 1)->whereNull('e.deleted_at')
        ->where('l.account_id', $cash)->whereBetween('e.entry_date', [$from, $to])->sum(DB::raw('l.debit - l.credit')), 2);
    check('ledger cash: 100,000 in October', $ledgerMonth('2026-10-01', '2026-10-31') === 100000.0, (string) $ledgerMonth('2026-10-01', '2026-10-31'));
    check('ledger cash: 50,000 in November', $ledgerMonth('2026-11-01', '2026-11-30') === 50000.0, (string) $ledgerMonth('2026-11-01', '2026-11-30'));

    $book = collect(app(App\Services\DaybookService::class)->rows('2026-10-01', '2026-11-30'))->where('source', 'fee')->where('source_id', $fee->id);
    check('daybook: two rows, 100,000 on ' . $oct . ' and 50,000 on ' . $nov,
        $book->count() === 2 && (float) $book->firstWhere('date', $oct)['amount'] === 100000.0 && (float) $book->firstWhere('date', $nov)['amount'] === 50000.0,
        json_encode($book->values()->map(fn ($r) => [$r['date'], $r['amount']])));

    $after = $octUnexplained();
    check('the budget sheet and the ledger reconcile for October exactly as before this fee (nothing new unexplained)',
        abs($after - $unexplainedBefore) < 0.01, "before $unexplainedBefore, after $after");

    echo "\n== The checks agree ==\n";
    check('the credit audit finds nothing to correct', app(App\Services\FeeCreditReconciliation::class)->ledgerCorrections()->where('fee_id', $fee->id)->isEmpty());
    $health = app(App\Services\Accounting\LedgerHealth::class);
    $health->forget();
    $drift = collect($health->checks())->firstWhere('key', 'amount_drift');
    check('ledger health sees no amount drift on this fee', collect($drift['rows'])->where('reference_id', $fee->id)->isEmpty());
    if (App\Services\Accounting\FeeAccrual::enabled()) {
        check('411 still equals what students owe', app(App\Services\Accounting\FeeAccrual::class)->tieOut()['agrees']);
    }
    check('every new entry balances', $entries()->filter(fn ($e) => abs($e->total_debit - $e->total_credit) > 0.009)->isEmpty());
    check('a second resync changes nothing', app(App\Services\FeeLedgerPosting::class)->resync($fee->fresh()) === App\Services\FeeLedgerPosting::UNCHANGED);
} finally {
    DB::rollBack();
}

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
