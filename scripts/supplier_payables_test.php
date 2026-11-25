<?php
/**
 * Supplier bills and payments (accounts payable).
 *
 * What this guards:
 *
 *   - approving a bill posts the cost and the debt, and nothing else does;
 *   - what is owed is the total less live payments, everywhere (no stored flag);
 *   - a payment can never exceed what is owed, nor be split wrongly;
 *   - voids reverse in the ledger and the row stays;
 *   - the budget sheet and the daybook see a bill exactly as the ledger does,
 *     so the sheet-versus-ledger reconciliation does not move;
 *   - the aging report and a supplier statement agree with account 40;
 *   - closed periods, missing fiscal years, duplicate invoices and cash boxes.
 *
 * Expectations are worked out here from the inputs, not by calling the code
 * under test twice. Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/supplier_payables_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\ItemSupplier;
use App\Models\JournalEntry;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Services\Accounting\BudgetLineChoices;
use App\Services\Accounting\SupplierPayables;
use App\Services\BudgetActualsService;
use App\Services\BudgetReconciliationService;
use App\Services\DaybookService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

/** Run $fn and return the RuntimeException message it throws, or null. */
function refusal(callable $fn): ?string
{
    try {
        $fn();
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    }

    return null;
}

/** Net balance of an account (credit - debit) from posted lines, optionally up to a date. */
function creditBalance(int $accountId, ?string $asOf = null): float
{
    return round((float) DB::table('journal_entry_lines as l')
        ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
        ->where('l.account_id', $accountId)->where('j.is_posted', 1)->whereNull('j.deleted_at')
        ->when($asOf, fn ($q) => $q->whereDate('j.entry_date', '<=', $asOf))
        ->sum(DB::raw('l.credit - l.debit')), 2);
}

$admin = App\User::where('is_admin', 1)->orderBy('id')->firstOrFail();
Auth::guard('web')->login($admin);
$uid = (int) $admin->id;

$ap = app(SupplierPayables::class);
$actuals = app(BudgetActualsService::class);
$daybook = app(DaybookService::class);
$recon = app(BudgetReconciliationService::class);

$categories = (new BudgetLineChoices())->categories('expense_category')->values();
if ($categories->count() < 2) {
    echo "Needs two expense categories filed under budget lines.\n";
    exit(1);
}
$catA = $categories[0];
$catB = $categories[1];
$accountOf = fn ($cat) => (int) DB::table('default_account_mappings')
    ->where('mapping_type', 'expense_category')->where('category_id', $cat['id'])->value('debit_account_id');

$payable = (int) ChartOfAccount::where('account_code', \App\Console\Commands\ChartSyscohada::current('40'))->value('id');
$cash = (int) ChartOfAccount::where('account_code', '571')->value('id');
$equity = (int) ChartOfAccount::where('class_number', 1)->where('account_category', 'detail')->orderBy('account_code')->value('id');

$window = ['2026-01-01', '2026-12-31'];

echo "Setup\n";
check('the supplier account is set to 40 Fournisseurs', $ap->payableAccountId() === $payable,
    'got ' . var_export($ap->payableAccountId(), true));

DB::beginTransaction();

