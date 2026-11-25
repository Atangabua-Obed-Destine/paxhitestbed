<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\ItemSupplier;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Supplier bills and payments: every rule, in one place.
 *
 * The ledger
 *   approve a bill   debit each line's expense account, credit 40 (the debt)
 *   void a bill      the same entry reversed
 *   pay              debit 40, credit the bank or cash account
 *   void a payment   the same entry reversed
 *
 * Every posting is written in the same database transaction as the record it
 * belongs to, so a bill can never be approved without its entry or the other
 * way round.
 *
 * What is owed
 *   One definition, owedQuery(), used by every screen and report: a bill's
 *   total less its allocations from payments that have not been voided. There
 *   is no stored "paid" flag. A flag that several code paths write is how the
 *   amount owed and the payments drift apart, and a payment allocated by a
 *   drifted flag goes to the wrong bill.
 *
 * Dates
 *   "As of" a date means as the ledger stood that day: a bill counts from the
 *   date of its posting until the date of its reversal, a payment likewise. So
 *   the aging report and the supplier statement for any past date agree with
 *   the balance of account 40 on that date.
 *
 * The budget sheet
 *   An approved bill is spending against its budget line on the bill date,
 *   the day the ledger records the cost. The sheet and the ledger then agree
 *   by construction (see budgetLedgerLines()). Paying the bill moves money
 *   from the bank to the supplier and is not a second cost.
 */
class SupplierPayables
{
    use Concerns\PostsJournalEntries;

    public const MAPPING_TYPE = 'expense_category';
    public const SETTING_PAYABLE = 'supplier_payable_account_id';

    /** Rounding tolerance, as elsewhere in the accounts. */
    private const CENT = 0.005;

    /* ===================================================================
     |  Settings
     |===================================================================*/

    /** The liability account supplier debts are owed on (40 Fournisseurs as installed). */
    public function payableAccountId(): ?int
    {
        $value = DB::table('accounting_settings')->where('setting_key', self::SETTING_PAYABLE)->value('setting_value');

        if ($value && ChartOfAccount::whereKey((int) $value)->exists()) {
            return (int) $value;
        }

        // 401 on the standard chart; 40 was the supplier account before renumbering.
        $fallback = ChartOfAccount::where('account_code', \App\Console\Commands\ChartSyscohada::current('40'))->value('id');

        return $fallback ? (int) $fallback : null;
    }

    public function setPayableAccount(int $accountId): void
    {
        $account = ChartOfAccount::find($accountId);

        if (!$account || (int) $account->class_number !== 4 || $account->account_category !== 'detail') {
            throw new \RuntimeException(__('Supplier debts have to be owed on a third-party (class 4) account that is not a heading.'));
        }

        DB::table('accounting_settings')->updateOrInsert(
            ['setting_key' => self::SETTING_PAYABLE],
            ['setting_value' => (string) $accountId, 'setting_type' => 'integer', 'category' => 'payables',
             'description' => 'Liability account supplier bills are owed on', 'updated_at' => now()]
        );
    }

    /** Accounts a supplier debt may be owed on. */
    public function payableAccounts()
    {
        return ChartOfAccount::where('class_number', 4)->where('account_category', 'detail')
            ->where('is_active', true)->orderBy('account_code')->get();
    }

    /** Accounts an opening balance may be set against (equity, class 1). */
    public function openingBalanceAccounts()
    {
        return ChartOfAccount::where('class_number', 1)->where('account_category', 'detail')
            ->where('is_active', true)->orderBy('account_code')->get();
    }

    /** Bank and cash accounts a supplier may be paid from. */
    public function cashAccounts()
    {
        return ChartOfAccount::where('class_number', 5)->where('account_category', 'detail')
            ->where('is_active', true)->where('account_code', 'not like', '59%')
            ->orderBy('account_code')->get();
    }

    /* ===================================================================
     |  What is owed: the one definition
     |===================================================================*/

