<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\CreditApplication;
use App\Models\DefaultAccountMapping;
use App\Models\Fee;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\StudentCredit;
use App\Support\PaymentMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Student credit in the ledger: overpayments are a debt until they are used.
 *
 * A fee posts the cash it received (FeeLedgerPosting). When that cash is more
 * than the fee asked for, the excess is not the school's income yet: it is held
 * for the student, on account 419 (Clients créditeurs et avances reçues), until
 * it settles another fee or is paid back.
 *
 *   hold     Dr fee income / Cr 419   the credit is raised on the fee it came from
 *   release  Dr 419 / Cr fee income   the credit settles another fee
 *   refund   Dr 419 / Cr cash         the credit is paid back to the student
 *   forfeit  Dr 419 / Cr fee income   credit that will never be used or refunded
 *   receipt  Dr cash / Cr 419          money paid in advance, beyond every fee owed
 *
 * Only credit that cash actually backs is held. Some credit on file was raised
 * twice for the same overpayment (see FeeCreditReconciliation); the school
 * decided to honour what was already spent, so that part settled its fee as a
 * discount — no cash, so no income and nothing in 419. Each credit's backed
 * share is taken from the fee it came from:
 *
 *   overpayment  the fee's paid amount less what it asked for, shared out
 *                oldest credit first
 *   transfer     all of it: a transfer moves cash that the fee really received
 *   other        none (a manual adjustment brought no cash)
 *
 * A credit's backed share is used by its applications oldest first, then by a
 * refund. That keeps 419 equal, at any date, to the backed credit not yet used,
 * and the total of fee income plus 419 equal to the fee cash received less
 * refunds.
 *
 * Everything here is derived from the credit records, so sync() can be run at
 * any time and makes the ledger match them: a posting that is no longer right
 * is reversed and written again, and one that is right is left alone.
 */
class StudentCreditLedger
{
    use Concerns\PostsJournalEntries;

    public const SETTING = 'student_credit_account_id';

    public const HOLD = 'hold';
    public const RELEASE = 'release';
    public const REFUND = 'refund';
    public const FORFEIT = 'forfeit';
    public const RECEIPT = 'receipt';
    public const DISCOUNT = 'discount';

    /** The journal reference types this class writes (reversals add "_reversal"). */
    public const REFERENCE_TYPES = [
        'student_credit_hold', 'student_credit_release', 'student_credit_refund', 'student_credit_forfeit', 'student_credit_receipt', 'student_credit_discount',
        'student_credit_discount_reversal', 'student_credit_hold_reversal', 'student_credit_release_reversal', 'student_credit_receipt_reversal',
        'student_credit_refund_reversal', 'student_credit_forfeit_reversal',
    ];

    /** @var array<int, ?int> fee category id => income account id */
    protected array $incomeAccounts = [];

    public function liabilityAccountId(): ?int
    {
        $value = DB::table('accounting_settings')->where('setting_key', self::SETTING)->value('setting_value');

        if ($value && ChartOfAccount::whereKey((int) $value)->exists()) {
            return (int) $value;
        }

        $fallback = ChartOfAccount::where('account_code', '419')->value('id');

        return $fallback ? (int) $fallback : null;
    }

    /* ===================================================================
     |  What the ledger should hold
     |===================================================================*/