try {
    $supplier = ItemSupplier::create(['title' => 'TEST Supplier ' . uniqid(), 'contact_person' => 'Test',
        'payment_terms_days' => 30, 'tax_number' => 'M0123456789', 'status' => 1]);
    $other = ItemSupplier::create(['title' => 'TEST Other ' . uniqid(), 'contact_person' => 'Test', 'status' => 1]);

    $before = [
        'entries' => JournalEntry::count(),
        'payable' => creditBalance($payable),
        'cash' => creditBalance($cash),
        'lineA' => $actuals->forPeriod(...$window)['lines'][$catA['line_id']] ?? 0.0,
        'lineB' => $actuals->forPeriod(...$window)['lines'][$catB['line_id']] ?? 0.0,
        'recon' => collect($recon->reconcile(...$window)['sections'])->keyBy('section')->map(fn ($s) => $s['unexplained'])->all(),
        'gap' => $ap->aging('2026-11-24')['difference'],
    ];

    echo "\nDrafts\n";
    $bill = $ap->saveDraft([
        'supplier_id' => $supplier->id, 'supplier_reference' => 'INV-77', 'bill_date' => '2026-09-10',
        'description' => 'Stationery and repairs',
        'lines' => [
            ['description' => 'Paper', 'expense_category_id' => $catA['id'], 'amount' => 120000],
            ['description' => 'Repair', 'expense_category_id' => $catB['id'], 'amount' => 80000.5],
            ['description' => '', 'expense_category_id' => '', 'amount' => ''],
        ],
    ], $uid);

    check('a draft takes its due date from the supplier terms (10 Sep + 30 days)', $bill->due_date->toDateString() === '2026-10-10',
        $bill->due_date->toDateString());
    check('its total is the sum of the lines, blank rows ignored', (float) $bill->total === 200000.5 && $bill->lines->count() === 2,
        $bill->total . ' / ' . $bill->lines->count());
    check('a draft posts nothing', JournalEntry::count() === $before['entries']);
    check('bills are numbered by their own year', preg_match('/^SB-2026-\d{4}$/', $bill->number) === 1, $bill->number);
    check('a draft is owed nothing and reads as draft', $ap->owedOn($bill) == 0.0 && $ap->paymentState($bill) === 'draft');

    check('a line without a filed category is refused', refusal(fn () => $ap->saveDraft([
        'supplier_id' => $supplier->id, 'bill_date' => '2026-09-10',
        'lines' => [['description' => 'x', 'expense_category_id' => 999999, 'amount' => 10]]], $uid)) !== null);
    check('a bill with no lines is refused', refusal(fn () => $ap->saveDraft([
        'supplier_id' => $supplier->id, 'bill_date' => '2026-09-10', 'lines' => []], $uid)) !== null);
    check('a due date before the bill date is refused', refusal(fn () => $ap->saveDraft([
        'supplier_id' => $supplier->id, 'bill_date' => '2026-09-10', 'due_date' => '2026-09-01',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 10]]], $uid)) !== null);
    check('an opening balance against a non-equity account is refused', refusal(fn () => $ap->saveDraft([
        'supplier_id' => $supplier->id, 'kind' => 'opening_balance', 'bill_date' => '2026-01-01',
        'opening_account_id' => $cash, 'lines' => [['amount' => 10]]], $uid)) !== null);

    $toDelete = $ap->saveDraft(['supplier_id' => $supplier->id, 'bill_date' => '2026-09-10',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 5]]], $uid);
    $ap->deleteDraft($toDelete);
    check('deleting a draft leaves nothing behind', !SupplierBill::whereKey($toDelete->id)->exists()
        && DB::table('supplier_bill_lines')->where('supplier_bill_id', $toDelete->id)->count() === 0);

    echo "\nApproval\n";
    $ap->approve($bill, $uid);
    $bill->refresh();
    $entry = JournalEntry::with('lines')->find($bill->journal_entry_id);

    check('approving posts one balanced entry', $entry && $entry->is_posted && $entry->isBalanced()
        && (float) $entry->total_debit === 200000.5);
    check('dated on the bill date, typed purchase, pointing at the bill', $entry->entry_date->toDateString() === '2026-09-10'
        && $entry->journal_type === 'purchase' && $entry->reference_type === 'supplier_bill' && (int) $entry->reference_id === $bill->id);
    $debits = $entry->lines->where('debit', '>', 0)->mapWithKeys(fn ($l) => [$l->account_id => (float) $l->debit])->all();
    $expectDebits = [$accountOf($catA) => 120000.0];
    $expectDebits[$accountOf($catB)] = ($expectDebits[$accountOf($catB)] ?? 0) + 80000.5;
    check("each line debits its category's expense account", $debits == $expectDebits,
        json_encode($debits) . ' vs ' . json_encode($expectDebits));
    check('the whole total is credited to 40 Fournisseurs',
        (float) $entry->lines->where('account_id', $payable)->sum('credit') === 200000.5);
    check('account 40 now carries the debt', round(creditBalance($payable) - $before['payable'], 2) === 200000.5);
    check('the bill is approved and keeps the accounts it posted to', $bill->isApproved()
        && (int) $bill->payable_account_id === $payable && $bill->lines->every(fn ($l) => $l->account_id && $l->journal_entry_line_id));
    check('it is owed in full and reads unpaid', $ap->owedOn($bill) === 200000.5 && $ap->paymentState($bill) === 'unpaid');
    check('approving twice is refused', refusal(fn () => $ap->approve($bill, $uid)) !== null);
    check('an approved bill cannot be edited', refusal(fn () => $ap->saveDraft(['supplier_id' => $supplier->id,
        'bill_date' => '2026-09-10', 'lines' => [['expense_category_id' => $catA['id'], 'amount' => 1]]], $uid, $bill)) !== null);
    check('an approved bill cannot be deleted', refusal(fn () => $ap->deleteDraft($bill)) !== null);

    $dup = $ap->saveDraft(['supplier_id' => $supplier->id, 'supplier_reference' => ' inv-77 ', 'bill_date' => '2026-09-12',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 10]]], $uid);
    $entriesNow = JournalEntry::count();
    check('the same supplier invoice cannot be entered twice (case and spaces ignored)',
        refusal(fn () => $ap->approve($dup, $uid)) !== null && $dup->fresh()->isDraft() && JournalEntry::count() === $entriesNow);
    $ap->deleteDraft($dup);
    $sameRefOther = $ap->saveDraft(['supplier_id' => $other->id, 'supplier_reference' => 'INV-77', 'bill_date' => '2026-09-12',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 10]]], $uid);
    check('another supplier may use the same invoice number', refusal(fn () => $ap->approve($sameRefOther, $uid)) === null);

    echo "\nThe budget sheet and the daybook\n";
    $after = $actuals->forPeriod(...$window)['lines'];
    $expectA = $before['lineA'] + 120000 + ($catA['line_id'] === $catB['line_id'] ? 80000.5 : 0) + 10;
    check("line A moves by its bill lines (incl. the other supplier's 10)", round(($after[$catA['line_id']] ?? 0), 2) === round($expectA, 2),
        ($after[$catA['line_id']] ?? 0) . ' vs ' . $expectA);
    if ($catA['line_id'] !== $catB['line_id']) {
        check('line B moves by its bill line', round(($after[$catB['line_id']] ?? 0) - $before['lineB'], 2) === 80000.5);
    }
    $book = $daybook->totalsByLine(...$window)['lines'];
    $sheet = $actuals->forPeriod(...$window)['lines'];
    $mismatch = [];
    foreach (array_unique(array_merge(array_keys($book), array_keys($sheet))) as $line) {
        if (abs(($book[$line] ?? 0) - ($sheet[$line] ?? 0)) > 0.01) {
            $mismatch[] = $line;
        }
    }
    check('the daybook and the budget actuals still agree line by line', $mismatch === [], 'lines ' . implode(',', $mismatch));
    $rows = collect($daybook->rows(...$window))->where('source', 'supplier_bill');
    check('the daybook shows each bill line as its own row on the bill date',
        $rows->where('ref', $bill->number)->count() === 2 && $rows->where('ref', $bill->number)->every(fn ($r) => $r['date'] === '2026-09-10'));
    $reconNow = collect($recon->reconcile(...$window)['sections'])->keyBy('section')->map(fn ($s) => $s['unexplained'])->all();
    check('the sheet-versus-ledger reconciliation does not move', $reconNow == $before['recon'],
        json_encode($reconNow) . ' vs ' . json_encode($before['recon']));

    echo "\nPayments\n";
    $bill2 = $ap->approve($ap->saveDraft(['supplier_id' => $supplier->id, 'supplier_reference' => 'INV-80',
        'bill_date' => '2026-08-01', 'lines' => [['expense_category_id' => $catA['id'], 'amount' => 50000]]], $uid), $uid);
    check('bill 2 falls due on 31 Aug, before bill 1', $bill2->due_date->toDateString() === '2026-08-31');

    $cashBefore = creditBalance($cash);
    $p1 = $ap->pay(['supplier_id' => $supplier->id, 'payment_date' => '2026-10-01', 'amount' => 70000,
        'payment_method' => 1, 'cash_account_id' => $cash, 'allocations' => [$bill->id => 70000]], $uid);
    $pe = JournalEntry::with('lines')->find($p1->journal_entry_id);
    check('a payment posts debit 40, credit the cash account',
        $pe && $pe->is_posted && (float) $pe->lines->where('account_id', $payable)->sum('debit') === 70000.0
        && (float) $pe->lines->where('account_id', $cash)->sum('credit') === 70000.0 && $pe->journal_type === 'cash');
    check('the cash account goes down by the payment', round(creditBalance($cash) - $cashBefore, 2) === 70000.0);
    check('bill 1 is part paid with 130,000.50 owed', $ap->owedOn($bill->fresh()) === 130000.5
        && $ap->paymentState($bill->fresh()) === 'part_paid');
    check('payments are numbered SP-2026-NNNN', preg_match('/^SP-2026-\d{4}$/', $p1->number) === 1, $p1->number);

    check('allocating more than is owed is refused', refusal(fn () => $ap->pay(['supplier_id' => $supplier->id,
        'payment_date' => '2026-10-02', 'amount' => 60000, 'cash_account_id' => $cash,
        'allocations' => [$bill2->id => 60000]], $uid)) !== null);
    check('allocations that do not add up to the payment are refused', refusal(fn () => $ap->pay(['supplier_id' => $supplier->id,
        'payment_date' => '2026-10-02', 'amount' => 30000, 'cash_account_id' => $cash,
        'allocations' => [$bill2->id => 20000]], $uid)) !== null);
    check("another supplier's bill cannot be paid from here", refusal(fn () => $ap->pay(['supplier_id' => $supplier->id,
        'payment_date' => '2026-10-02', 'amount' => 10, 'cash_account_id' => $cash,
        'allocations' => [$sameRefOther->id => 10]], $uid)) !== null);
    check('paying a bill before its date is refused', refusal(fn () => $ap->pay(['supplier_id' => $supplier->id,
        'payment_date' => '2026-09-05', 'amount' => 10, 'cash_account_id' => $cash,
        'allocations' => [$bill->id => 10]], $uid)) !== null);
    check('a payment dated in the future is refused', refusal(fn () => $ap->pay(['supplier_id' => $supplier->id,
        'payment_date' => now()->addDay()->toDateString(), 'amount' => 10, 'cash_account_id' => $cash], $uid)) !== null);
    check('paying from an account that is not bank or cash is refused', refusal(fn () => $ap->pay(['supplier_id' => $supplier->id,
        'payment_date' => '2026-10-02', 'amount' => 10, 'cash_account_id' => $accountOf($catA)], $uid)) !== null);
    check('paying more than is owed in total is refused (no advances yet)', refusal(fn () => $ap->pay(['supplier_id' => $supplier->id,
        'payment_date' => '2026-10-02', 'amount' => 500000, 'cash_account_id' => $cash], $uid)) !== null);

    // Without allocations: oldest due first. Bill 2 (due 31 Aug) takes 50,000,
    // the remaining 10,000 goes to bill 1.
    $p2 = $ap->pay(['supplier_id' => $supplier->id, 'payment_date' => '2026-10-15', 'amount' => 60000,
        'payment_method' => 2, 'cash_account_id' => $cash], $uid);
    $alloc = $p2->allocations->mapWithKeys(fn ($a) => [$a->supplier_bill_id => (float) $a->amount])->all();
    check('without allocations the oldest bill due is paid first', $alloc == [$bill2->id => 50000.0, $bill->id => 10000.0], json_encode($alloc));
    check('bill 2 now reads paid', $ap->paymentState($bill2->fresh()) === 'paid');
    check('account 40 carries exactly what the bills still owe',
        round(creditBalance($payable) - $before['payable'], 2) === round(200000.5 + 10 + 50000 - 70000 - 60000, 2));

    echo "\nVoids\n";
    check('a bill with payments against it cannot be voided', refusal(fn () => $ap->voidBill($bill->fresh(), $uid)) !== null);

    $ap->voidPayment($p1, $uid, 'Cheque bounced');
    $p1->refresh();
    $rev = JournalEntry::with('lines')->find($p1->void_journal_entry_id);
    check('voiding a payment posts its mirror image', $rev && $rev->is_posted
        && (float) $rev->lines->where('account_id', $cash)->sum('debit') === 70000.0
        && (int) $rev->reversed_entry_id === (int) $p1->journal_entry_id && JournalEntry::find($p1->journal_entry_id)->is_reversed);
    check('dated in the original month while that month is open', $rev->entry_date->toDateString() === '2026-10-01');
    check('the row stays, marked void, with its reason', SupplierPayment::whereKey($p1->id)->exists() && $p1->isVoid()
        && $p1->void_reason === 'Cheque bounced');
    check('the bill it paid is owed again', $ap->owedOn($bill->fresh()) === 190000.5);
    check('voiding twice is refused', refusal(fn () => $ap->voidPayment($p1, $uid)) !== null);

    echo "\nAs of a date, aging and statements\n";
    // The void of payment 1 was dated back into October (still open), so the
    // ledger no longer holds that payment on any October date: as of 5 Oct the
    // bill was owed in full.
    $hist = $ap->openBills($supplier->id, '2026-10-05')->keyBy('id');
    check('as of 5 Oct, bill 1 is owed in full: the void was dated back into its open month',
        isset($hist[$bill->id]) && $hist[$bill->id]->owed === 200000.5, json_encode($hist[$bill->id]->owed ?? null));
    check('as of 10 Oct bill 2 was still owed; by 20 Oct it was paid (payment of 15 Oct)',
        ($ap->openBills($supplier->id, '2026-10-10')->keyBy('id')[$bill2->id]->owed ?? null) === 50000.0
        && !$ap->openBills($supplier->id, '2026-10-20')->contains('id', $bill2->id));
    check('as of 5 Sep, bill 1 did not exist yet', !isset($ap->openBills($supplier->id, '2026-09-05')->keyBy('id')[$bill->id]));

    $aging = $ap->aging('2026-11-24');
    $row = collect($aging['suppliers'])->firstWhere('supplier_id', $supplier->id);
    // Bill 1, due 10 Oct, is 45 days overdue on 24 Nov: 31-60. Bill 2 is paid.
    check('the aging puts bill 1 in 31-60 days', $row && $row['d31_60'] === 190000.5 && $row['total'] === 190000.5,
        json_encode($row ? array_intersect_key($row, array_flip(SupplierPayables::BUCKETS)) : null));
    check('aging ties to account 40: its gap is what it was before the test', $aging['difference'] === $before['gap'],
        $aging['difference'] . ' vs ' . $before['gap']);
    $early = collect($ap->aging('2026-09-20')['suppliers'])->firstWhere('supplier_id', $supplier->id);
    check('as of 20 Sep, bill 1 is not yet due and bill 2 is 1-30 days overdue',
        $early && $early['not_due'] === 200000.5 && $early['d1_30'] === 50000.0, json_encode($early ? [$early['not_due'], $early['d1_30']] : null));

    $st = $ap->statement($supplier->id, '2026-09-01', '2026-11-24');
    $owedNow = round($ap->openBills($supplier->id)->sum('owed'), 2);
    check('the statement closes on what is owed now', $st['closing'] === $owedNow, $st['closing'] . ' vs ' . $owedNow);
    check('its opening balance on 1 Sep is bill 2 (dated 1 Aug)', $st['opening'] === 50000.0, (string) $st['opening']);
    check('its running balance ends on the closing figure', end($st['rows'])['balance'] === $st['closing']);
    $stOct = $ap->statement($supplier->id, null, '2026-10-05');
    check('a statement to 5 Oct closes on what was owed that day',
        $stOct['closing'] === round($ap->openBills($supplier->id, '2026-10-05')->sum('owed'), 2), (string) $stOct['closing']);

    $ap->voidPayment($p2, $uid);
    $ap->voidBill($bill->fresh(), $uid, 'Entered in error');
    $bill->refresh();
    $vrev = JournalEntry::with('lines')->find($bill->void_journal_entry_id);
    check('voiding the bill posts its mirror image', $vrev && $vrev->is_posted
        && (float) $vrev->lines->where('account_id', $payable)->sum('debit') === 200000.5);
    check('the bill is void and owes nothing', $bill->isVoid() && $ap->owedOn($bill) == 0.0
        && !$ap->openBills($supplier->id)->contains('id', $bill->id));
    check("its lines are tied to the void's journal lines", $bill->lines->every(fn ($l) => $l->void_journal_entry_line_id));
    $lineAfterVoid = $actuals->forPeriod(...$window)['lines'][$catA['line_id']] ?? 0;
    $expectAfterVoid = $before['lineA'] + 10 + 50000 + ($catA['line_id'] === $catB['line_id'] ? 0 : 0);
    check('the voided bill comes back off the budget line', round($lineAfterVoid, 2) === round($expectAfterVoid, 2),
        $lineAfterVoid . ' vs ' . $expectAfterVoid);
    $voidRows = collect($daybook->rows(...$window))->where('source', 'supplier_bill')->where('ref', $bill->number);
    check('the daybook shows the void as rows coming back in', $voidRows->where('direction', 'in')->count() === 2);
    check('the reconciliation still has not moved', collect($recon->reconcile(...$window)['sections'])->keyBy('section')
        ->map(fn ($s) => $s['unexplained'])->all() == $before['recon']);

    echo "\nOpening balances\n";
    $lineSnapshot = $actuals->forPeriod(...$window)['lines'];
    $opening = $ap->approve($ap->saveDraft(['supplier_id' => $other->id, 'kind' => 'opening_balance',
        'bill_date' => '2026-01-01', 'opening_account_id' => $equity, 'lines' => [['amount' => 300000]]], $uid), $uid);
    $oe = JournalEntry::with('lines')->find($opening->journal_entry_id);
    check('an opening balance posts debit equity, credit 40',
        (float) $oe->lines->where('account_id', $equity)->sum('debit') === 300000.0
        && (float) $oe->lines->where('account_id', $payable)->sum('credit') === 300000.0);
    check('it never reaches the budget sheet', $actuals->forPeriod(...$window)['lines'] == $lineSnapshot);
    check('it ages like any bill', collect($ap->aging('2026-11-24')['suppliers'])->firstWhere('supplier_id', $other->id)['over_90'] === 300000.0);

    echo "\nClosed periods and missing fiscal years\n";
    $period = App\Models\FiscalYear::periodForDate('2026-03-15');
    $period->is_closed = true;
    $period->save();
    $closedDraft = $ap->saveDraft(['supplier_id' => $supplier->id, 'bill_date' => '2026-03-15',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 10]]], $uid);
    $count = JournalEntry::count();
    check('a bill dated in a closed month is refused, with nothing written',
        refusal(fn () => $ap->approve($closedDraft, $uid)) !== null && $closedDraft->fresh()->isDraft() && JournalEntry::count() === $count);
    $oldDraft = $ap->saveDraft(['supplier_id' => $supplier->id, 'bill_date' => '2024-05-01',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 10]]], $uid);
    $msg = refusal(fn () => $ap->approve($oldDraft, $uid));
    check('a bill dated where no fiscal year exists is refused, saying why', $msg !== null && str_contains($msg, 'fiscal year'), (string) $msg);

    echo "\nCash boxes (payment accounts)\n";
    $box = PaymentAccount::create(['title' => 'TEST box', 'account_number' => 'T-1',
        'account_type_id' => DB::table('payment_account_types')->value('id'), 'chart_of_account_id' => $cash,
        'opening_balance' => 100000, 'current_balance' => 100000, 'status' => 1]);
    $bill3 = $ap->approve($ap->saveDraft(['supplier_id' => $supplier->id, 'bill_date' => '2026-11-01',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 150000]]], $uid), $uid);
    check('paying more than the cash box holds is refused', refusal(fn () => $ap->pay(['supplier_id' => $supplier->id,
        'payment_date' => '2026-11-02', 'amount' => 120000, 'payment_account_id' => $box->id], $uid)) !== null);
    $p3 = $ap->pay(['supplier_id' => $supplier->id, 'payment_date' => '2026-11-02', 'amount' => 40000,
        'payment_method' => 1, 'payment_account_id' => $box->id], $uid);
    check('paying from a cash box takes its ledger account', (int) $p3->cash_account_id === $cash);
    check('and records the money leaving the box', (float) $box->fresh()->current_balance === 60000.0
        && PaymentAccountTransaction::where('reference_type', 'supplier_payment')->where('reference_id', $p3->id)
            ->where('transaction_type', 'debit')->where('amount', 40000)->exists());
    $ap->voidPayment($p3, $uid);
    check('voiding it puts the money back in the box', (float) $box->fresh()->current_balance === 100000.0
        && PaymentAccountTransaction::where('reference_type', 'supplier_payment_reversal')->where('reference_id', $p3->id)
            ->where('transaction_type', 'credit')->exists());

    echo "\nPreviews: shown before, nothing written, and exactly what confirming does\n";
    $preview = app(App\Services\Accounting\SupplierPayablesPreview::class);
    $footprint = fn () => [
        JournalEntry::count(), DB::table('journal_entry_lines')->count(), SupplierPayment::count(),
        DB::table('supplier_payment_allocations')->count(), DB::table('audit_logs')->count(),
        DB::table('payment_account_transactions')->count(), (float) $box->fresh()->current_balance,
        SupplierBill::where('status', 'draft')->count(), SupplierBill::where('status', 'approved')->count(),
    ];

    $pdraft = $ap->saveDraft(['supplier_id' => $supplier->id, 'supplier_reference' => 'PV-1', 'bill_date' => '2026-11-05',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 70000], ['expense_category_id' => $catB['id'], 'amount' => 5000]]], $uid);
    $mark = $footprint();
    $pv = $preview->approve($pdraft->fresh(['supplier', 'lines.category']), $uid);
    check('an approval preview writes nothing at all (ledger, bills, audit log)', $footprint() === $mark, json_encode([$mark, $footprint()]));
    check('it is clear to confirm and shows one balanced entry', $pv['ok'] && count($pv['entries']) === 1
        && $pv['entries'][0]['debit'] === 75000.0 && $pv['entries'][0]['credit'] === 75000.0);
    check('and the budget lines it touches', count($pv['effects'][0]['rows'] ?? []) >= 1);
    $previewLines = collect($pv['entries'][0]['lines'])->map(fn ($l) => [$l['account'], $l['debit'], $l['credit']])->all();
    $real = $ap->approve($pdraft, $uid);
    $realLines = JournalEntry::with('lines.account')->find($real->journal_entry_id)->lines
        ->map(fn ($l) => [($l->account->account_code ?? '') . ' ' . ($l->account->account_name ?? ''), (float) $l->debit, (float) $l->credit])->all();
    check('confirming posts exactly the lines the preview showed', $previewLines === $realLines,
        json_encode($previewLines) . ' vs ' . json_encode($realLines));

    $dupDraft = $ap->saveDraft(['supplier_id' => $supplier->id, 'supplier_reference' => 'pv-1', 'bill_date' => '2026-11-06',
        'lines' => [['expense_category_id' => $catA['id'], 'amount' => 1]]], $uid);
    $pv = $preview->approve($dupDraft->fresh(['supplier', 'lines.category']), $uid);
    check('a preview of a duplicate invoice is refused, saying why, with nothing to confirm',
        !$pv['ok'] && str_contains(implode(' ', $pv['problems']), 'already entered') && $pv['entries'] === []);
    $ap->deleteDraft($dupDraft);

    $closedPv = $preview->approve($closedDraft->fresh(['supplier', 'lines.category']), $uid);
    check('a preview in a closed month is refused', !$closedPv['ok']);

    $payData = ['supplier_id' => $supplier->id, 'payment_date' => '2026-11-10', 'amount' => 30000, 'payment_method' => 1,
        'payment_account_id' => $box->id, 'reference' => 'PV-CHQ', 'allocations' => [$real->id => 30000]];
    $mark = $footprint();
    $pv = $preview->pay($payData, $uid);
    check('a payment preview writes nothing, cash box untouched', $footprint() === $mark);
    $billRow = $pv['effects'][0]['rows'][0] ?? [];
    check('it shows the bill before and after', $pv['ok'] && ($billRow[2] ?? '') === format_amount(75000)
        && ($billRow[4] ?? '') === format_amount(45000), json_encode($billRow));
    check('and the cash box balance before and after', ($pv['effects'][1]['rows'][0][1] ?? '') === format_amount(100000)
        && ($pv['effects'][1]['rows'][0][2] ?? '') === format_amount(70000));
    $realPay = $ap->pay($payData, $uid);
    $realPayLines = JournalEntry::with('lines.account')->find($realPay->journal_entry_id)->lines
        ->map(fn ($l) => [($l->account->account_code ?? '') . ' ' . ($l->account->account_name ?? ''), (float) $l->debit, (float) $l->credit])->all();
    check('confirming the payment posts exactly what was previewed',
        collect($pv['entries'][0]['lines'])->map(fn ($l) => [$l['account'], $l['debit'], $l['credit']])->all() === $realPayLines);

    $over = $preview->pay(['supplier_id' => $supplier->id, 'payment_date' => '2026-11-10', 'amount' => 999999,
        'payment_method' => 1, 'cash_account_id' => $cash, 'allocations' => [$real->id => 999999]], $uid);
    check('an overpayment preview is refused with the reason', !$over['ok'] && $over['problems'] !== []);

    $mark = $footprint();
    $pv = $preview->voidPayment($realPay->fresh(), $uid);
    check('a void preview writes nothing', $footprint() === $mark && !$realPay->fresh()->isVoid());
    check('and shows the reversing entry and the bill owed again', $pv['ok'] && $pv['entries'][0]['debit'] === 30000.0
        && ($pv['effects'][0]['rows'][0][2] ?? '') === format_amount(75000));
    $pv = $preview->voidBill($real->fresh(), $uid);
    check('voiding a bill with a payment is refused in the preview too', !$pv['ok']);

    echo "\nNothing left behind\n";
    $unbalanced = JournalEntry::whereIn('reference_type', ['supplier_bill', 'supplier_bill_reversal', 'supplier_payment', 'supplier_payment_reversal'])
        ->get()->filter(fn ($e) => !$e->isBalanced() || !$e->is_posted)->count();
    check('every supplier entry written is posted and balanced', $unbalanced === 0, "$unbalanced not");
    $orphans = DB::table('supplier_bills')->where('status', 'approved')->whereNull('journal_entry_id')->count()
        + DB::table('supplier_payments')->whereNull('journal_entry_id')->count();
    check('no approved bill or payment is without its entry', $orphans === 0);
} finally {
    DB::rollBack();
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