    /**
     * Bills with what has been paid on them and what is still owed.
     *
     * Without $asOf: the bills approved now, with every live payment. With
     * $asOf: the bills that stood on the ledger that day, with the payments
     * that stood that day. Columns added: paid, owed.
     */
    public function owedQuery(?string $asOf = null): Builder
    {
        $paid = DB::table('supplier_payment_allocations as a')
            ->join('supplier_payments as p', 'p.id', '=', 'a.supplier_payment_id')
            ->leftJoin('journal_entries as pv', 'pv.id', '=', 'p.void_journal_entry_id')
            ->whereColumn('a.supplier_bill_id', 'b.id');

        if ($asOf) {
            $paid->whereDate('p.payment_date', '<=', $asOf)
                ->where(fn ($q) => $q->whereNull('p.voided_at')
                    ->orWhereDate('pv.entry_date', '>', $asOf));
        } else {
            $paid->whereNull('p.voided_at');
        }

        $paid->selectRaw('COALESCE(SUM(a.amount), 0)');

        $query = DB::table('supplier_bills as b')
            ->join('journal_entries as bj', 'bj.id', '=', 'b.journal_entry_id')
            ->leftJoin('journal_entries as bv', 'bv.id', '=', 'b.void_journal_entry_id')
            ->leftJoin('item_suppliers as s', 's.id', '=', 'b.supplier_id')
            ->select('b.*', 's.title as supplier_name')
            ->selectSub($paid, 'paid');

        if ($asOf) {
            $query->whereDate('bj.entry_date', '<=', $asOf)
                ->where(fn ($q) => $q->where('b.status', SupplierBill::APPROVED)
                    ->orWhere(fn ($q) => $q->where('b.status', SupplierBill::VOID)->whereDate('bv.entry_date', '>', $asOf)));
        } else {
            $query->where('b.status', SupplierBill::APPROVED);
        }

        return $query;
    }

    /**
     * The bills with something still owed, oldest due first.
     *
     * @return \Illuminate\Support\Collection<int, object> each with paid and owed
     */
    public function openBills(?int $supplierId = null, ?string $asOf = null)
    {
        return $this->owedQuery($asOf)
            ->when($supplierId, fn ($q) => $q->where('b.supplier_id', $supplierId))
            ->orderBy('b.due_date')->orderBy('b.bill_date')->orderBy('b.id')
            ->get()
            ->map(function ($bill) {
                $bill->paid = round((float) $bill->paid, 2);
                $bill->owed = round((float) $bill->total - $bill->paid, 2);

                return $bill;
            })
            ->filter(fn ($bill) => abs($bill->owed) > self::CENT)
            ->values();
    }

    /** What is owed on one bill now. */
    public function owedOn(SupplierBill $bill): float
    {
        if (!$bill->isApproved()) {
            return 0.0;
        }

        $paid = (float) SupplierPaymentAllocation::where('supplier_bill_id', $bill->id)
            ->whereHas('payment', fn ($q) => $q->whereNull('voided_at'))
            ->sum('amount');

        return round((float) $bill->total - $paid, 2);
    }

    /** 'draft', 'unpaid', 'part_paid', 'paid' or 'void', worked out from the payments. */
    public function paymentState(SupplierBill $bill, ?float $owed = null): string
    {
        if ($bill->isDraft()) {
            return 'draft';
        }
        if ($bill->isVoid()) {
            return 'void';
        }

        $owed ??= $this->owedOn($bill);

        if ($owed <= self::CENT) {
            return 'paid';
        }

        return $owed < (float) $bill->total - self::CENT ? 'part_paid' : 'unpaid';
    }

    /* ===================================================================
     |  Bills
     |===================================================================*/

    /**
     * Create or update a draft bill.
     *
     * $data: supplier_id, kind, supplier_reference, bill_date, due_date (may be
     * empty: worked out from the supplier's terms), description, attach,
     * opening_account_id (opening balance only), lines: [[description,
     * expense_category_id, amount], ...] (for an opening balance: one amount).
     */
    public function saveDraft(array $data, int $userId, ?SupplierBill $bill = null): SupplierBill
    {
        if ($bill && !$bill->isDraft()) {
            throw new \RuntimeException(__('Only a draft bill can be changed. Void an approved bill and enter it again.'));
        }

        $supplier = ItemSupplier::find($data['supplier_id'] ?? null);
        if (!$supplier) {
            throw new \RuntimeException(__('Choose the supplier.'));
        }

        $kind = ($data['kind'] ?? SupplierBill::KIND_BILL) === SupplierBill::KIND_OPENING
            ? SupplierBill::KIND_OPENING : SupplierBill::KIND_BILL;

        $billDate = Carbon::parse($data['bill_date'])->toDateString();
        $dueDate = !empty($data['due_date'])
            ? Carbon::parse($data['due_date'])->toDateString()
            : Carbon::parse($billDate)->addDays((int) ($supplier->payment_terms_days ?? 0))->toDateString();

        if ($dueDate < $billDate) {
            throw new \RuntimeException(__('The due date cannot be before the bill date.'));
        }

        $lines = $this->cleanLines($data, $kind);

        return DB::transaction(function () use ($data, $userId, $bill, $supplier, $kind, $billDate, $dueDate, $lines) {
            $total = round(array_sum(array_column($lines, 'amount')), 2);

            $bill ??= new SupplierBill([
                'number' => $this->nextNumber('SB', 'supplier_bills', $billDate),
                'status' => SupplierBill::DRAFT,
                'created_by' => $userId,
            ]);

            $bill->fill([
                'supplier_id' => $supplier->id,
                'kind' => $kind,
                'supplier_reference' => trim((string) ($data['supplier_reference'] ?? '')) ?: null,
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'description' => $data['description'] ?? null,
                'total' => $total,
                'updated_by' => $userId,
            ]);

            if (array_key_exists('attach', $data) && $data['attach']) {
                $bill->attach = $data['attach'];
            }

            $bill->save();

            $bill->lines()->delete();
            foreach ($lines as $i => $line) {
                SupplierBillLine::create($line + ['supplier_bill_id' => $bill->id, 'line_number' => $i + 1]);
            }

            return $bill->fresh('lines');
        });
    }