    /**
     * Every posting the credit records call for.
     *
     * @return Collection<string, array> keyed "kind:credit:application"
     */
    public function desired(?int $studentId = null): Collection
    {
        $credits = StudentCredit::query()
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->orderBy('id')
            ->get();

        if ($credits->isEmpty()) {
            return collect();
        }

        $applications = CreditApplication::whereIn('student_credit_id', $credits->pluck('id'))
            ->orderBy('id')->get()->groupBy('student_credit_id');

        $feeIds = $credits->pluck('source_fee_id')
            ->merge($applications->flatten()->pluck('fee_id'))
            ->filter()->unique();
        $fees = Fee::query()->whereIn('id', $feeIds)->get()->keyBy('id');

        $backed = $this->backedShares($credits, $fees);
        $liability = $this->liabilityAccountId();
        $out = collect();

        $receivable = FeeAccrual::receivableAccountId();

        foreach ($credits as $credit) {
            $share = $backed[$credit->id] ?? 0.0;

            $source = $fees->get($credit->source_fee_id);
            $sourceCategory = $source->category_id ?? null;
            $sourceIncome = $this->incomeAccount($sourceCategory);
            // On accrual a billed fee's money sits on 411 (what the student
            // owes), not on income: credit moves between 411 and 419.
            $sourceSettle = FeeAccrual::bills($source) ? $receivable : $sourceIncome;

            if ($share <= 0.009) {
                // No cash behind this credit. What it settled on a billed fee
                // was honoured as a discount: it comes off what the student
                // owes and off income (Dr income / Cr 411).
                $this->discounts($out, $credit, $applications->get($credit->id, collect()), $fees, 0.0, $sourceCategory, $receivable);
                continue;
            }

            if ($credit->source_type === StudentCredit::SOURCE_PREPAYMENT) {
                // Cash paid in advance, never part of a fee: it comes straight
                // into the cash account and is owed to the student.
                $out->put($this->key(self::RECEIPT, $credit->id, null), [
                    'kind' => self::RECEIPT,
                    'student_credit_id' => $credit->id,
                    'credit_application_id' => null,
                    'amount' => $share,
                    'date' => $this->dateOf($credit->received_on ?? $credit->created_at),
                    'debit_account_id' => $this->receiptCashAccount($credit, $sourceCategory),
                    'credit_account_id' => $liability,
                    'fee_category_id' => $sourceCategory,
                    'description' => sprintf('Paid in advance, held as student credit — with fee #%d', $credit->source_fee_id),
                ]);
            } else {
                $out->put($this->key(self::HOLD, $credit->id, null), [
                    'kind' => self::HOLD,
                    'student_credit_id' => $credit->id,
                    'credit_application_id' => null,
                    'amount' => $share,
                    'date' => $this->dateOf($credit->created_at),
                    'debit_account_id' => $sourceSettle,
                    'credit_account_id' => $liability,
                    'fee_category_id' => $sourceCategory,
                    'description' => sprintf('Overpayment held as student credit — fee #%d', $credit->source_fee_id),
                ]);
            }

            $left = $share;

            foreach ($applications->get($credit->id, collect()) as $application) {
                if ($left <= 0.009) {
                    break;
                }

                $amount = round(min((float) $application->amount_applied, $left), 2);
                $left = round($left - $amount, 2);

                // A credit applied to a fee that has since been deleted: the
                // money came back to nothing, so it returns to where it came from.
                $target = $fees->get($application->fee_id);
                $category = $target->category_id ?? $sourceCategory;

                $out->put($this->key(self::RELEASE, $credit->id, $application->id), [
                    'kind' => self::RELEASE,
                    'student_credit_id' => $credit->id,
                    'credit_application_id' => $application->id,
                    'amount' => $amount,
                    'date' => $this->dateOf($application->created_at),
                    'debit_account_id' => $liability,
                    'credit_account_id' => FeeAccrual::bills($target) ? $receivable : $this->incomeAccount($category),
                    'fee_category_id' => $category,
                    'description' => sprintf('Student credit applied to fee #%s', $application->fee_id ?? '—'),
                ]);
            }

            // Whatever of its applications the backed share did not cover.
            $this->discounts($out, $credit, $applications->get($credit->id, collect()), $fees, $share, $sourceCategory, $receivable);

            if ($left > 0.009 && $credit->status === StudentCredit::STATUS_REFUNDED && $credit->refund_processed_at) {
                $out->put($this->key(self::REFUND, $credit->id, null), [
                    'kind' => self::REFUND,
                    'student_credit_id' => $credit->id,
                    'credit_application_id' => null,
                    'amount' => $left,
                    'date' => $this->dateOf($credit->refund_processed_at),
                    'debit_account_id' => $liability,
                    'credit_account_id' => $this->refundCashAccount($credit->refund_method, $sourceCategory),
                    'fee_category_id' => $sourceCategory,
                    'description' => sprintf('Student credit refunded (%s)', $credit->refund_method ?? 'cash'),
                ]);
                $left = 0.0;
            }

            // Expired with backed money still on it: nobody will use it or be
            // paid it, so it becomes the school's income after all.
            if ($left > 0.009 && $credit->status === StudentCredit::STATUS_EXPIRED) {
                $out->put($this->key(self::FORFEIT, $credit->id, null), [
                    'kind' => self::FORFEIT,
                    'student_credit_id' => $credit->id,
                    'credit_application_id' => null,
                    'amount' => $left,
                    'date' => $this->dateOf($credit->updated_at),
                    'debit_account_id' => $liability,
                    'credit_account_id' => $sourceIncome,
                    'fee_category_id' => $sourceCategory,
                    'description' => sprintf('Expired student credit taken to income — fee #%d', $credit->source_fee_id),
                ]);
            }
        }

        return $out;
    }

