<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\LedgerCashSetting;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransfer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which cash, bank or mobile-money account in the ledger a transaction's
 * money actually went through.
 *
 * A category's account mapping names one cash account (571, as installed), so
 * every receipt and payment landed in the cash box whether it was paid in
 * cash, by bank transfer or by mobile money, and the bank could never be
 * reconciled against the ledger. The mapping still decides the revenue or
 * expense side; this decides the cash side, in this order:
 *
 *   1. the payment account (cash box, bank, mobile-money wallet) the money
 *      went through, when the transaction records one and that account is
 *      linked to a ledger account;
 *   2. the school's default ledger account for the payment method;
 *   3. the mapping's own cash account — what happened before.
 *
 * Three numbering schemes for payment methods are in use, and they disagree
 * (fees store 2 for cash, incomes store 2 for bank transfer), so each source
 * is read in its own scheme. Payment-plan payments carry labels that
 * contradict their own translations, so only their payment account is used.
 */
class CashAccounts
{
    public const KIND_CASH = 'cash';
    public const KIND_BANK = 'bank';
    public const KIND_MOBILE = 'mobile_money';
    public const KIND_OTHER = 'other';

    /** App\Support\PaymentMethod: incomes and expenses. */
    private const SCHEME_CURRENT = [1 => self::KIND_CASH, 2 => self::KIND_BANK, 3 => self::KIND_MOBILE, 4 => self::KIND_MOBILE, 5 => self::KIND_OTHER];

    /** The older keys fees and payroll store: 1 and 2 are both cash, 3 MTN, 4 bank. */
    private const SCHEME_LEGACY = [1 => self::KIND_CASH, 2 => self::KIND_CASH, 3 => self::KIND_MOBILE, 4 => self::KIND_BANK, 5 => self::KIND_OTHER];

    /**
     * Swap the class-5 side of a mapping for the account the money went
     * through. A mapping with no class-5 side, or with two (a transfer), is
     * returned as it is.
     *
     * @return array{0:int, 1:int} [debit account id, credit account id]
     */
    public function apply(int $debitId, int $creditId, ?string $type, ?int $id): array
    {
        $target = $this->forSource($type, $id);
        if (!$target) {
            return [$debitId, $creditId];
        }

        $debitIsCash = $this->isCash($debitId);
        $creditIsCash = $this->isCash($creditId);

        if ($debitIsCash && !$creditIsCash) {
            return [$target, $creditId];
        }
        if ($creditIsCash && !$debitIsCash) {
            return [$debitId, $target];
        }

        return [$debitId, $creditId];
    }

    /** The ledger cash account for one recorded transaction, or null for "keep the mapping's". */
    public function forSource(?string $type, ?int $id): ?int
    {
        if (!$type || !$id) {
            return null;
        }

        $row = match ($type) {
            'income' => DB::table('incomes')->where('id', $id)->first(['payment_account_id', 'payment_method']),
            'expense' => DB::table('expenses')->where('id', $id)->first(['payment_account_id', 'payment_method']),
            'fee' => DB::table('fees')->where('id', $id)->first(['payment_account_id', 'payment_method']),
            'payment_plan_payment' => DB::table('payment_plan_payments')->where('id', $id)->first(['payment_account_id']),
            default => null,
        };
        if (!$row) {
            return null;
        }

        $scheme = match ($type) {
            'income', 'expense' => self::SCHEME_CURRENT,
            'fee' => self::SCHEME_LEGACY,
            default => [],
        };

        return $this->forPaymentAccount($row->payment_account_id ?? null)
            ?? $this->forMethod($row->payment_method ?? null, $scheme);
    }

    /** Incomes, expenses and supplier payments use the current scheme (App\Support\PaymentMethod). */
    public function forCurrentMethod($method): ?int
    {
        return $this->forMethod($method, self::SCHEME_CURRENT);
    }

    /** Payroll keeps its payment method in the older scheme. */
    public function forPayrollMethod($method): ?int
    {
        return $this->forMethod($method, self::SCHEME_LEGACY);
    }

    public function forPaymentAccount($paymentAccountId): ?int
    {
        if (!$paymentAccountId) {
            return null;
        }

        $accountId = PaymentAccount::whereKey($paymentAccountId)->value('chart_of_account_id');

        return $accountId && $this->isCash($accountId) ? (int) $accountId : null;
    }

    /** @param array<int, string> $scheme */
    public function forMethod($method, array $scheme): ?int
    {
        $kind = $scheme[(int) $method] ?? null;
        if (!$kind) {
            return null;
        }

        $accountId = LedgerCashSetting::current()->accountFor($kind);

        return $accountId && $this->isCash($accountId) ? (int) $accountId : null;
    }

    /** A class-5 detail account that is not a provision (59). */
    public function isCash($accountId): bool
    {
        static $cache = [];
        if (!array_key_exists($accountId, $cache)) {
            $code = (string) ChartOfAccount::whereKey($accountId)->value('account_code');
            $cache[$accountId] = $code !== '' && $code[0] === '5' && !str_starts_with($code, '59');
        }

        return $cache[$accountId];
    }

