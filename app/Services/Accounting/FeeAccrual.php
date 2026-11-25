<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use App\Models\Fee;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fees on accrual: what each student owes is in the books, on 411.
 *
 *   assign a fee        Dr 411 / Cr fee income      for what it asks (fee − discount + fine)
 *   take a payment      Dr cash / Cr 411            (FeeLedgerPosting, through the auto-mapper)
 *   overpayment held    Dr 411 / Cr 419             (StudentCreditLedger)
 *   credit used         Dr 419 / Cr 411
 *   unbacked credit     Dr fee income / Cr 411      honoured as a discount
 *
 * So 411 is, at every date, what students owe, and income is the year's fees
 * whether or not they have been paid yet.
 *
 * Which fees are billed: fees of an enrolled student, not cancelled (status 3),
 * asking for more than nothing. An applicant's admission fee is not billed: an
 * applicant owes nothing until they decide to come, so it stays income when it
 * is paid, as before.
 *
 * Like the student credit ledger, everything here is derived from the fee
 * records: sync() can run at any time, and a billing that is no longer right
 * (the fee changed, was cancelled or deleted) is reversed and written again.
 */
class FeeAccrual
{
    use Concerns\PostsJournalEntries;

    public const SETTING_ENABLED = 'fee_accrual_enabled';
    public const SETTING_ACCOUNT = 'fee_receivable_account_id';
    public const REFERENCE_TYPES = ['fee_billing', 'fee_billing_reversal'];

    protected static ?bool $enabled = null;
    protected static ?int $receivable = null;