    /**
     * Applications beyond a credit's backed share, on billed fees: honoured as
     * a discount (Dr income / Cr 411), so 411 stops showing as owed what the
     * fee screens show as settled. Only on accrual; on a cash basis an
     * unbacked credit simply posts nothing.
     */
    protected function discounts(Collection $out, StudentCredit $credit, Collection $applications, Collection $fees, float $share, ?int $sourceCategory, ?int $receivable): void
    {
        if (!FeeAccrual::enabled() || !$receivable) {
            return;
        }

        $covered = $share;

        foreach ($applications as $application) {
            $applied = (float) $application->amount_applied;
            $backedPart = min($applied, max(0.0, $covered));
            $covered = round($covered - $backedPart, 2);
            $unbacked = round($applied - $backedPart, 2);
            $target = $fees->get($application->fee_id);

            if ($unbacked <= 0.009 || !FeeAccrual::bills($target)) {
                continue;
            }

            $category = $target->category_id ?? $sourceCategory;
            $out->put($this->key(self::DISCOUNT, $credit->id, $application->id), [
                'kind' => self::DISCOUNT,
                'student_credit_id' => $credit->id,
                'credit_application_id' => $application->id,
                'amount' => $unbacked,
                'date' => $this->dateOf($application->created_at),
                'debit_account_id' => $this->incomeAccount($category),
                'credit_account_id' => $receivable,
                'fee_category_id' => $category,
                'description' => sprintf('Credit with no payment behind it, honoured as a discount — fee #%s', $application->fee_id),
            ]);
        }
    }

    /**
     * How much of each credit cash backs.
     *
     * @return array<int, float> credit id => backed amount
     */
    public function backedShares(Collection $credits, Collection $fees): array
    {
        $shares = [];

        foreach ($credits->groupBy('source_fee_id') as $feeId => $group) {
            $fee = $fees->get($feeId);

            // Paid in advance: all of it is cash that arrived.
            foreach ($group->where('source_type', StudentCredit::SOURCE_PREPAYMENT) as $credit) {
                $shares[$credit->id] = round((float) $credit->original_amount, 2);
            }

            foreach ($group->where('source_type', StudentCredit::SOURCE_TRANSFER) as $credit) {
                $shares[$credit->id] = $fee ? round((float) $credit->original_amount, 2) : 0.0;
            }

            $available = $fee ? max(0.0, round((float) $fee->paid_amount - (float) $fee->total_amount, 2)) : 0.0;

            foreach ($group->where('source_type', StudentCredit::SOURCE_OVERPAYMENT)->sortBy('id') as $credit) {
                $share = round(min((float) $credit->original_amount, $available), 2);
                $shares[$credit->id] = $share;
                $available = round($available - $share, 2);
            }
        }

        return $shares;
    }

    /* ===================================================================
     |  Making the ledger match
     |===================================================================*/

