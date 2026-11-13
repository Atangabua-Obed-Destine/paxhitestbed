<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Support\FeesPaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Spreading one handover of money across a payment plan.
 *
 * A student on a plan pays the way every other student here pays: toward what
 * they owe, not toward one line of a schedule. Handing over 100,000 against a
 * 50,000 instalment used to be refused outright, so the bursar recorded
 * instalment 2, went back, opened instalment 3, and recorded it again — two
 * entries, two confirmations, for one handful of notes.
 *
 * So the money is carried, in the order the student owes it:
 *
 *  1. the instalment the bursar opened;
 *  2. the plan's other unpaid instalments, by their number;
 *  3. once the plan is settled, the student's other instalment fees — the same
 *     fees, in the same order, that FeePaymentDistribution offers a payment
 *     made on an ordinary fee. That rule is asked of that class rather than
 *     copied here, so the two screens cannot disagree about it.
 *
 * Each leg is recorded exactly as it would be on its own screen: an instalment
 * leg through PaymentPlanInstallment::recordPayment(), a fee leg through
 * FeePaymentRecorder. Nothing about what either writes is restated here.
 *
 * Money beyond everything the student owes is REFUSED, deliberately. On the
 * ordinary fee screen that remainder is raised as a student credit — but only
 * the credit is written: no receipt, no cash-book entry, nothing on the fee.
 * Until that is settled there, this class will not create a second way for
 * cash to be handed over without reaching the books.
 */
class PlanPaymentDistribution
{
    public const TYPE_INSTALLMENT = 'installment';
    public const TYPE_FEE = 'fee';

    protected FeePaymentDistribution $fees;
    protected FeePaymentRecorder $recorder;

    public function __construct(FeePaymentDistribution $fees, FeePaymentRecorder $recorder)
    {
        $this->fees = $fees;
        $this->recorder = $recorder;
    }

    /**
     * The instalment a payment on this plan goes to first: the earliest one
     * that still has a balance and is not waiting on a multi-payment review.
     *
     * By number, not by due date in the future — an overdue instalment is
     * exactly the one a student walking in is there to pay.
     */
    public function nextInstallment(?PaymentPlan $plan): ?PaymentPlanInstallment
    {
        if (!$plan || !$plan->isActive()) {
            return null;
        }

        return $plan->installments
            ->sortBy('installment_number')
            ->first(fn (PaymentPlanInstallment $i) => $this->canTake($i));
    }

    protected function canTake(PaymentPlanInstallment $installment): bool
    {
        return !$installment->isPaid()
            && (float) $installment->remaining_balance > 0.005
            && !$installment->hasPendingMultiPayment();
    }

    /**
     * Everything this payment may be spread over, the opened instalment first.
     *
     * @return array<int, array{type: string, id: int, model: PaymentPlanInstallment|Fee, outstanding: float}>
     */
    public function targets(PaymentPlanInstallment $origin): array
    {
        $plan = $origin->paymentPlan;
        $targets = [];

        $targets[] = [
            'type' => self::TYPE_INSTALLMENT,
            'id' => (int) $origin->id,
            'model' => $origin,
            'outstanding' => round((float) $origin->remaining_balance, 2),
        ];

        if (!$plan) {
            return $targets;
        }

        foreach ($plan->installments()->orderBy('installment_number')->get() as $other) {
            if ((int) $other->id === (int) $origin->id || !$this->canTake($other)) {
                continue;
            }

            $targets[] = [
                'type' => self::TYPE_INSTALLMENT,
                'id' => (int) $other->id,
                'model' => $other,
                'outstanding' => round((float) $other->remaining_balance, 2),
            ];
        }

        // Then the other fees on the same agreement — a student who agreed a
        // plan for both instalments at once pays toward both — in the order
        // their instalments fall due.
        if ($plan->agreement_id) {
            $siblings = PaymentPlanInstallment::with('paymentPlan')
                ->whereHas('paymentPlan', fn ($q) => $q->where('agreement_id', $plan->agreement_id)
                    ->where('id', '!=', $plan->id)->where('status', 'active'))
                ->orderBy('due_date')->orderBy('installment_number')
                ->get();

            foreach ($siblings as $other) {
                if (!$this->canTake($other)) {
                    continue;
                }

                $targets[] = [
                    'type' => self::TYPE_INSTALLMENT,
                    'id' => (int) $other->id,
                    'model' => $other,
                    'outstanding' => round((float) $other->remaining_balance, 2),
                ];
            }
        }

        if ($plan->fee) {
            // The first target FeePaymentDistribution returns is the fee asked
            // about — here the plan's own fee, which the instalments above
            // already cover. Everything after it is the student's other
            // instalment fees, filtered and ordered by its rule.
            foreach ($this->fees->targets($plan->fee)->slice(1) as $fee) {
                $targets[] = [
                    'type' => self::TYPE_FEE,
                    'id' => (int) $fee->id,
                    'model' => $fee,
                    'outstanding' => $this->recorder->outstanding($fee),
                ];
            }
        }

        return $targets;
    }