    public static function enabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = (string) DB::table('accounting_settings')->where('setting_key', self::SETTING_ENABLED)->value('setting_value') === '1';
        }

        return self::$enabled;
    }

    /** Forget cached settings (after switching accrual on, and in tests). */
    public static function flush(): void
    {
        self::$enabled = null;
        self::$receivable = null;
    }

    public static function receivableAccountId(): ?int
    {
        if (self::$receivable === null) {
            $value = (int) DB::table('accounting_settings')->where('setting_key', self::SETTING_ACCOUNT)->value('setting_value');
            self::$receivable = ($value && ChartOfAccount::whereKey($value)->exists())
                ? $value
                : (int) (ChartOfAccount::where('account_code', '411')->value('id') ?? 0);
        }

        return self::$receivable ?: null;
    }

    /** Is this fee billed to 411 (so its payments settle 411)? */
    public static function bills($fee): bool
    {
        if (!self::enabled() || !$fee) {
            return false;
        }

        $fee = $fee instanceof Fee ? $fee : Fee::find($fee);

        if (!$fee || $fee->applicant_id || (int) $fee->status === 3) {
            return false;
        }

        return (bool) DB::table('student_enrolls')->where('id', $fee->student_enroll_id)->whereNotNull('student_id')->exists();
    }

    /**
     * The account a cash posting of this source credits, when it settles a
     * billed fee rather than earning income: 411. Null when the normal
     * income mapping applies.
     */
    public static function settlementAccountFor(?string $type, ?int $id): ?int
    {
        if (!self::enabled() || !$id) {
            return null;
        }

        $feeId = match ($type) {
            'fee' => $id,
            'payment_plan_payment' => DB::table('payment_plan_payments as ppp')
                ->join('payment_plan_installments as ppi', 'ppi.id', '=', 'ppp.installment_id')
                ->join('payment_plans as pp', 'pp.id', '=', 'ppi.payment_plan_id')
                ->where('ppp.id', $id)->value('pp.fee_id'),
            default => null,
        };

        return $feeId && self::bills((int) $feeId) ? self::receivableAccountId() : null;
    }

    /* ===================================================================
     |  Billing
     |===================================================================*/

    /** What the fee's billing entry should be, or null when it should have none. */
    public function desired(Fee $fee): ?array
    {
        if (!self::bills($fee)) {
            return null;
        }

        $amount = round((float) $fee->total_amount, 2);
        $income = DefaultAccountMapping::where('mapping_type', 'fee_category')
            ->where('category_id', $fee->category_id)->where('status', 'active')->value('credit_account_id');

        if ($amount <= 0.009) {
            return null;
        }

        return [
            'amount' => $amount,
            'date' => substr((string) ($fee->assign_date ?: $fee->created_at ?: now()), 0, 10),
            'debit_account_id' => self::receivableAccountId(),
            'credit_account_id' => $income ? (int) $income : null,
            'description' => 'Fee billed - ' . ($fee->category->title ?? 'Student fee') . ' (fee #' . $fee->id . ')',
        ];
    }

    /**
     * Bring one fee's billing into line, inside the caller's transaction.
     *
     * @return string posted | reversed | reposted | unchanged
     * @throws \RuntimeException when it cannot be posted
     */
    public function sync($fee, ?int $userId = null): string
    {
        $feeId = $fee instanceof Fee ? $fee->id : (int) $fee;
        $fee = Fee::with('category')->find($feeId);
        $userId = $userId ?? Auth::id();

        $want = $fee ? $this->desired($fee) : null;
        $active = DB::table('fee_billing_postings')->where('fee_id', $feeId)->where('status', 'active')->lockForUpdate()->first();

        if ($active && $want && abs((float) $active->amount - $want['amount']) < 0.01
            && (int) $active->debit_account_id === (int) $want['debit_account_id']
            && (int) $active->credit_account_id === (int) $want['credit_account_id']) {
            return 'unchanged';
        }

        if ($active) {
            $this->reversePosting($active, $userId);
        }

        if (!$want) {
            return $active ? 'reversed' : 'unchanged';
        }

        if (!$want['debit_account_id'] || !$want['credit_account_id']) {
            throw new \RuntimeException("Fee #{$feeId}: no income account for its category, or no receivable account (411).");
        }

        $this->post($feeId, $want, $userId);

        return $active ? 'reposted' : 'posted';
    }

    /** sync() that never throws: for observers, so recording a fee never fails on the ledger. */
    public function syncQuietly($fee, ?int $userId = null): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            DB::transaction(fn () => $this->sync($fee, $userId));
        } catch (\Throwable $e) {
            Log::warning('Fee billing sync failed for fee #' . ($fee instanceof Fee ? $fee->id : $fee) . ': ' . $e->getMessage());
        }
    }

    /** @return array{posted:int, reversed:int, reposted:int, unchanged:int, problems:array} */
    public function syncAll(?int $userId = null): array
    {
        $result = ['posted' => 0, 'reversed' => 0, 'reposted' => 0, 'unchanged' => 0, 'problems' => []];
        $ids = DB::table('fees')->pluck('id')
            ->merge(DB::table('fee_billing_postings')->where('status', 'active')->pluck('fee_id'))
            ->unique();

        foreach ($ids as $id) {
            try {
                $outcome = DB::transaction(fn () => $this->sync((int) $id, $userId));
                $result[$outcome]++;
            } catch (\Throwable $e) {
                $result['problems'][] = "Fee #{$id}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Does 411 hold what students owe? Per billed fee: what it asks, less what
     * was paid on it, plus any overpayment moved out of it to 419.
     *
     * @return array{ledger: float, expected: float, difference: float, agrees: bool}
     */
    public function tieOut(): array
    {
        $account = self::receivableAccountId();
        $ledger = $account ? round((float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $account)->where('e.is_posted', 1)->whereNull('e.deleted_at')
            ->sum(DB::raw('l.debit - l.credit')), 2) : 0.0;

        $billed = Fee::with('category')->get()->filter(fn ($fee) => self::bills($fee));
        $expected = round($billed->sum(fn ($fee) => (float) $fee->total_amount - (float) $fee->paid_amount), 2);

        // Overpayment held on 419 came off 411 (Dr 411 / Cr 419). A transfer's
        // hold is not added: the transfer already took that money off the
        // fee's paid amount, so it is in (asks − paid) once.
        $holds = (float) DB::table('student_credit_postings as p')
            ->join('student_credits as c', 'c.id', '=', 'p.student_credit_id')
            ->where('p.status', 'active')->where('p.kind', 'hold')->where('c.source_type', 'overpayment')
            ->where('p.debit_account_id', $account)->sum('p.amount');
        $expected = round($expected + $holds, 2);

        return [
            'ledger' => $ledger,
            'expected' => $expected,
            'difference' => round($ledger - $expected, 2),
            'agrees' => abs($ledger - $expected) < 0.01,
        ];
    }

    /* ---- posting ----------------------------------------------------- */

    protected function post(int $feeId, array $want, ?int $userId): void
    {
        $date = $this->openDate($want['date']);

        $postingId = DB::table('fee_billing_postings')->insertGetId([
            'fee_id' => $feeId,
            'amount' => $want['amount'],
            'entry_date' => $date,
            'debit_account_id' => $want['debit_account_id'],
            'credit_account_id' => $want['credit_account_id'],
            'status' => 'active',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entry = $this->newEntry($date, 'sales', $want['description'], 'fee_billing', $postingId, $want['amount'], $userId, 'FEE-' . $feeId);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 1, 'account_id' => $want['debit_account_id'],
            'description' => $want['description'], 'debit' => $want['amount'], 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 2, 'account_id' => $want['credit_account_id'],
            'description' => $want['description'], 'debit' => 0, 'credit' => $want['amount']]);
        $entry->load('lines');
        $entry->post($userId);

        DB::table('fee_billing_postings')->where('id', $postingId)->update(['journal_entry_id' => $entry->id]);
    }

    protected function reversePosting(object $posting, ?int $userId): void
    {
        $original = $posting->journal_entry_id ? JournalEntry::with('lines')->find($posting->journal_entry_id) : null;
        $reversalId = null;

        if ($original && !$original->is_reversed) {
            [$reversal] = $this->reverseEntry($original, 'fee_billing_reversal', (int) $posting->id, $userId);
            $reversalId = $reversal->id;
        }

        DB::table('fee_billing_postings')->where('id', $posting->id)
            ->update(['status' => 'reversed', 'reversal_entry_id' => $reversalId, 'updated_at' => now()]);
    }

    /** The date itself while its month and year are open; today once they are closed. */
    protected function openDate(string $date): string
    {
        $year = FiscalYear::forDate($date);
        $period = $year ? FiscalYear::periodForDate($date) : null;

        return (($year && $year->is_closed) || ($period && $period->is_closed)) ? now()->toDateString() : $date;
    }
}