    /** Validate and normalise the lines a form sent. */
    private function cleanLines(array $data, string $kind): array
    {
        if ($kind === SupplierBill::KIND_OPENING) {
            $amount = round((float) ($data['lines'][0]['amount'] ?? $data['amount'] ?? 0), 2);
            $account = ChartOfAccount::find($data['opening_account_id'] ?? null);

            if ($amount <= 0) {
                throw new \RuntimeException(__('An opening balance has to be more than zero.'));
            }
            if (!$account || (int) $account->class_number !== 1 || $account->account_category !== 'detail') {
                throw new \RuntimeException(__('Choose the equity (class 1) account the opening balance is set against.'));
            }

            return [[
                'description' => $data['lines'][0]['description'] ?? __('Opening balance'),
                'expense_category_id' => null,
                'account_id' => $account->id,
                'amount' => $amount,
            ]];
        }

        $lines = [];
        $choices = new BudgetLineChoices();

        foreach ($data['lines'] ?? [] as $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $categoryId = $line['expense_category_id'] ?? null;
            $description = trim((string) ($line['description'] ?? ''));

            // A row the form added and left empty.
            if ($amount == 0.0 && !$categoryId && $description === '') {
                continue;
            }

            if ($amount <= 0) {
                throw new \RuntimeException(__('Every line needs an amount above zero.'));
            }
            if (!$choices->isUsable(self::MAPPING_TYPE, $categoryId)) {
                throw new \RuntimeException(__('Every line needs an expense category that is filed under a budget line.'));
            }

            $lines[] = [
                'description' => $description ?: null,
                'expense_category_id' => (int) $categoryId,
                'account_id' => null,
                'amount' => $amount,
            ];
        }

        if (!$lines) {
            throw new \RuntimeException(__('A bill needs at least one line.'));
        }

        return $lines;
    }

    /** Delete a draft. A draft has posted nothing, so nothing is left behind. */
    public function deleteDraft(SupplierBill $bill): void
    {
        if (!$bill->isDraft()) {
            throw new \RuntimeException(__('Only a draft bill can be deleted. Void an approved bill instead.'));
        }

        DB::transaction(function () use ($bill) {
            $bill->lines()->delete();
            $bill->delete();
        });
    }

    /** Another live bill from the same supplier under the same invoice number, if any. */
    public function duplicateOf(SupplierBill $bill): ?SupplierBill
    {
        if (!$bill->supplier_reference) {
            return null;
        }

        return SupplierBill::where('supplier_id', $bill->supplier_id)
            ->where('id', '!=', $bill->id)
            ->where('status', '!=', SupplierBill::VOID)
            ->whereRaw('LOWER(TRIM(supplier_reference)) = ?', [mb_strtolower(trim($bill->supplier_reference))])
            ->first();
    }

