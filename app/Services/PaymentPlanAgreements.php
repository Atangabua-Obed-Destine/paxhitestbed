<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanAgreement;
use App\Models\PaymentPlanAgreementEvent;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanPayment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything that happens to a payment plan agreement.
 *
 * The bursar prepares an agreement covering one or more of a student's fees.
 * It is printed, signed by the bursar and the student, and taken to the Vice
 * Chancellor, who signs it on paper and approves it here. Until then it takes
 * no money at all: not through the plan, and not directly on the fees it holds.
 *
 * An approved agreement that changes goes back for approval, and the change is
 * spelled out — whether the bursar revised the schedule or one of the fees it
 * covers changed underneath it. Approval is refused while the schedule no
 * longer adds up to what the fees say is owed, so a plan can never complete
 * while money is still due.
 *
 * Each fee keeps its own PaymentPlan, exactly as before. Nothing here changes
 * how a payment reaches a fee, the ledger or a reversal.
 */
class PaymentPlanAgreements
{
    /** Set while a plan changes its own fee (a late fee), so that is not a "change". */
    protected static int $quiet = 0;

    // ------------------------------------------------------------------
    // Preparing
    // ------------------------------------------------------------------

    /**
     * The fees a student could put on an agreement, with what each still owes.
     *
     * @return Collection<int, Fee>
     */
    public function eligibleFees(int $studentId): Collection
    {
        return Fee::with(['category', 'studentEnroll.session', 'paymentPlan', 'resitRequest'])
            ->whereHas('studentEnroll', fn ($q) => $q->where('student_id', $studentId))
            ->whereIn('status', ['0', '2'])
            ->get()
            ->filter(fn (Fee $fee) => $this->feeBlockers($fee, $studentId) === [])
            ->sortBy(fn (Fee $fee) => [
                optional($fee->category)->is_first_installment ? 0 : (optional($fee->category)->is_second_installment ? 1 : 2),
                (string) $fee->due_date,
                $fee->id,
            ])
            ->values();
    }

    /** Why this fee cannot go on a new agreement. */
    public function feeBlockers(Fee $fee, int $studentId): array
    {
        $title = optional($fee->category)->title ?? ('fee #' . $fee->id);

        if ((int) optional($fee->studentEnroll)->student_id !== $studentId) {
            return [__(':fee does not belong to this student.', ['fee' => $title])];
        }

        $reasons = [];

        if ((string) $fee->status === '3') {
            $reasons[] = __(':fee is cancelled.', ['fee' => $title]);
        }

        // A resit is paid in full, on its own: the resit rule refuses part
        // payments, and a plan is nothing but part payments.
        if ($fee->resitRequest) {
            $reasons[] = __(':fee is a resit fee, which is paid in full rather than on a plan.', ['fee' => $title]);
        }

        if ($this->balance($fee) <= 0.005) {
            $reasons[] = __(':fee has nothing left to pay.', ['fee' => $title]);
        }

        if ($fee->hasPaymentPlanHold()) {
            $reasons[] = __(':fee is already on payment plan agreement :ref.', [
                'fee' => $title,
                'ref' => optional(optional($fee->paymentPlan)->agreement)->reference ?? ('#' . $fee->payment_plan_id),
            ]);
        }

        if (method_exists($fee, 'pendingReceipt') && $fee->pendingReceipt()->exists()) {
            $reasons[] = __(':fee has a payment waiting to be verified. Settle that first.', ['fee' => $title]);
        }

        if (method_exists($fee, 'hasPendingMultiPayment') && $fee->hasPendingMultiPayment()) {
            $reasons[] = __(':fee has a split payment waiting to be verified. Settle that first.', ['fee' => $title]);
        }

        return $reasons;
    }

    /** What a fee still owes: the charge, less what has been paid on it. */
    public function balance(Fee $fee): float
    {
        return round(max(0, (float) $fee->total_amount - (float) ($fee->paid_amount ?? 0)), 2);
    }

