<?php
/**
 * Student credit in the ledger: overpayments held on account 419.
 *
 * Walks one student through the life of a credit with the real services:
 * overpay a fee, use part of the credit on another fee, refund the rest, raise
 * credit that no cash backs, undo an application. After each step the ledger is
 * read back directly (journal lines, not the service under test) and compared
 * with figures written out by hand.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/student_credit_ledger_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CreditApplication;
use App\Models\Fee;
use App\Models\StudentCredit;
use App\Services\Accounting\StudentCreditLedger;
use App\Services\StudentCreditService;
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

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();
Auth::guard('web')->login($admin);

$ledger = app(StudentCreditLedger::class);
$credits = app(StudentCreditService::class);
$liability = $ledger->liabilityAccountId();
$code = fn ($id) => DB::table('chart_of_accounts')->where('id', $id)->value('account_code');

/** Balance of an account from the posted journal lines, credit minus debit. */
$balance = function (int $accountId, ?array $entryIds = null) {
    return round((float) DB::table('journal_entry_lines as l')
        ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
        ->where('l.account_id', $accountId)->where('e.is_posted', 1)->whereNull('e.deleted_at')
        ->when($entryIds !== null, fn ($q) => $q->whereIn('e.id', $entryIds ?: [0]))
        ->sum(DB::raw('l.credit - l.debit')), 2);
};

$postings = fn (int $creditId, ?string $status = 'active') => DB::table('student_credit_postings')
    ->where('student_credit_id', $creditId)->when($status, fn ($q) => $q->where('status', $status))->orderBy('id')->get();

echo "\n== Set-up ==\n";
check('account 419 is the student credit account', $liability && $code($liability) === '419');
check('both observers are registered',
    str_contains(file_get_contents(__DIR__ . '/../app/Providers/AppServiceProvider.php'), 'StudentCredit::observe(')
    && str_contains(file_get_contents(__DIR__ . '/../app/Providers/AppServiceProvider.php'), 'CreditApplication::observe('));

DB::beginTransaction();