    /**
     * Bring the ledger into line with the credit records.
     *
     * One student at a time, each in its own transaction, so one that cannot be
     * posted (no fiscal year for its date, a category with no account) is left
     * as it was and reported while the rest go ahead.
     *
     * @return array{posted:int, reversed:int, unchanged:int, problems:array<int,string>}
     */
    public function sync(?int $studentId = null, ?int $userId = null, bool $dryRun = false): array
    {
        $userId = $userId ?? Auth::id();
        $result = ['posted' => 0, 'reversed' => 0, 'unchanged' => 0, 'problems' => [], 'changes' => []];

        $students = $studentId
            ? collect([$studentId])
            : StudentCredit::query()->distinct()->pluck('student_id')
                ->merge(DB::table('student_credit_postings as p')
                    ->join('student_credits as c', 'c.id', '=', 'p.student_credit_id')
                    ->where('p.status', 'active')->distinct()->pluck('c.student_id'))
                ->unique()->values();

        foreach ($students as $id) {
            try {
                DB::beginTransaction();
                $one = $this->syncStudent((int) $id, $userId);
                $dryRun ? DB::rollBack() : DB::commit();

                foreach (['posted', 'reversed', 'unchanged'] as $k) {
                    $result[$k] += $one[$k];
                }
                $result['changes'] = array_merge($result['changes'], $one['changes']);
            } catch (\Throwable $e) {
                DB::rollBack();
                $result['problems'][] = "Student #{$id}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Sync one student's credit, inside the caller's transaction. Used by the
     * credit screens straight after a change, so the posting and the change are
     * saved together. Never throws: a failure is logged, the credit change
     * stands, and the next sync (or the ledger health screen) picks it up.
     */
    public function syncQuietly(?int $studentId, ?int $userId = null): void
    {
        if (!$studentId || !$this->liabilityAccountId()) {
            return;
        }

        try {
            DB::transaction(fn () => $this->syncStudent($studentId, $userId ?? Auth::id()));
        } catch (\Throwable $e) {
            Log::warning("Student credit ledger sync failed for student #{$studentId}: " . $e->getMessage());
        }
    }

    protected function syncStudent(int $studentId, ?int $userId): array
    {
        $result = ['posted' => 0, 'reversed' => 0, 'unchanged' => 0, 'changes' => []];
        $desired = $this->desired($studentId);

        $active = DB::table('student_credit_postings as p')
            ->join('student_credits as c', 'c.id', '=', 'p.student_credit_id')
            ->where('c.student_id', $studentId)
            ->where('p.status', 'active')
            ->select('p.*')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($p) => $this->key($p->kind, (int) $p->student_credit_id, $p->credit_application_id ? (int) $p->credit_application_id : null));

        foreach ($active as $key => $posting) {
            $want = $desired->get($key);

            if ($want && abs((float) $posting->amount - $want['amount']) < 0.01
                && (int) $posting->debit_account_id === (int) $want['debit_account_id']
                && (int) $posting->credit_account_id === (int) $want['credit_account_id']) {
                $result['unchanged']++;
                $desired->forget($key);
                continue;
            }

            $this->reversePosting($posting, $userId);
            $result['reversed']++;
            $result['changes'][] = ['action' => 'reverse', 'kind' => $posting->kind, 'amount' => (float) $posting->amount, 'credit' => (int) $posting->student_credit_id];
        }

        foreach ($desired as $want) {
            $this->post($want, $userId);
            $result['posted']++;
            $result['changes'][] = ['action' => 'post', 'kind' => $want['kind'], 'amount' => $want['amount'], 'credit' => $want['student_credit_id'], 'date' => $want['date']];
        }

        return $result;
    }

    protected function post(array $want, ?int $userId): void
    {
        foreach (['debit_account_id', 'credit_account_id'] as $side) {
            if (!$want[$side]) {
                throw new \RuntimeException(sprintf(
                    'Credit #%d: no %s account. Map the fee category to an income account and set the student credit account (419).',
                    $want['student_credit_id'], $side === 'debit_account_id' ? 'debit' : 'credit'
                ));
            }
        }

        $date = $this->openDate($want['date']);

        $postingId = DB::table('student_credit_postings')->insertGetId([
            'student_credit_id' => $want['student_credit_id'],
            'credit_application_id' => $want['credit_application_id'],
            'kind' => $want['kind'],
            'amount' => $want['amount'],
            'entry_date' => $date,
            'debit_account_id' => $want['debit_account_id'],
            'credit_account_id' => $want['credit_account_id'],
            'fee_category_id' => $want['fee_category_id'],
            'status' => 'active',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entry = $this->newEntry($date, $want['kind'] === self::REFUND ? 'cash' : 'adjustment', $want['description'],
            'student_credit_' . $want['kind'], $postingId, $want['amount'], $userId, 'SC-' . $want['student_credit_id']);

        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 1, 'account_id' => $want['debit_account_id'],
            'description' => $want['description'], 'debit' => $want['amount'], 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'line_number' => 2, 'account_id' => $want['credit_account_id'],
            'description' => $want['description'], 'debit' => 0, 'credit' => $want['amount']]);

        $entry->post($userId);

        DB::table('student_credit_postings')->where('id', $postingId)->update(['journal_entry_id' => $entry->id]);
    }

    protected function reversePosting(object $posting, ?int $userId): void
    {
        $original = $posting->journal_entry_id ? JournalEntry::with('lines')->find($posting->journal_entry_id) : null;
        $reversalId = null;

        if ($original && !$original->is_reversed) {
            [$reversal] = $this->reverseEntry($original, 'student_credit_' . $posting->kind . '_reversal', (int) $posting->id, $userId);
            $reversalId = $reversal->id;
        }

        DB::table('student_credit_postings')->where('id', $posting->id)->update([
            'status' => 'reversed',
            'reversal_entry_id' => $reversalId,
            'updated_at' => now(),
        ]);
    }

    /* ===================================================================
     |  Checks and reports
     |===================================================================*/

    /**
     * Does account 419 hold exactly the backed credit not yet used?
     *
     * @return array{ledger: float, expected: float, difference: float, agrees: bool, pending: int}
     */
    public function tieOut(?string $asOf = null): array
    {
        $account = $this->liabilityAccountId();

        $ledger = $account ? (float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $account)
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->when($asOf, fn ($q) => $q->whereDate('e.entry_date', '<=', $asOf))
            ->sum(DB::raw('l.credit - l.debit')) : 0.0;

        $desired = $this->desired();
        $signed = fn ($w) => in_array($w['kind'], [self::HOLD, self::RECEIPT], true) ? $w['amount'] : ($w['kind'] === self::DISCOUNT ? 0.0 : -$w['amount']);
        $expected = round($desired
            ->filter(fn ($w) => !$asOf || $w['date'] <= $asOf)
            ->sum($signed), 2);

        $pending = $this->sync(null, null, true);

        return [
            'ledger' => round($ledger, 2),
            'expected' => $expected,
            'difference' => round($ledger - $expected, 2),
            'agrees' => abs($ledger - $expected) < 0.01,
            'pending' => $pending['posted'] + $pending['reversed'],
        ];
    }

    /**
     * Net effect of student credit postings on fee income (class 7), for a
     * date range: positive when more credit was used than held. The budget
     * sheet counts fee cash when it arrived, so this is exactly the timing
     * difference between the sheet's income and the ledger's.
     */
    public static function incomeEffect(?string $from = null, ?string $to = null): float
    {
        return round((float) DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('e.reference_type', self::REFERENCE_TYPES)
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->where('a.account_code', 'like', '7%')
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to))
            ->sum(DB::raw('l.credit - l.debit')), 2);
    }