    /**
     * Post a transfer between two payment accounts: Dr the receiving account's
     * ledger account, Cr the sending one's. Nothing is posted when either is
     * unlinked or both use the same ledger account — then no ledger balance
     * moves, and there is nothing to record.
     */
    public function postTransfer(PaymentAccountTransfer $transfer, ?int $userId = null): ?JournalEntry
    {
        $from = $this->forPaymentAccount($transfer->from_account_id);
        $to = $this->forPaymentAccount($transfer->to_account_id);
        if (!$from || !$to || $from === $to) {
            return null;
        }

        $date = \Carbon\Carbon::parse($transfer->transfer_date)->format('Y-m-d');
        $year = FiscalYear::forDate($date);
        $period = FiscalYear::periodForDate($date);
        $amount = round((float) $transfer->amount, 2);

        $entry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber($date),
            'entry_date' => $date,
            'fiscal_year_id' => $year->id ?? null,
            'accounting_period_id' => $period->id ?? null,
            'journal_type' => 'cash',
            'description' => __('Transfer') . ': ' . ($transfer->fromAccount->title ?? '') . ' → ' . ($transfer->toAccount->title ?? ''),
            'reference_type' => 'payment_transfer',
            'reference_id' => $transfer->id,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'is_posted' => false,
            'is_system_generated' => true,
            'created_by' => $userId ?? auth()->id(),
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 1, 'account_id' => $to, 'debit' => $amount, 'credit' => 0,
            'description' => __('Transfer in')]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 2, 'account_id' => $from, 'debit' => 0, 'credit' => $amount,
            'description' => __('Transfer out')]);
        $entry->post($userId ?? auth()->id());

        return $entry;
    }

    /**
     * Reverse a transfer's live posting with a mirror entry: on the transfer's
     * date while that period is open, otherwise today.
     */
    public function reverseTransfer(PaymentAccountTransfer $transfer, ?int $userId = null): ?JournalEntry
    {
        $original = JournalEntry::where('reference_type', 'payment_transfer')->where('reference_id', $transfer->id)
            ->where('is_posted', true)->where('is_reversed', false)->latest('id')->with('lines')->first();
        if (!$original) {
            return null;
        }

        $date = $original->entry_date->format('Y-m-d');
        $period = FiscalYear::periodForDate($date);
        if (($period && $period->is_closed) || optional($original->fiscalYear)->is_closed) {
            $date = now()->toDateString();
            $period = FiscalYear::periodForDate($date);
        }

        $reversal = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber($date),
            'entry_date' => $date,
            'fiscal_year_id' => FiscalYear::forDate($date)->id ?? $original->fiscal_year_id,
            'accounting_period_id' => $period->id ?? null,
            'journal_type' => 'cash',
            'description' => __('Reversal') . ': ' . $original->description,
            'reference_type' => 'payment_transfer_reversal',
            'reference_id' => $transfer->id,
            'reversed_entry_id' => $original->id,
            'total_debit' => $original->total_credit,
            'total_credit' => $original->total_debit,
            'is_posted' => false,
            'is_system_generated' => true,
            'created_by' => $userId ?? auth()->id(),
        ]);
        $n = 0;
        foreach ($original->lines as $line) {
            JournalEntryLine::create(['journal_entry_id' => $reversal->id, 'line_number' => ++$n, 'account_id' => $line->account_id,
                'debit' => $line->credit, 'credit' => $line->debit, 'description' => __('Reversal') . ': ' . $line->description]);
        }
        $reversal->post($userId ?? auth()->id());
        $original->update(['is_reversed' => true]);

        return $reversal;
    }

    /**
     * Each ledger account that payment accounts are linked to: the books'
     * balances beside the ledger's. A difference is expected wherever the
     * ledger carries history from before the link, or money that no book
     * recorded; it is shown, not judged.
     */
    public function comparison(): Collection
    {
        $books = PaymentAccount::whereNotNull('chart_of_account_id')->get()->groupBy('chart_of_account_id');
        if ($books->isEmpty()) {
            return collect();
        }

        $ledger = collect(app(FinancialStatements::class)->accounts(null, now()->toDateString()))->keyBy('id');

        return $books->map(function ($accounts, $accountId) use ($ledger) {
            $gl = $ledger->get((int) $accountId);
            $book = round((float) $accounts->sum('current_balance'), 2);
            $ledgerBalance = round((float) ($gl->closing ?? 0), 2);

            return (object) [
                'account_id' => (int) $accountId,
                'account_code' => $gl->account_code ?? ChartOfAccount::whereKey($accountId)->value('account_code'),
                'account_name' => $gl->account_name ?? ChartOfAccount::whereKey($accountId)->value('account_name'),
                'books' => $accounts->pluck('title')->implode(', '),
                'book_balance' => $book,
                'ledger_balance' => $ledgerBalance,
                'difference' => round($ledgerBalance - $book, 2),
            ];
        })->values();
    }
}