    /**
     * How an amount would be spread, filling each target in turn.
     *
     * Nothing is written. `leftover` is what remains once everything the
     * student owes is settled; a spread with any leftover cannot be recorded.
     *
     * @return array{legs: array<int, array>, leftover: float, total: float, owed: float}
     */
    public function propose(PaymentPlanInstallment $origin, float $amount): array
    {
        $remaining = round($amount, 2);
        $legs = [];
        $owed = 0.0;

        foreach ($this->targets($origin) as $target) {
            $outstanding = $target['outstanding'];
            $owed = round($owed + max(0, $outstanding), 2);

            if ($remaining <= 0.005 || $outstanding <= 0.005) {
                continue;
            }

            $applied = min($remaining, $outstanding);
            $remaining = round($remaining - $applied, 2);

            $legs[] = [
                'type' => $target['type'],
                'id' => $target['id'],
                'label' => $this->label($target),
                'due_date' => $this->dueDate($target),
                'outstanding' => round($outstanding, 2),
                'applied' => round($applied, 2),
                'remaining_after' => round($outstanding - $applied, 2),
                'is_origin' => $target['type'] === self::TYPE_INSTALLMENT && $target['id'] === (int) $origin->id,
            ];
        }

        return [
            'legs' => $legs,
            'leftover' => round($remaining, 2),
            'total' => round($amount, 2),
            // Everything this student could put money on from this screen.
            'owed' => $owed,
        ];
    }