    /**
     * Normalise and check a schedule as submitted.
     *
     * $spec is a list of ['fee_id' => int, 'installments' => [['amount', 'due_date'], ...]].
     *
     * @return array{0: array, 1: array<int, string>}  [cleaned spec, reasons]
     */
    public function check(array $spec, int $studentId, ?PaymentPlanAgreement $revising = null): array
    {
        $reasons = [];
        $clean = [];
        $seen = [];

        if ($spec === []) {
            return [[], [__('Choose at least one fee to put on the agreement.')]];
        }

        foreach ($spec as $entry) {
            $feeId = (int) ($entry['fee_id'] ?? 0);
            $fee = Fee::with(['category', 'studentEnroll', 'paymentPlan.agreement'])->find($feeId);
            $title = optional(optional($fee)->category)->title ?? ('fee #' . $feeId);

            if (!$fee || isset($seen[$feeId])) {
                $reasons[] = __('A fee appears twice, or does not exist.');
                continue;
            }
            $seen[$feeId] = true;

            $plan = $revising ? $revising->plans->firstWhere('fee_id', $feeId) : null;

            if ($revising && !$plan) {
                $reasons[] = __(':fee is not on this agreement. To add a fee, cancel the agreement and prepare a new one.', ['fee' => $title]);
                continue;
            }

            if (!$revising) {
                foreach ($this->feeBlockers($fee, $studentId) as $reason) {
                    $reasons[] = $reason;
                }
            }

            $rows = [];
            foreach ((array) ($entry['installments'] ?? []) as $row) {
                $amount = round((float) ($row['amount'] ?? 0), 2);
                $due = trim((string) ($row['due_date'] ?? ''));

                if ($amount <= 0) {
                    $reasons[] = __('Every instalment of :fee must be more than zero.', ['fee' => $title]);
                    continue;
                }

                try {
                    $date = Carbon::parse($due)->startOfDay();
                } catch (\Throwable $e) {
                    $reasons[] = __('An instalment of :fee has no valid due date.', ['fee' => $title]);
                    continue;
                }

                $rows[] = ['amount' => $amount, 'due_date' => $date->toDateString()];
            }

            $locked = $plan ? $this->lockedInstallments($plan) : collect();

            if ($rows === [] && $locked->isEmpty()) {
                $reasons[] = __(':fee needs at least one instalment.', ['fee' => $title]);
            }

            if (count($rows) + $locked->count() > 12) {
                $reasons[] = __(':fee can have at most 12 instalments.', ['fee' => $title]);
            }

            $expected = $plan ? $this->expectedPlanTotal($plan) : $this->balance($fee);
            $scheduled = round(array_sum(array_column($rows, 'amount'))
                + (float) $locked->sum(fn ($i) => (float) $i->amount), 2);

            if (abs($scheduled - $expected) >= 0.01) {
                $reasons[] = __('The instalments for :fee add up to :scheduled, but :expected is owed on it.', [
                    'fee' => $title,
                    'scheduled' => number_format($scheduled, 2),
                    'expected' => number_format($expected, 2),
                ]);
            }

            usort($rows, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));
            $clean[] = ['fee' => $fee, 'installments' => $rows];
        }

        if ($revising) {
            $open = $revising->plans->whereIn('status', ['pending', 'active'])->pluck('fee_id')->map(fn ($id) => (int) $id);

            if ($open->diff(array_keys($seen))->isNotEmpty()) {
                $reasons[] = __('Every fee on the agreement must keep a schedule. To drop a fee, cancel the agreement and prepare a new one.');
            }
        }