try {
    $enroll = App\Models\StudentEnroll::whereNotNull('student_id')->orderByDesc('id')->first();
    $studentId = (int) $enroll->student_id;
    $today = now()->toDateString();
    $first = DB::table('default_account_mappings')->where('mapping_type', 'fee_category')->where('status', 'active')
        ->whereIn('category_id', DB::table('fees_categories')->pluck('id'))->orderBy('category_id')->get();
    $catA = $first[0];
    $catB = $first->count() > 1 ? $first[1] : $first[0];
    $incomeA = (int) $catA->credit_account_id;
    $incomeB = (int) $catB->credit_account_id;
    $cash = (int) $catA->debit_account_id;

    $startEntry = (int) DB::table('journal_entries')->max('id');
    $newEntries = fn () => DB::table('journal_entries')->where('id', '>', $startEntry)->pluck('id')->all();

    $make = fn ($cat, $due, $paid) => Fee::create([
        'student_enroll_id' => $enroll->id, 'category_id' => $cat->category_id,
        'fee_amount' => $due, 'fine_amount' => 0, 'discount_amount' => 0, 'paid_amount' => $paid,
        'assign_date' => $today, 'due_date' => $today, 'pay_date' => $paid > 0 ? $today : null,
        'payment_method' => 1, 'status' => $paid > 0 ? 1 : 0,
    ]);

    echo "\n== An overpayment is held, not earned ==\n";
    $feeA = $make($catA, 100000, 160000);
    $credit = $credits->createFromOverpayment($feeA->fresh(), 60000);
    $hold = $postings($credit->id)->where('kind', 'hold')->first();
    // On accrual a billed fee's money sits on 411, so credit moves between 411 and 419.
    $accrual = App\Services\Accounting\FeeAccrual::enabled();
    $receivable = App\Services\Accounting\FeeAccrual::receivableAccountId();
    $settleA = $accrual ? $receivable : $incomeA;
    $settleB = $accrual ? $receivable : $incomeB;
    check('raising the credit posts a hold of 60,000', $hold && (float) $hold->amount === 60000.0);
    check($accrual ? 'the hold is Dr 411 / Cr 419' : 'the hold is Dr fee income / Cr 419',
        $hold && (int) $hold->debit_account_id === $settleA && (int) $hold->credit_account_id === $liability);
    check('so 419 holds 60,000', $balance($liability, $newEntries()) === 60000.0);
    check('and fee A earns 100,000 of its 160,000',
        $incomeA === $incomeB ? true : $balance($incomeA, $newEntries()) === 100000.0,
        'income A ' . $balance($incomeA, $newEntries()));

    echo "\n== Using the credit earns it on the fee it settles ==\n";
    $feeB = $make($catB, 100000, 0);
    $credits->applyToFee($credit->fresh(), $feeB->fresh(), 40000);
    $application = CreditApplication::where('student_credit_id', $credit->id)->first();
    $release = $postings($credit->id)->where('kind', 'release')->first();
    check('applying 40,000 posts a release of 40,000', $release && (float) $release->amount === 40000.0);
    check($accrual ? 'the release is Dr 419 / Cr 411 (it settles what fee B owes)' : 'the release is Dr 419 / Cr the receiving fee\'s income',
        $release && (int) $release->debit_account_id === $liability && (int) $release->credit_account_id === $settleB);
    check('419 now holds 20,000', $balance($liability, $newEntries()) === 20000.0);
    check('fee B posted no cash of its own (credit is not cash)',
        !DB::table('transaction_mappings')->where('transaction_type', 'fee')->where('transaction_id', $feeB->id)->where('status', 'active')->exists());

    echo "\n== Credit that no cash backs stays out of the ledger ==\n";
    $before = DB::table('student_credit_postings')->where('kind', '!=', 'discount')->count();
    $duplicate = $credits->createFromOverpayment($feeA->fresh(), 60000);
    check('a second credit for the same overpayment is not held', $postings($duplicate->id)->isEmpty());
    $manual = $credits->createManualCredit($studentId, 30000, 'test adjustment');
    $credits->applyToFee($manual->fresh(), $feeB->fresh(), 30000);
    $discounts = DB::table('student_credit_postings')->where('student_credit_id', $manual->id)->where('kind', 'discount')->where('status', 'active')->get();
    check($accrual ? 'a manual credit holds nothing; its use is honoured as a 30,000 discount on 411' : 'a manual credit and its use post nothing',
        $accrual
            ? DB::table('student_credit_postings')->where('kind', '!=', 'discount')->count() === $before && (float) $discounts->sum('amount') === 30000.0
            : DB::table('student_credit_postings')->where('kind', '!=', 'discount')->count() === $before);
    check('419 still holds 20,000', $balance($liability, $newEntries()) === 20000.0);

    echo "\n== A refund pays the held money back out ==\n";
    $cashBefore = -$balance($cash, $newEntries());
    $credit = $credit->fresh();
    $credits->requestRefund($credit, 'leaving');
    $credits->approveRefund($credit->fresh());
    $credits->processRefund($credit->fresh(), 'cash', 'R-1');
    $refund = $postings($credit->id)->where('kind', 'refund')->first();
    check('processing the refund posts 20,000', $refund && (float) $refund->amount === 20000.0);
    check('Dr 419 / Cr a cash account (class 5)',
        $refund && (int) $refund->debit_account_id === $liability && str_starts_with((string) $code($refund->credit_account_id), '5'));
    check('419 is back to nothing', $balance($liability, $newEntries()) === 0.0);
    check('the cash paid out is in the ledger', abs((-$balance((int) $refund->credit_account_id, $newEntries())) - ($refund->credit_account_id == $cash ? $cashBefore - 20000 : -20000)) < 0.01
        || $balance((int) $refund->credit_account_id, [DB::table('student_credit_postings')->where('id', $refund->id)->value('journal_entry_id')]) === 20000.0);

    echo "\n== The money is conserved ==\n";
    $entries = $newEntries();
    $income = round((float) DB::table('journal_entry_lines as l')->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
        ->whereIn('l.journal_entry_id', $entries)->where('a.account_code', 'like', '7%')->sum(DB::raw('l.credit - l.debit')), 2);
    $owedNow = $receivable ? -$balance($receivable, $entries) : 0.0;
    check('fee income + 419 − what is still owed (411) = cash received (160,000) − refunded (20,000)',
        abs($income - $owedNow - 140000.0) < 0.01, "income $income, owed " . (-$owedNow));
    $unbalanced = DB::table('journal_entries')->whereIn('id', $entries)->whereRaw('ABS(total_debit - total_credit) > 0.009')->count();
    check('every entry balances', $unbalanced === 0);

    echo "\n== The reconciliation names the timing difference ==\n";
    $effect = StudentCreditLedger::incomeEffect($today, $today);
    $expected = round((float) DB::table('journal_entry_lines as l')
        ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
        ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
        ->where('e.reference_type', 'like', 'student_credit%')->whereDate('e.entry_date', $today)
        ->where('a.account_code', 'like', '7%')->sum(DB::raw('l.credit - l.debit')), 2);
    check('incomeEffect() equals the credit lines on class 7 for the day', $effect === $expected, "$effect vs $expected");
    $rec = app(App\Services\BudgetReconciliationService::class)->reconcile($today, $today);
    $known = collect($rec['known_differences'])->firstWhere('section', 'income');
    $incomeSection = collect($rec['sections'])->firstWhere('section', 'income');
    check('and the reconciliation explains the fee income difference in full (named, nothing unexplained)',
        $incomeSection && $incomeSection['agrees'] && (abs($incomeSection['difference']) < 0.01 || $known !== null),
        json_encode($incomeSection ? array_intersect_key($incomeSection, array_flip(['sheet', 'ledger', 'difference', 'explained', 'unexplained'])) : null));

    echo "\n== Undoing a use reverses its release, nothing is deleted ==\n";
    $application->delete();
    $reversed = $postings($credit->id, 'reversed')->where('kind', 'release')->first();
    check('the release is marked reversed, with a reversing entry', $reversed && $reversed->reversal_entry_id);
    check('the original entry is kept and flagged',
        (bool) DB::table('journal_entries')->where('id', $reversed->journal_entry_id ?? 0)->value('is_reversed'));

    echo "\n== Money paid in advance is cash received and held, not a carve-out of a fee ==\n";
    $feeC = $make($catA, 70000, 70000);
    $prepay = $credits->createPrepayment($feeC->fresh(), 30000, ['pay_date' => $today, 'payment_method' => 1, 'payment_account_id' => null]);
    $receipt = $postings($prepay->id)->where('kind', 'receipt')->first();
    check('a prepayment posts a receipt of 30,000', $receipt && (float) $receipt->amount === 30000.0);
    check('Dr a cash account (class 5) / Cr 419',
        $receipt && str_starts_with((string) $code($receipt->debit_account_id), '5') && (int) $receipt->credit_account_id === $liability);
    $feeD = $make($catB, 30000, 0);
    $credits->applyToFee($prepay->fresh(), $feeD->fresh(), 30000);
    check('using it releases 30,000 to the receiving fee\'s income',
        (float) optional($postings($prepay->id)->where('kind', 'release')->first())->amount === 30000.0);
    $net = (float) DB::table('fees')->where('id', $feeC->id)->value(DB::raw(Fee::netPaidSql('fees')));
    check('the fee it was paid with still shows fully paid (nothing carved out of it)', abs($net - 70000) < 0.01, "net paid $net");

    echo "\n== Sync is repeatable ==\n";
    $again = $ledger->sync($studentId);
    $twice = $ledger->sync($studentId);
    check('a second sync changes nothing', $twice['posted'] === 0 && $twice['reversed'] === 0, json_encode($twice));
    $tie = $ledger->tieOut();
    check('419 ties out to the backed credit not yet used', $tie['agrees'], json_encode($tie));

} finally {
    DB::rollBack();
}

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