    /**
     * Approve a draft: post the cost and the debt on the bill date.
     *
     * @throws \RuntimeException with the reason, when it cannot be approved
     */
    public function approve(SupplierBill $bill, int $userId): SupplierBill
    {
        return DB::transaction(function () use ($bill, $userId) {
            $bill = SupplierBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (!$bill->isDraft()) {
                throw new \RuntimeException(__('That bill is not a draft, so it cannot be approved again.'));
            }

            $lines = $bill->lines()->get();
            if ($lines->isEmpty() || (float) $bill->total <= 0) {
                throw new \RuntimeException(__('A bill needs at least one line before it can be approved.'));
            }
            if (abs(round((float) $lines->sum('amount'), 2) - (float) $bill->total) > self::CENT) {
                throw new \RuntimeException(__('The lines do not add up to the bill total. Save the bill again.'));
            }

            if ($duplicate = $this->duplicateOf($bill)) {
                throw new \RuntimeException(__('Invoice :ref from this supplier is already entered as :number. One invoice cannot be entered twice.',
                    ['ref' => $bill->supplier_reference, 'number' => $duplicate->number]));
            }

            $payable = $this->payableAccountId();
            if (!$payable) {
                throw new \RuntimeException(__('No supplier account (40 Fournisseurs) is set. Set it under Supplier bills before approving.'));
            }

            // Each line's account, decided now. A category's mapping can change
            // later; the bill keeps the account it was posted to.
            foreach ($lines as $line) {
                if ($bill->isOpeningBalance()) {
                    continue;
                }

                $accountId = DB::table('default_account_mappings')
                    ->where('mapping_type', self::MAPPING_TYPE)
                    ->where('category_id', $line->expense_category_id)
                    ->value('debit_account_id');
                $account = $accountId ? ChartOfAccount::find($accountId) : null;

                if (!$account || (int) $account->class_number === 5 || (int) $account->class_number === 4) {
                    throw new \RuntimeException(__('The category ":category" has no expense account to post to. Set it under Accounting → Transaction Mappings.',
                        ['category' => $line->category->title ?? '#' . $line->expense_category_id]));
                }

                $line->account_id = $account->id;
                $line->save();
            }

            $date = $bill->bill_date->toDateString();
            $supplierName = $bill->supplier->title ?? '';
            $description = trim(__('Supplier bill') . ' ' . $bill->number . ' - ' . $supplierName
                . ($bill->supplier_reference ? ' (' . $bill->supplier_reference . ')' : ''));

            $entry = $this->newEntry($date, 'purchase', $description, 'supplier_bill', $bill->id, (float) $bill->total, $userId, $bill->supplier_reference);

            $n = 1;
            foreach ($lines as $line) {
                $jl = JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'line_number' => $n++,
                    'account_id' => $line->account_id,
                    'description' => trim(($line->description ?: ($line->category->title ?? __('Opening balance'))) . ' - ' . $supplierName),
                    'debit' => round((float) $line->amount, 2),
                    'credit' => 0,
                ]);
                $line->journal_entry_line_id = $jl->id;
                $line->save();
            }

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'line_number' => $n,
                'account_id' => $payable,
                'description' => __('Owed to') . ' ' . $supplierName . ' - ' . $bill->number,
                'debit' => 0,
                'credit' => round((float) $bill->total, 2),
            ]);

            $entry->post($userId);

            $bill->forceFill([
                'status' => SupplierBill::APPROVED,
                'payable_account_id' => $payable,
                'journal_entry_id' => $entry->id,
                'approved_by' => $userId,
                'approved_at' => now(),
            ])->save();

            return $bill;
        });
    }

    /**
     * Void an approved bill with a reversing entry.
     *
     * Refused while any live payment is allocated to it: void the payment
     * first, so the money paid is never left pointing at a bill that no
     * longer exists.
     */
    public function voidBill(SupplierBill $bill, int $userId, ?string $reason = null): SupplierBill
    {
        return DB::transaction(function () use ($bill, $userId, $reason) {
            $bill = SupplierBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if (!$bill->isApproved()) {
                throw new \RuntimeException(__('Only an approved bill can be voided. A draft can simply be deleted.'));
            }

            $paid = SupplierPaymentAllocation::where('supplier_bill_id', $bill->id)
                ->whereHas('payment', fn ($q) => $q->whereNull('voided_at'))
                ->count();
            if ($paid > 0) {
                throw new \RuntimeException(__('Payments are recorded against this bill. Void those payments first.'));
            }

            $original = JournalEntry::with('lines')->findOrFail($bill->journal_entry_id);
            [$reversal, $lineMap] = $this->reverseEntry($original, 'supplier_bill_reversal', $bill->id, $userId);

            foreach ($bill->lines as $line) {
                if ($line->journal_entry_line_id && isset($lineMap[$line->journal_entry_line_id])) {
                    $line->void_journal_entry_line_id = $lineMap[$line->journal_entry_line_id];
                    $line->save();
                }
            }

            $bill->forceFill([
                'status' => SupplierBill::VOID,
                'void_journal_entry_id' => $reversal->id,
                'voided_by' => $userId,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            return $bill;
        });
    }

    /* ===================================================================
     |  Payments
     |===================================================================*/

    /**
     * Pay a supplier: the one way money reaches a supplier.
     *
     * $data: supplier_id, payment_date, amount, payment_method, cash_account_id
     * and/or payment_account_id, reference, note, allocations [bill_id =>
     * amount] (optional: without it the oldest bills due are paid first).
     */
    public function pay(array $data, int $userId): SupplierPayment
    {
        $supplier = ItemSupplier::find($data['supplier_id'] ?? null);
        if (!$supplier) {
            throw new \RuntimeException(__('Choose the supplier.'));
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException(__('A payment has to be for more than zero.'));
        }

        $date = Carbon::parse($data['payment_date'])->toDateString();
        if ($date > now()->toDateString()) {
            throw new \RuntimeException(__('A payment cannot be dated in the future.'));
        }

        [$cashAccountId, $paymentAccount] = $this->resolveCashSide($data, $amount);

        return DB::transaction(function () use ($data, $userId, $supplier, $amount, $date, $cashAccountId, $paymentAccount) {
            // Lock this supplier's approved bills: two people paying the same
            // bill at once must not both see it as unpaid.
            SupplierBill::where('supplier_id', $supplier->id)->where('status', SupplierBill::APPROVED)
                ->lockForUpdate()->get(['id']);

            $open = $this->openBills($supplier->id)->keyBy('id');
            $allocations = $this->allocate($open, $amount, $data['allocations'] ?? null);

            foreach ($allocations as $billId => $part) {
                if ($open[$billId]->bill_date > $date) {
                    throw new \RuntimeException(__('Bill :number is dated after the payment. A bill cannot be paid before it was issued.',
                        ['number' => $open[$billId]->number]));
                }
            }

            $payment = SupplierPayment::create([
                'supplier_id' => $supplier->id,
                'number' => $this->nextNumber('SP', 'supplier_payments', $date),
                'payment_date' => $date,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? null,
                'cash_account_id' => $cashAccountId,
                'payment_account_id' => $paymentAccount->id ?? null,
                'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($allocations as $billId => $part) {
                SupplierPaymentAllocation::create([
                    'supplier_payment_id' => $payment->id,
                    'supplier_bill_id' => $billId,
                    'amount' => $part,
                ]);
            }

            // Debit each supplier account the paid bills are owed on (one, as
            // installed), credit the bank or cash.
            $byPayable = [];
            foreach ($allocations as $billId => $part) {
                $acc = (int) $open[$billId]->payable_account_id;
                $byPayable[$acc] = round(($byPayable[$acc] ?? 0) + $part, 2);
            }

            $cashCode = (string) ChartOfAccount::whereKey($cashAccountId)->value('account_code');
            $numbers = collect(array_keys($allocations))->map(fn ($id) => $open[$id]->number)->implode(', ');
            $description = trim(__('Supplier payment') . ' ' . $payment->number . ' - ' . $supplier->title . ' (' . $numbers . ')');

            $entry = $this->newEntry($date, str_starts_with($cashCode, '52') ? 'bank' : 'cash', $description,
                'supplier_payment', $payment->id, $amount, $userId, $payment->reference);

            $n = 1;
            foreach ($byPayable as $accountId => $part) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id, 'line_number' => $n++, 'account_id' => $accountId,
                    'description' => __('Paid to') . ' ' . $supplier->title, 'debit' => $part, 'credit' => 0,
                ]);
            }
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id, 'line_number' => $n, 'account_id' => $cashAccountId,
                'description' => $description, 'debit' => 0, 'credit' => $amount,
            ]);

            $entry->post($userId);
            $payment->journal_entry_id = $entry->id;

            if ($paymentAccount) {
                $payment->payment_account_transaction_id = $this->moveCashBox(
                    $paymentAccount, 'debit', $amount, $date,
                    __('Supplier payment') . ' - ' . $supplier->title, $description,
                    'supplier_payment', $payment->id, $data['payment_method'] ?? null, $payment->reference, $userId
                );
            }

            $payment->save();

            return $payment->fresh('allocations');
        });
    }

    /**
     * Split a payment over bills.
     *
     * Given allocations are checked; without them the oldest bills due are
     * paid first. Either way the parts add up to the payment exactly and no
     * bill receives more than is owed on it.
     *
     * @return array<int, float> bill id => amount
     */
    private function allocate($open, float $amount, ?array $requested): array
    {
        $owedTotal = round($open->sum('owed'), 2);

        if ($requested !== null) {
            $out = [];
            foreach ($requested as $billId => $part) {
                $part = round((float) $part, 2);
                if ($part == 0.0) {
                    continue;
                }
                if ($part < 0) {
                    throw new \RuntimeException(__('An amount allocated to a bill cannot be negative.'));
                }
                if (!isset($open[$billId])) {
                    throw new \RuntimeException(__('One of the bills chosen is not an unpaid bill of this supplier.'));
                }
                if ($part > $open[$billId]->owed + self::CENT) {
                    throw new \RuntimeException(__('More is allocated to bill :number than is owed on it (:owed).',
                        ['number' => $open[$billId]->number, 'owed' => format_amount($open[$billId]->owed)]));
                }
                $out[(int) $billId] = $part;
            }

            if (!$out) {
                throw new \RuntimeException(__('Allocate the payment to at least one bill.'));
            }
            if (abs(round(array_sum($out), 2) - $amount) > self::CENT) {
                throw new \RuntimeException(__('The amounts allocated to the bills (:allocated) do not add up to the payment (:amount).',
                    ['allocated' => format_amount(array_sum($out)), 'amount' => format_amount($amount)]));
            }

            return $out;
        }

        if ($amount > $owedTotal + self::CENT) {
            throw new \RuntimeException(__('That is more than is owed to this supplier (:owed). Payments in advance are not recorded here yet.',
                ['owed' => format_amount($owedTotal)]));
        }

        $out = [];
        $left = $amount;
        foreach ($open as $bill) {
            if ($left <= self::CENT) {
                break;
            }
            $part = round(min($left, $bill->owed), 2);
            $out[(int) $bill->id] = $part;
            $left = round($left - $part, 2);
        }

        return $out;
    }

    /**
     * The ledger account the money leaves, and the cash box when one is used.
     *
     * @return array{0:int, 1:?PaymentAccount}
     */
    private function resolveCashSide(array $data, float $amount): array
    {
        $paymentAccount = !empty($data['payment_account_id']) ? PaymentAccount::find($data['payment_account_id']) : null;

        if (!empty($data['payment_account_id']) && !$paymentAccount) {
            throw new \RuntimeException(__('That payment account no longer exists.'));
        }

        if ($paymentAccount) {
            $linked = app(CashAccounts::class)->forPaymentAccount($paymentAccount->id);
            if (!$linked) {
                throw new \RuntimeException(__('The payment account ":name" is not linked to a bank or cash account in the ledger. Link it first.',
                    ['name' => $paymentAccount->title]));
            }
            if (!empty($data['cash_account_id']) && (int) $data['cash_account_id'] !== $linked) {
                throw new \RuntimeException(__('The payment account ":name" is linked to a different ledger account from the one chosen.',
                    ['name' => $paymentAccount->title]));
            }
            if ((float) $paymentAccount->current_balance < $amount) {
                throw new \RuntimeException(__('insufficient_balance_in_account'));
            }

            return [$linked, $paymentAccount];
        }

        $account = ChartOfAccount::find($data['cash_account_id'] ?? null);
        if (!$account || !app(CashAccounts::class)->isCash($account->id) || $account->account_category !== 'detail') {
            throw new \RuntimeException(__('Choose the bank or cash account the money was paid from.'));
        }

        return [$account->id, null];
    }

    /** Void a payment with a reversing entry. The bills it paid are owed again. */
    public function voidPayment(SupplierPayment $payment, int $userId, ?string $reason = null): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $userId, $reason) {
            $payment = SupplierPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->isVoid()) {
                throw new \RuntimeException(__('That payment has already been voided.'));
            }

            $original = JournalEntry::with('lines')->findOrFail($payment->journal_entry_id);
            [$reversal] = $this->reverseEntry($original, 'supplier_payment_reversal', $payment->id, $userId);

            if ($payment->payment_account_id && $payment->payment_account_transaction_id) {
                $box = PaymentAccount::find($payment->payment_account_id);
                if ($box) {
                    $this->moveCashBox(
                        $box, 'credit', (float) $payment->amount, $reversal->entry_date->toDateString(),
                        __('Reversal') . ': ' . __('Supplier payment') . ' ' . $payment->number, $reversal->description,
                        'supplier_payment_reversal', $payment->id, $payment->payment_method, $payment->reference, $userId
                    );
                }
            }

            $payment->forceFill([
                'void_journal_entry_id' => $reversal->id,
                'voided_by' => $userId,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            return $payment;
        });
    }

    /** Record money moving in or out of a cash box, as the expense screen does. */
    private function moveCashBox(PaymentAccount $box, string $type, float $amount, string $date, string $title,
                                 string $description, string $refType, int $refId, $method, ?string $reference, int $userId): int
    {
        $box = PaymentAccount::whereKey($box->id)->lockForUpdate()->first();
        $balance = round((float) $box->current_balance + ($type === 'debit' ? -$amount : $amount), 2);

        $tx = PaymentAccountTransaction::create([
            'payment_account_id' => $box->id,
            'transaction_type' => $type,
            'amount' => $amount,
            'transaction_date' => $date,
            'title' => $title,
            'description' => $description,
            'payment_method' => $method,
            'payment_reference' => $reference,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'balance_after' => $balance,
            'created_by' => $userId,
        ]);

        $box->current_balance = $balance;
        $box->save();

        return $tx->id;
    }

    /* ===================================================================
     |  Reports
     |===================================================================*/

    public const BUCKETS = ['not_due', 'd1_30', 'd31_60', 'd61_90', 'over_90'];

    /**
     * What is owed to each supplier on a date, by how overdue it is.
     *
     * Overdue is counted from each bill's due date, so a bill entered without
     * terms is due on its own date rather than vanishing from the report.
     */
    public function aging(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->toDateString() : now()->toDateString();
        $bills = $this->openBills(null, $asOf);

        $suppliers = [];
        $totals = array_fill_keys(self::BUCKETS, 0.0) + ['total' => 0.0];

        foreach ($bills as $bill) {
            $days = Carbon::parse($bill->due_date)->diffInDays(Carbon::parse($asOf), false);
            $bucket = match (true) {
                $days <= 0 => 'not_due',
                $days <= 30 => 'd1_30',
                $days <= 60 => 'd31_60',
                $days <= 90 => 'd61_90',
                default => 'over_90',
            };
            $bill->days_overdue = max(0, (int) $days);
            $bill->bucket = $bucket;

            $id = (int) $bill->supplier_id;
            $suppliers[$id] ??= ['supplier_id' => $id, 'name' => $bill->supplier_name ?? '#' . $id, 'bills' => []]
                + array_fill_keys(self::BUCKETS, 0.0) + ['total' => 0.0];
            $suppliers[$id][$bucket] = round($suppliers[$id][$bucket] + $bill->owed, 2);
            $suppliers[$id]['total'] = round($suppliers[$id]['total'] + $bill->owed, 2);
            $suppliers[$id]['bills'][] = $bill;
            $totals[$bucket] = round($totals[$bucket] + $bill->owed, 2);
            $totals['total'] = round($totals['total'] + $bill->owed, 2);
        }

        uasort($suppliers, fn ($a, $b) => $b['total'] <=> $a['total']);

        $ledger = $this->ledgerBalance($asOf);

        return [
            'as_of' => $asOf,
            'suppliers' => array_values($suppliers),
            'totals' => $totals,
            'ledger_balance' => $ledger,
            'difference' => round($ledger - $totals['total'], 2),
        ];
    }

    /**
     * The balance of the supplier account(s) in the ledger on a date.
     *
     * Compared against the bills on the aging report: the two should agree,
     * and anything typed straight into account 40 by hand shows as the gap.
     */
    public function ledgerBalance(?string $asOf = null): float
    {
        $accounts = array_values(array_unique(array_filter(array_merge(
            [$this->payableAccountId()],
            SupplierBill::whereNotNull('payable_account_id')->distinct()->pluck('payable_account_id')->all()
        ))));

        if (!$accounts) {
            return 0.0;
        }

        return round((float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->whereIn('l.account_id', $accounts)
            ->where('j.is_posted', 1)
            ->whereNull('j.deleted_at')
            ->when($asOf, fn ($q) => $q->whereDate('j.entry_date', '<=', $asOf))
            ->sum(DB::raw('l.credit - l.debit')), 2);
    }

    /**
     * One supplier's account: bills, payments and their reversals, with a
     * running balance. The closing balance equals what owedQuery() says is
     * owed on the closing date.
     */
    public function statement(int $supplierId, ?string $from = null, ?string $to = null): array
    {
        $to = $to ? Carbon::parse($to)->toDateString() : now()->toDateString();
        $events = [];

        $bills = SupplierBill::with(['journalEntry', 'voidJournalEntry'])
            ->where('supplier_id', $supplierId)->whereNotNull('journal_entry_id')->get();
        foreach ($bills as $bill) {
            $events[] = ['date' => $bill->journalEntry->entry_date->toDateString(), 'order' => 1, 'type' => 'bill',
                'number' => $bill->number, 'reference' => $bill->supplier_reference,
                'description' => $bill->isOpeningBalance() ? __('Opening balance') : ($bill->description ?: __('Supplier bill')),
                'due_date' => $bill->due_date->toDateString(), 'billed' => (float) $bill->total, 'paid' => 0.0,
                'id' => $bill->id];
            if ($bill->voidJournalEntry) {
                $events[] = ['date' => $bill->voidJournalEntry->entry_date->toDateString(), 'order' => 2, 'type' => 'bill_void',
                    'number' => $bill->number, 'reference' => $bill->supplier_reference,
                    'description' => __('Bill voided') . ($bill->void_reason ? ': ' . $bill->void_reason : ''),
                    'due_date' => null, 'billed' => -(float) $bill->total, 'paid' => 0.0, 'id' => $bill->id];
            }
        }

        $payments = SupplierPayment::with(['journalEntry', 'voidJournalEntry', 'allocations.bill'])
            ->where('supplier_id', $supplierId)->whereNotNull('journal_entry_id')->get();
        foreach ($payments as $payment) {
            $paidBills = $payment->allocations->map(fn ($a) => $a->bill->number ?? '')->filter()->implode(', ');
            $events[] = ['date' => $payment->payment_date->toDateString(), 'order' => 3, 'type' => 'payment',
                'number' => $payment->number, 'reference' => $payment->reference,
                'description' => __('Payment') . ($paidBills ? ' — ' . $paidBills : ''),
                'due_date' => null, 'billed' => 0.0, 'paid' => (float) $payment->amount, 'id' => $payment->id];
            if ($payment->voidJournalEntry) {
                $events[] = ['date' => $payment->voidJournalEntry->entry_date->toDateString(), 'order' => 4, 'type' => 'payment_void',
                    'number' => $payment->number, 'reference' => $payment->reference,
                    'description' => __('Payment voided') . ($payment->void_reason ? ': ' . $payment->void_reason : ''),
                    'due_date' => null, 'billed' => 0.0, 'paid' => -(float) $payment->amount, 'id' => $payment->id];
            }
        }

        usort($events, fn ($a, $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);

        $opening = 0.0;
        $rows = [];
        $balance = 0.0;
        $billedTotal = 0.0;
        $paidTotal = 0.0;

        foreach ($events as $e) {
            if ($e['date'] > $to) {
                continue;
            }
            $change = $e['billed'] - $e['paid'];
            if ($from && $e['date'] < $from) {
                $opening = round($opening + $change, 2);
                continue;
            }
            if (!$rows) {
                $balance = $opening;
            }
            $balance = round($balance + $change, 2);
            $billedTotal = round($billedTotal + $e['billed'], 2);
            $paidTotal = round($paidTotal + $e['paid'], 2);
            $rows[] = $e + ['balance' => $balance];
        }

        return [
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'rows' => $rows,
            'billed' => $billedTotal,
            'paid' => $paidTotal,
            'closing' => round($opening + $billedTotal - $paidTotal, 2),
        ];
    }

    /**
     * The journal lines supplier bills put on the budget sheet.
     *
     * Read from the posted entries, so the sheet and the ledger agree by
     * construction: the approval's debit is spending on the line's budget
     * line on the bill date, and a void's credit takes it back off on the day
     * of the void. Each journal line is tied to its bill line, which carries
     * the expense category, and the category decides the budget line through
     * the same mapping an expense uses. Opening balances have no category and
     * never reach the sheet: they are last year's cost.
     */
    public static function budgetLedgerLines(?string $from = null, ?string $to = null): Builder
    {
        return DB::table('journal_entry_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->leftJoin('supplier_bill_lines as al', 'al.journal_entry_line_id', '=', 'jl.id')
            ->leftJoin('supplier_bill_lines as vl', 'vl.void_journal_entry_line_id', '=', 'jl.id')
            ->leftJoin('supplier_bills as b', 'b.id', '=', DB::raw('COALESCE(al.supplier_bill_id, vl.supplier_bill_id)'))
            ->leftJoin('item_suppliers as s', 's.id', '=', 'b.supplier_id')
            ->leftJoin('expense_categories as ec', 'ec.id', '=', DB::raw('COALESCE(al.expense_category_id, vl.expense_category_id)'))
            ->leftJoin('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', DB::raw('COALESCE(al.expense_category_id, vl.expense_category_id)'))
                    ->where('m.mapping_type', '=', self::MAPPING_TYPE);
            })
            ->whereIn('je.reference_type', ['supplier_bill', 'supplier_bill_reversal'])
            ->where('je.is_posted', 1)
            ->whereNull('je.deleted_at')
            ->whereNotNull(DB::raw('COALESCE(al.expense_category_id, vl.expense_category_id)'))
            ->when($from, fn ($q) => $q->whereDate('je.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('je.entry_date', '<=', $to));
    }

    /* ===================================================================
     |  Ledger plumbing
     |===================================================================*/

    /** SB-2026-0001 / SP-2026-0001, by the year of the document's own date. */
    private function nextNumber(string $prefix, string $table, string $date): string
    {
        $head = $prefix . '-' . Carbon::parse($date)->year . '-';

        $max = DB::table($table)->where('number', 'like', $head . '%')->lockForUpdate()->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($head)))->max() ?? 0;

        return $head . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
