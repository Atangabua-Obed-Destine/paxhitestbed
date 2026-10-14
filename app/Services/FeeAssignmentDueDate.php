<?php

namespace App\Services;

use App\Models\Fee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Correcting the payment deadline on a fee that has already been assigned.
 *
 * A deadline is copied onto the student's row when the fee is raised, the same
 * way the amount is, and nothing reads the source again afterwards. So a date
 * entered wrongly — or left empty, which is why acceptance letters have been
 * going out saying "TBD" — stays wrong on every student raised from it.
 *
 * This is quieter than changing an amount, because nothing already recorded
 * moves: a late fee is worked out and stored at the moment a payment is taken
 * (FeesStudentController), and no scheduled job writes fines in between. What a
 * deadline decides is what happens NEXT — the fine the student will be charged
 * when they come to pay, and whether they show on the overdue lists.
 *
 * That is still money, so it is said out loud rather than left to be noticed:
 * fineIfPaidToday() answers what the late fee would be on either date, and the
 * screens show both before anything is written.
 *
 * One class, used by the single-row correction and the cohort one alike, so the
 * rule cannot be enforced in one place and forgotten in the other.
 */
class FeeAssignmentDueDate
{
    /** Written into the fee's note, so the correction is legible on the record. */
    public const MARKER = 'Deadline corrected';

    protected FeeLedgerPosting $ledger;

    public function __construct(FeeLedgerPosting $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Why this fee's deadline cannot be moved to that date.
     *
     * An empty array means it can. Each reason names the obstacle and what to
     * do about it — "cannot edit" on its own sends a bursar looking in the
     * wrong place.
     *
     * @return array<int, string>
     */
    public function blockers(Fee $fee, ?string $newDate): array
    {
        $reasons = [];

        $parsed = $this->parse($newDate);

        if (!$parsed) {
            $reasons[] = __('Enter the date the fee is due.');

            return $reasons;
        }

        $current = $this->parse($fee->due_date);

        if ($current && $parsed->isSameDay($current)) {
            $reasons[] = __('That is already the deadline on this fee.');
        }

        // The same rule the fees screens validate on when a fee is created: a
        // fee cannot fall due before it was charged.
        $assigned = $this->parse($fee->assign_date);

        if ($assigned && $parsed->lt($assigned)) {
            $reasons[] = __('The deadline cannot be before the fee was assigned on :date.', [
                'date' => $assigned->format('d M Y'),
            ]);
        }

        if ($this->isSettled($fee)) {
            // Moving it would rewrite the deadline a payment was already judged
            // against, and the late fee charged then stays charged either way.
            $reasons[] = __('This fee is settled. Its deadline is part of what was already paid.');
        }

        if ($fee->hasActivePaymentPlan()) {
            // The plan's instalments carry the dates the student was given in
            // writing. Moving the parent fee's deadline leaves the two
            // disagreeing about when money is owed.
            $reasons[] = __('This fee is on active payment plan #:plan. Its instalment dates are set on the plan.', [
                'plan' => $fee->payment_plan_id,
            ]);
        }

        if ($fee->resitRequest) {
            $reasons[] = __('A resit fee is dated by the resit configuration, not here.');
        }

        if ($fee->pendingReceipt()->exists()) {
            // The late fee is worked out when the receipt is verified, so
            // moving the date now would change what that payment settles.
            $reasons[] = __('A payment receipt on this fee is waiting to be verified. Settle that first.');
        }

        if (method_exists($fee, 'pendingMultiPaymentDistributions')
            && $fee->pendingMultiPaymentDistributions()->exists()) {
            $reasons[] = __('A payment covering several fees is still being processed against this one.');
        }

        return $reasons;
    }

    public function canChange(Fee $fee, ?string $newDate): bool
    {
        return $this->blockers($fee, $newDate) === [];
    }

    /**
     * The late fee this student would be charged if they paid today, with the
     * fee falling due on the given date.
     *
     * Deliberately the same arithmetic FeesStudentController does when a payment
     * is actually taken — days late against the category's fine bands, flat or
     * percentage — so what the screen promises is what the till will charge.
     * Nothing is written here.
     */
    public function fineIfPaidToday(Fee $fee, ?string $dueDate): float
    {
        $due = $this->parse($dueDate);

        if (!$due) {
            return 0.0;
        }

        $today = Carbon::today();

        if ($due->gte($today)) {
            return 0.0;
        }

        $days = (int) $due->diffInDays($today);
        $category = $fee->category;

        if (!$category) {
            return 0.0;
        }

        $fine = 0.0;

        foreach ($category->fines->where('status', '1') as $band) {
            if ($band->start_day <= $days && $band->end_day >= $days) {
                $fine += $band->type == '1'
                    ? (float) $band->amount
                    : ((float) $fee->fee_amount / 100) * (float) $band->amount;
            }
        }

        return round($fine, 2);
    }

    /**
     * Move it.
     *
     * @return array{changed: bool, reasons: array, old: ?string, new: ?string, fine_before: float, fine_after: float}
     */
    public function apply(Fee $fee, ?string $newDate, ?string $reason = null, ?int $userId = null): array
    {
        $reasons = $this->blockers($fee, $newDate);
        $currentRaw = $this->parse($fee->due_date);
        $oldRaw = $currentRaw ? $currentRaw->format('Y-m-d') : null;

        if ($reasons !== []) {
            return [
                'changed' => false,
                'reasons' => $reasons,
                'old' => $oldRaw,
                'new' => $newDate,
                'fine_before' => $this->fineIfPaidToday($fee, $oldRaw),
                'fine_after' => $this->fineIfPaidToday($fee, $newDate),
            ];
        }

        $new = $this->parse($newDate)->format('Y-m-d');
        $fineBefore = $this->fineIfPaidToday($fee, $oldRaw);
        $fineAfter = $this->fineIfPaidToday($fee, $new);

        DB::transaction(function () use ($fee, $oldRaw, $new, $reason, $userId) {
            $fee->due_date = $new;
            $fee->updated_by = $userId;
            $fee->note = trim((string) $fee->note . "\n" . $this->noteFor($oldRaw, $new, $reason));
            $fee->save();

            // Nothing in the ledger is dated from here — it posts cash as it is
            // received — but it is called so the two can never drift apart if
            // that ever stops being true.
            $this->ledger->resync($fee->fresh(), $userId);
        });

        $fee->refresh();

        return [
            'changed' => true,
            'reasons' => [],
            'old' => $oldRaw,
            'new' => $new,
            'fine_before' => $fineBefore,
            'fine_after' => $fineAfter,
        ];
    }

    /**
     * Paid off, by the same comparison the rest of the system makes.
     */
    protected function isSettled(Fee $fee): bool
    {
        $paid = (float) ($fee->paid_amount ?? 0);

        if ($paid <= 0.005) {
            return false;
        }

        $total = (float) ($fee->fee_amount ?? 0)
            + (float) ($fee->fine_amount ?? 0)
            - (float) ($fee->discount_amount ?? 0);

        return $paid >= $total - 0.005;
    }

    protected function parse($value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function noteFor(?string $old, string $new, ?string $reason): string
    {
        $line = self::MARKER . ' on ' . now()->format('d M Y') . ': '
            . ($old ? Carbon::parse($old)->format('d M Y') : __('none'))
            . ' to ' . Carbon::parse($new)->format('d M Y') . '.';

        $reason = trim((string) $reason);

        return $reason === '' ? $line : $line . ' ' . $reason;
    }
}