        return [$clean, array_values(array_unique($reasons))];
    }

    /**
     * Prepare an agreement. It is saved awaiting approval, and holds its fees.
     *
     * @throws \DomainException with the reasons, when the schedule cannot be accepted
     */
    public function create(Student $student, array $spec, ?string $notes, ?int $userId, int $graceDays = 7): PaymentPlanAgreement
    {
        [$clean, $reasons] = $this->check($spec, (int) $student->id);

        if ($reasons !== []) {
            throw new \DomainException(implode(' ', $reasons));
        }

        return DB::transaction(function () use ($student, $clean, $notes, $userId, $graceDays) {
            $agreement = PaymentPlanAgreement::create([
                'reference' => $this->nextReference(),
                'student_id' => $student->id,
                'status' => PaymentPlanAgreement::PENDING,
                'version' => 1,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            foreach ($clean as $entry) {
                $fee = Fee::lockForUpdate()->findOrFail($entry['fee']->id);

                if ($fee->payment_plan_id && optional($fee->paymentPlan)->holdsFee()) {
                    throw new \DomainException(__(':fee was put on another agreement while this one was being prepared.', [
                        'fee' => optional($fee->category)->title,
                    ]));
                }

                $plan = PaymentPlan::create([
                    'agreement_id' => $agreement->id,
                    'student_id' => $student->id,
                    'fee_id' => $fee->id,
                    'total_amount' => round(array_sum(array_column($entry['installments'], 'amount')), 2),
                    'installments_count' => count($entry['installments']),
                    'late_fee_percentage' => 0,
                    'grace_period_days' => $graceDays,
                    'created_by' => $userId,
                    'status' => 'pending',
                    'notes' => $notes,
                ]);

                $this->writeInstallments($plan, $entry['installments'], 1);

                $fee->update(['payment_plan_id' => $plan->id]);
            }

            $agreement->load('plans.installments', 'plans.fee.category');
            $this->record($agreement, 'created', $userId);

            return $agreement;
        });
    }

    /**
     * Change the schedule. Instalments that have taken money stay exactly as
     * they are; the rest is replaced by $spec. The agreement goes (back) to
     * awaiting approval, with what changed written down.
     */
    public function revise(PaymentPlanAgreement $agreement, array $spec, ?string $reason, ?int $userId): PaymentPlanAgreement
    {
        if ($agreement->isClosed()) {
            throw new \DomainException(__('This agreement is :status, so it can no longer be changed.', ['status' => $agreement->status_label]));
        }

        $agreement->load('plans.installments.payments', 'plans.fee.category');
        [$clean, $reasons] = $this->check($spec, (int) $agreement->student_id, $agreement);

        if ($reasons !== []) {
            throw new \DomainException(implode(' ', $reasons));
        }

        return DB::transaction(function () use ($agreement, $clean, $reason, $userId) {
            $before = $this->snapshot($agreement);

            foreach ($clean as $entry) {
                $plan = $agreement->plans->firstWhere('fee_id', $entry['fee']->id);
                $locked = $this->lockedInstallments($plan);

                $plan->installments()->whereNotIn('id', $locked->pluck('id'))->delete();
                $this->writeInstallments($plan, $entry['installments'], (int) $locked->max('installment_number') + 1);

                $plan->update([
                    'total_amount' => round((float) $locked->sum(fn ($i) => (float) $i->amount)
                        + array_sum(array_column($entry['installments'], 'amount')), 2),
                    'installments_count' => $locked->count() + count($entry['installments']),
                    'status' => $plan->status === 'completed' ? 'completed' : 'pending',
                ]);
            }

            $agreement->update([
                'status' => PaymentPlanAgreement::PENDING,
                'version' => $agreement->version + 1,
                'review_reason' => $reason,
            ]);

            $agreement->refresh()->load('plans.installments', 'plans.fee.category');
            $after = $this->snapshot($agreement);
            $this->record($agreement, 'revised', $userId, $reason, $this->diff($before, $after));

            return $agreement;
        });
    }

    // ------------------------------------------------------------------
    // Deciding
    // ------------------------------------------------------------------

    /** Why this agreement cannot be approved as it stands. */
    public function approvalBlockers(PaymentPlanAgreement $agreement): array
    {
        if (!$agreement->isPending()) {
            return [__('Only an agreement awaiting approval can be approved.')];
        }

        return $this->mismatches($agreement);
    }

    public function approve(PaymentPlanAgreement $agreement, ?int $userId): PaymentPlanAgreement
    {
        $agreement->load('plans.installments', 'plans.fee.category');
        $reasons = $this->approvalBlockers($agreement);

        if ($reasons !== []) {
            throw new \DomainException(implode(' ', $reasons));
        }

        return DB::transaction(function () use ($agreement, $userId) {
            foreach ($agreement->plans as $plan) {
                if ($plan->status === 'pending') {
                    $plan->update(['status' => 'active', 'approved_by' => $userId, 'approved_at' => now()]);
                }
            }

            $agreement->update([
                'status' => PaymentPlanAgreement::APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'approved_version' => $agreement->version,
                'review_reason' => null,
            ]);

            $this->record($agreement->refresh()->load('plans.installments', 'plans.fee.category'), 'approved', $userId);

            return $agreement;
        });
    }

    /**
     * End an agreement that will not go ahead. Its fees are released for
     * ordinary payment again; money already taken on a plan stays where it
     * was paid, exactly as cancelling a plan always has.
     */
    public function reject(PaymentPlanAgreement $agreement, string $reason, ?int $userId): PaymentPlanAgreement
    {
        if (!$agreement->isPending()) {
            throw new \DomainException(__('Only an agreement awaiting approval can be rejected.'));
        }

        return $this->close($agreement, PaymentPlanAgreement::REJECTED, $reason, $userId);
    }

    public function cancel(PaymentPlanAgreement $agreement, string $reason, ?int $userId): PaymentPlanAgreement
    {
        if ($agreement->isClosed()) {
            throw new \DomainException(__('This agreement is already :status.', ['status' => $agreement->status_label]));
        }

        return $this->close($agreement, PaymentPlanAgreement::CANCELLED, $reason, $userId);
    }

    protected function close(PaymentPlanAgreement $agreement, string $status, string $reason, ?int $userId): PaymentPlanAgreement
    {
        return DB::transaction(function () use ($agreement, $status, $reason, $userId) {
            foreach ($agreement->plans()->with('fee')->get() as $plan) {
                if (!$plan->holdsFee()) {
                    continue;
                }

                $paid = PaymentPlanPayment::whereIn('installment_id', $plan->installments()->pluck('id'))->active()->exists();

                if ($paid || $status === PaymentPlanAgreement::CANCELLED) {
                    // Money was taken: the plan ends as cancelled, and the money
                    // stays on the fee and in the ledger — it was really paid.
                    $plan->cancel($reason);
                } else {
                    $plan->update(['status' => 'rejected', 'cancellation_reason' => $reason]);
                    if ($plan->fee && (int) $plan->fee->payment_plan_id === (int) $plan->id) {
                        $plan->fee->update(['payment_plan_id' => null]);
                    }
                }
            }

            $agreement->update($status === PaymentPlanAgreement::REJECTED
                ? ['status' => $status, 'rejected_by' => $userId, 'rejected_at' => now(), 'rejection_reason' => $reason]
                : ['status' => $status, 'rejection_reason' => $reason]);

            $this->record($agreement->refresh()->load('plans.installments', 'plans.fee.category'),
                $status === PaymentPlanAgreement::REJECTED ? 'rejected' : 'cancelled', $userId, $reason);

            return $agreement;
        });
    }

    // ------------------------------------------------------------------
    // When a fee changes underneath an agreement
    // ------------------------------------------------------------------

    /** Run something that changes a plan's own fee without calling it a change. */
    public static function quietly(callable $work)
    {
        static::$quiet++;

        try {
            return $work();
        } finally {
            static::$quiet--;
        }
    }

    /**
     * Called whenever a fee is saved. If it is held by an agreement and what it
     * charges has changed, the agreement goes back for approval, saying what
     * changed. The schedule no longer matches the fee, so it cannot be
     * approved again until the bursar revises it.
     */
    public function feeChanged(Fee $fee): void
    {
        if (static::$quiet > 0 || !$fee->payment_plan_id) {
            return;
        }

        $moneyChanged = $fee->wasChanged(['fee_amount', 'discount_amount', 'fine_amount']);
        $cancelled = $fee->wasChanged('status') && (string) $fee->status === '3';

        if (!$moneyChanged && !$cancelled) {
            return;
        }

        $plan = PaymentPlan::with('agreement')->find($fee->payment_plan_id);
        $agreement = optional($plan)->agreement;

        if (!$plan || !$plan->holdsFee() || !$agreement || $agreement->isClosed()) {
            return;
        }

        $title = optional($fee->category)->title ?? ('fee #' . $fee->id);
        $changes = [];

        if ($moneyChanged) {
            $was = round((float) $fee->getOriginal('fee_amount') - (float) $fee->getOriginal('discount_amount') + (float) $fee->getOriginal('fine_amount'), 2);
            $now = round((float) $fee->fee_amount - (float) $fee->discount_amount + (float) $fee->fine_amount, 2);

            foreach (['fee_amount' => __('charge'), 'discount_amount' => __('discount'), 'fine_amount' => __('late fee')] as $column => $word) {
                if ($fee->wasChanged($column)) {
                    $changes[] = __(':fee: :what changed from :was to :now.', [
                        'fee' => $title, 'what' => $word,
                        'was' => number_format((float) $fee->getOriginal($column), 2),
                        'now' => number_format((float) $fee->{$column}, 2),
                    ]);
                }
            }

            $changes[] = __(':fee now comes to :now instead of :was.', [
                'fee' => $title, 'was' => number_format($was, 2), 'now' => number_format($now, 2),
            ]);
        }

        if ($cancelled) {
            $changes[] = __(':fee was cancelled.', ['fee' => $title]);
        }

        $this->returnForReview($agreement, __('A fee on this agreement changed.'), $changes, auth()->id());
    }

    public function returnForReview(PaymentPlanAgreement $agreement, string $reason, array $changes, ?int $userId): void
    {
        DB::transaction(function () use ($agreement, $reason, $changes, $userId) {
            if ($agreement->isApproved()) {
                foreach ($agreement->plans as $plan) {
                    if ($plan->status === 'active') {
                        $plan->update(['status' => 'pending']);
                    }
                }

                $agreement->update([
                    'status' => PaymentPlanAgreement::PENDING,
                    'version' => $agreement->version + 1,
                    'review_reason' => $reason,
                ]);
            } else {
                $agreement->update(['review_reason' => $reason]);
            }

            $this->record($agreement->refresh()->load('plans.installments', 'plans.fee.category'), 'returned', $userId, $reason, $changes);
        });
    }

    // ------------------------------------------------------------------
    // What the agreement says
    // ------------------------------------------------------------------

    /**
     * What a plan must add up to now: what its fee charges, less whatever was
     * paid on the fee other than through this plan.
     */
    public function expectedPlanTotal(PaymentPlan $plan): float
    {
        $fee = $plan->fee;

        if (!$fee) {
            return round((float) $plan->total_amount, 2);
        }

        $throughPlan = (float) PaymentPlanPayment::whereIn('installment_id', $plan->installments()->pluck('id'))->active()->sum('amount');
        $outside = (float) ($fee->paid_amount ?? 0) - $throughPlan;

        return round(max(0, (float) $fee->total_amount - $outside), 2);
    }

    /** Why the schedule no longer matches the fees it covers. */
    public function mismatches(PaymentPlanAgreement $agreement): array
    {
        $reasons = [];

        foreach ($agreement->plans as $plan) {
            if (in_array($plan->status, ['completed', 'cancelled', 'rejected'], true)) {
                continue;
            }

            $fee = $plan->fee;
            $title = optional(optional($fee)->category)->title ?? ('fee #' . $plan->fee_id);

            if (!$fee) {
                $reasons[] = __('The fee behind one plan no longer exists.');
                continue;
            }

            if ((string) $fee->status === '3') {
                $reasons[] = __(':fee has been cancelled. Cancel this agreement and prepare a new one.', ['fee' => $title]);
                continue;
            }

            $scheduled = round((float) $plan->installments->sum(fn ($i) => (float) $i->amount + (float) ($i->late_fee ?? 0)), 2);
            $expected = $this->expectedPlanTotal($plan);

            if (abs($scheduled - $expected) >= 0.01) {
                $reasons[] = __('The instalments for :fee add up to :scheduled, but :expected is owed on it now. Revise the schedule first.', [
                    'fee' => $title,
                    'scheduled' => number_format($scheduled, 2),
                    'expected' => number_format($expected, 2),
                ]);
            }
        }

        return $reasons;
    }

    /** The agreement's schedule as plain data, for history and comparison. */
    public function snapshot(PaymentPlanAgreement $agreement): array
    {
        $fees = [];

        foreach ($agreement->plans as $plan) {
            $fee = $plan->fee;
            $fees[(string) $plan->fee_id] = [
                'label' => optional(optional($fee)->category)->title ?? ('fee #' . $plan->fee_id),
                'net' => round((float) optional($fee)->total_amount, 2),
                'planned' => round((float) $plan->total_amount, 2),
                'status' => $plan->status,
                'installments' => $plan->installments->sortBy('installment_number')->values()->map(fn ($i) => [
                    'n' => (int) $i->installment_number,
                    'amount' => round((float) $i->amount, 2),
                    'due' => optional($i->due_date)->format('Y-m-d'),
                ])->all(),
            ];
        }

        return ['version' => (int) $agreement->version, 'fees' => $fees];
    }

    /** What changed between two snapshots, as sentences. */
    public function diff(?array $before, array $after): array
    {
        if (!$before) {
            return [];
        }

        $lines = [];
        $money = fn ($v) => number_format((float) $v, 2);

        foreach ($after['fees'] as $feeId => $now) {
            $was = $before['fees'][$feeId] ?? null;
            $label = $now['label'];

            if (!$was) {
                $lines[] = __(':fee was added, :amount over :count instalments.', [
                    'fee' => $label, 'amount' => $money($now['planned']), 'count' => count($now['installments']),
                ]);
                continue;
            }

            if (abs($was['net'] - $now['net']) >= 0.01) {
                $lines[] = __(':fee: the fee changed from :was to :now.', ['fee' => $label, 'was' => $money($was['net']), 'now' => $money($now['net'])]);
            }

            if (abs($was['planned'] - $now['planned']) >= 0.01) {
                $lines[] = __(':fee: the plan total changed from :was to :now.', ['fee' => $label, 'was' => $money($was['planned']), 'now' => $money($now['planned'])]);
            }

            $old = collect($was['installments'])->map(fn ($i) => $i['due'] . '|' . $money($i['amount']))->all();
            $new = collect($now['installments'])->map(fn ($i) => $i['due'] . '|' . $money($i['amount']))->all();

            if ($old !== $new) {
                $describe = fn ($rows) => collect($rows)->map(fn ($i) => Carbon::parse($i['due'])->format('d M Y') . ' ' . $money($i['amount']))->implode('; ');
                $lines[] = __(':fee: the schedule changed from [:was] to [:now].', [
                    'fee' => $label, 'was' => $describe($was['installments']), 'now' => $describe($now['installments']),
                ]);
            }
        }

        foreach ($before['fees'] as $feeId => $was) {
            if (!isset($after['fees'][$feeId])) {
                $lines[] = __(':fee was taken off the agreement.', ['fee' => $was['label']]);
            }
        }

        return $lines;
    }

    /**
     * What has changed since the agreement was last approved — what the Vice
     * Chancellor is being asked to approve again.
     */
    public function changesSinceApproval(PaymentPlanAgreement $agreement): array
    {
        if (!$agreement->wasApproved() || $agreement->isApproved()) {
            return [];
        }

        // events() is ordered oldest first; the latest approval is wanted.
        $approved = $agreement->events()->reorder('id', 'desc')->where('event', 'approved')->first();
        $later = $agreement->events()->where('id', '>', optional($approved)->id ?? 0)
            ->whereIn('event', ['revised', 'returned'])->get();

        $lines = $later->flatMap(fn ($event) => (array) $event->changes)->values()->all();

        // The schedule itself, compared end to end, when the approval kept one.
        if ($approved && $approved->snapshot) {
            $agreement->loadMissing('plans.installments', 'plans.fee.category');
            foreach ($this->diff($approved->snapshot, $this->snapshot($agreement)) as $line) {
                if (!in_array($line, $lines, true)) {
                    $lines[] = $line;
                }
            }
        }

        return array_values(array_unique($lines));
    }

    /**
     * The signature on a printed agreement: its reference, version, student and
     * every line of the schedule. Changing a figure on the paper, or printing
     * an old version, stops it verifying.
     */
    public function verificationCode(PaymentPlanAgreement $agreement): string
    {
        $agreement->loadMissing('plans.installments', 'plans.fee.category');

        return $this->codeFor($agreement, $this->snapshot($agreement));
    }

    /** The code a copy printed from this snapshot carried. */
    public function codeFor(PaymentPlanAgreement $agreement, array $snapshot): string
    {
        $lines = collect($snapshot['fees'] ?? [])->flatMap(fn ($fee, $feeId) => collect($fee['installments'] ?? [])->map(
            fn ($i) => $feeId . ':' . $i['due'] . ':' . number_format((float) $i['amount'], 2, '.', '')
        ))->sort()->implode(',');

        $payload = implode('|', ['agreement', $agreement->reference, (int) ($snapshot['version'] ?? 0), $agreement->student_id, $lines]);

        return strtoupper(substr(hash_hmac('sha256', $payload, config('app.key')), 0, 16));
    }

    /**
     * Which version a printed code belongs to: the current one, an older one,
     * or none. An older one is genuine but out of date — the agreement has
     * changed since it was printed.
     */
    public function versionForCode(PaymentPlanAgreement $agreement, string $code): ?int
    {
        if (hash_equals($this->verificationCode($agreement), $code)) {
            return (int) $agreement->version;
        }

        foreach ($agreement->events()->reorder('id', 'desc')->whereNotNull('snapshot')->get() as $event) {
            if (hash_equals($this->codeFor($agreement, $event->snapshot), $code)) {
                return (int) ($event->snapshot['version'] ?? $event->version);
            }
        }

        return null;
    }

    // ------------------------------------------------------------------

    /** Instalments that have taken money, ever: they are history and do not move. */
    public function lockedInstallments(PaymentPlan $plan): Collection
    {
        return $plan->installments()->get()->filter(fn (PaymentPlanInstallment $i) => (float) $i->paid_amount > 0.005
            || PaymentPlanPayment::where('installment_id', $i->id)->exists())->values();
    }

    protected function writeInstallments(PaymentPlan $plan, array $rows, int $firstNumber): void
    {
        $number = $firstNumber;

        foreach ($rows as $row) {
            $due = Carbon::parse($row['due_date']);

            PaymentPlanInstallment::create([
                'payment_plan_id' => $plan->id,
                'installment_number' => $number++,
                'amount' => $row['amount'],
                'due_date' => $due,
                'grace_period_ends' => $due->copy()->addDays((int) $plan->grace_period_days),
                'status' => 'pending',
            ]);
        }
    }

    protected function record(PaymentPlanAgreement $agreement, string $event, ?int $userId, ?string $reason = null, array $changes = []): void
    {
        PaymentPlanAgreementEvent::create([
            'agreement_id' => $agreement->id,
            'version' => $agreement->version,
            'event' => $event,
            'user_id' => $userId,
            'reason' => $reason,
            'changes' => $changes ?: null,
            'snapshot' => $this->snapshot($agreement),
            'created_at' => now(),
        ]);
    }

    protected function nextReference(): string
    {
        $prefix = 'PPA-' . date('Y') . '-';
        $last = PaymentPlanAgreement::where('reference', 'like', $prefix . '%')->lockForUpdate()
            ->orderByDesc('reference')->value('reference');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        do {
            $reference = $prefix . str_pad((string) $next++, 4, '0', STR_PAD_LEFT);
        } while (PaymentPlanAgreement::where('reference', $reference)->exists());

        return $reference;
    }
}