    /* ===================================================================
     |  Helpers
     |===================================================================*/

    protected function key(string $kind, int $creditId, ?int $applicationId): string
    {
        return $kind . ':' . $creditId . ':' . ($applicationId ?? '-');
    }

    protected function dateOf($value): string
    {
        return $value ? substr((string) $value, 0, 10) : now()->toDateString();
    }

    /** The date itself while its month and year are open; today once they are closed. */
    protected function openDate(string $date): string
    {
        $year = FiscalYear::forDate($date);
        $period = $year ? FiscalYear::periodForDate($date) : null;

        if (($year && $year->is_closed) || ($period && $period->is_closed)) {
            return now()->toDateString();
        }

        return $date;
    }

    protected function incomeAccount(?int $categoryId): ?int
    {
        if (!array_key_exists((int) $categoryId, $this->incomeAccounts)) {
            $this->incomeAccounts[(int) $categoryId] = DefaultAccountMapping::where('mapping_type', 'fee_category')
                ->where('category_id', $categoryId)
                ->where('status', 'active')
                ->value('credit_account_id');
        }

        return $this->incomeAccounts[(int) $categoryId] ? (int) $this->incomeAccounts[(int) $categoryId] : null;
    }

    /** The cash, bank or mobile money account a prepayment came into. */
    protected function receiptCashAccount(StudentCredit $credit, ?int $categoryId): ?int
    {
        $cash = app(CashAccounts::class);
        $account = $credit->received_payment_account_id ? $cash->forPaymentAccount($credit->received_payment_account_id) : null;
        $account = $account ?: ($credit->received_method ? $cash->forCurrentMethod((int) $credit->received_method) : null);

        if (!$account) {
            $account = DefaultAccountMapping::where('mapping_type', 'fee_category')
                ->where('category_id', $categoryId)->where('status', 'active')->value('debit_account_id');
        }

        return $account ? (int) $account : null;
    }

    /** The cash or bank account a refund is paid from. */
    protected function refundCashAccount(?string $method, ?int $categoryId): ?int
    {
        $code = [
            'cash' => PaymentMethod::CASH,
            'bank_transfer' => PaymentMethod::BANK,
            'cheque' => PaymentMethod::BANK,
            'mobile_money' => PaymentMethod::MTN_MOMO,
        ][$method ?? 'cash'] ?? PaymentMethod::CASH;

        $account = app(CashAccounts::class)->forCurrentMethod($code);

        if (!$account) {
            // No cash account set for the method: the one the fee category posts receipts to.
            $account = DefaultAccountMapping::where('mapping_type', 'fee_category')
                ->where('category_id', $categoryId)->where('status', 'active')->value('debit_account_id');
        }

        return $account ? (int) $account : null;
    }
}