    /**
     * Why this spread cannot be recorded as it stands.
     *
     * `$legs` is what the bursar submitted — they may have moved money between
     * the lines — so each is checked again here rather than trusted. An empty
     * array means it can go ahead.
     *
     * @param array<int, array{type: string, id: int|string, applied: float|string}> $legs
     * @return array<int, string>
     */
    public function blockers(PaymentPlanInstallment $origin, array $legs, float $amount): array
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return [__('The amount must be more than zero.')];
        }

        if ($legs === []) {
            return [__('Nothing was allocated.')];
        }

        $plan = $origin->paymentPlan;

        if (!$plan || !$plan->isActive()) {
            return [__('This payment plan is :status, so it cannot take any more money.', [
                'status' => $plan->status ?? __('missing'),
            ])];
        }

        $allowed = [];
        $owed = 0.0;

        foreach ($this->targets($origin) as $target) {
            $allowed[$target['type'] . ':' . $target['id']] = $target;
            $owed = round($owed + max(0, $target['outstanding']), 2);
        }

        $reasons = [];
        $sum = 0.0;
        $seen = [];

        foreach ($legs as $leg) {
            $key = ($leg['type'] ?? '') . ':' . (int) ($leg['id'] ?? 0);
            $applied = round((float) ($leg['applied'] ?? 0), 2);
            $sum = round($sum + $applied, 2);

            if (isset($seen[$key])) {
                $reasons[] = __('The same line appears twice in the split.');
                continue;
            }

            $seen[$key] = true;

            if (!isset($allowed[$key])) {
                // Another plan's instalment, another student's fee, a line
                // already settled or one under review: one answer for all.
                $reasons[] = __('One of the lines in the split cannot take part of this payment.');
                continue;
            }

            if ($applied < 0) {
                $reasons[] = __('A negative amount was allocated to :line.', ['line' => $this->label($allowed[$key])]);
                continue;
            }

            if ($applied - $allowed[$key]['outstanding'] > 0.005) {
                $reasons[] = __(':amount was allocated to :line, which only needs :outstanding.', [
                    'amount' => number_format($applied, 2),
                    'line' => $this->label($allowed[$key]),
                    'outstanding' => number_format($allowed[$key]['outstanding'], 2),
                ]);
            }
        }

        if ($sum - $amount > 0.005) {
            $reasons[] = __('The split comes to :sum, which is more than the :amount received.', [
                'sum' => number_format($sum, 2),
                'amount' => number_format($amount, 2),
            ]);
        }

        // Every franc handed over has to land on something the student owes.
        $unplaced = round($amount - $sum, 2);

        if ($unplaced > 0.005) {
            $reasons[] = $amount - $owed > 0.005
                ? __(':amount is more than this student owes on the plan and their other instalments (:owed). Record :owed or less.', [
                    'amount' => number_format($amount, 2),
                    'owed' => number_format($owed, 2),
                ])
                : __(':unplaced of this payment has not been put on any instalment. Allocate it to a line that still has a balance.', [
                    'unplaced' => number_format($unplaced, 2),
                ]);
        }

        return $reasons;
    }

    /**
     * Write every leg. Must be called inside a transaction, after blockers()
     * has returned nothing.
     *
     * `$payment` carries what the bursar entered, in the plan's own terms:
     * payment_method (the plan's numbering), payment_date, payment_account_id,
     * reference_no, receipt_path, note.
     *
     * @return array<int, array{type: string, id: int, label: string, applied: float}>
     */
    public function record(PaymentPlanInstallment $origin, array $legs, array $payment): array
    {
        $userId = Auth::guard('web')->id();

        // One handover, several rows. Every row it writes — instalment
        // payments and fee receipts alike — carries the same group, so the
        // receipt can show the whole of what the student handed over.
        $written = array_filter($legs, fn ($leg) => round((float) ($leg['applied'] ?? 0), 2) > 0.005);
        $group = count($written) > 1 ? (string) Str::uuid() : null;
        $done = [];

        foreach ($legs as $leg) {
            $applied = round((float) ($leg['applied'] ?? 0), 2);

            if ($applied <= 0.005) {
                continue;
            }

            if ($leg['type'] === self::TYPE_INSTALLMENT) {
                // Read again under a lock: the balance on the form may have
                // been settled by another admin, or by an approved receipt,
                // while this one was being filled in. recordPayment decides;
                // this makes sure it decides on what is true now.
                $installment = PaymentPlanInstallment::whereKey((int) $leg['id'])->lockForUpdate()->firstOrFail();

                $installment->recordPayment($applied, [
                    'payment_method' => $payment['payment_method'],
                    'payment_date' => $payment['payment_date'],
                    'payment_account_id' => $payment['payment_account_id'] ?? null,
                    'reference_no' => $payment['reference_no'] ?? null,
                    'receipt_path' => $payment['receipt_path'] ?? null,
                    'paid_by_type' => 'App\User',
                    'paid_by_id' => $userId,
                    'note' => $payment['note'] ?? null,
                    'payment_group' => $group,
                ]);

                $done[] = [
                    'type' => self::TYPE_INSTALLMENT,
                    'id' => (int) $installment->id,
                    'label' => __('Instalment #:number', ['number' => $installment->installment_number]),
                    'applied' => $applied,
                ];

                continue;
            }

            $fee = Fee::with(['category', 'studentEnroll.student'])->lockForUpdate()->findOrFail((int) $leg['id']);

            $this->recorder->record($fee, $applied, [
                'pay_date' => $payment['payment_date'],
                // Fees number their methods differently from plans. Sent as
                // typed, a plan's "Bank Transfer" would be stored on the fee
                // as the value that fee screens read as something else.
                'payment_method' => FeesPaymentMethod::fromPlan($payment['payment_method']),
                'payment_account_id' => $payment['payment_account_id'] ?? null,
                'note' => $payment['note'] ?? null,
                'user_id' => $userId,
                'payment_group' => $group,
            ]);

            $done[] = [
                'type' => self::TYPE_FEE,
                'id' => (int) $fee->id,
                'label' => $fee->category->title ?? __('field_fees'),
                'applied' => $applied,
            ];
        }

        return $done;
    }

    protected function label(array $target): string
    {
        if ($target['type'] === self::TYPE_INSTALLMENT) {
            // Named with its fee: one agreement can carry instalments of two.
            $fee = optional(optional($target['model']->paymentPlan)->fee)->category;

            return trim(($fee->title ?? '') . ' · ' . __('Instalment #:number', ['number' => $target['model']->installment_number]), ' ·');
        }

        return optional($target['model']->category)->title ?? ('fee #' . $target['id']);
    }

    protected function dueDate(array $target): ?string
    {
        $date = $target['model']->due_date;

        return $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
    }
}
