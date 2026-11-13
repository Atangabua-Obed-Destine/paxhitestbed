<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanPayment;
use App\Models\PaymentReceipt;
use App\Models\Program;
use App\Models\Setting;
use App\Models\StudentCredit;

/**
 * Everything a fee receipt needs to say.
 *
 * Assembled here rather than in the two controllers that print it - the admin
 * screen and the student's own portal render the SAME view, and they used to
 * hand it different data, so the receipt a bursar printed and the one a student
 * downloaded were not the same document.
 *
 * It also replaces the old print-setting header. A receipt built from
 * `print_settings` carried its own copy of the school's name and logo, which
 * disagreed with the letterhead every other document uses. The letterhead is now
 * the single source, exactly as the admission receipt does it.
 */
class FeeReceipt
{
    /**
     * @return array<string, mixed>
     */
    public function for(Fee $fee): array
    {
        $fee->loadMissing([
            'category',
            'paymentPlan.installments.payments',
            'resitRequest.subject',
            'resitRequest.session',
            'studentEnroll.student',
            'studentEnroll.program.degreeType',
            'studentEnroll.session',
            'studentEnroll.semester',
            'studentEnroll.section',
            'paymentAccount',
        ]);

        $handover = $this->handoverFor($fee);
        $studentId = optional($fee->studentEnroll)->student_id;

        return [
            'fee' => $fee,
            'handover' => $handover,
            // Kept for anything still reading them: the fee receipt behind the
            // handover, if it was one, and every line the handover settled.
            'receipt' => $handover && $handover['anchor'] instanceof PaymentReceipt ? $handover['anchor'] : null,
            'legs' => $handover ? $handover['legs'] : collect(),
            'plan' => $this->planFor($fee, $handover),
            'instalments' => $this->instalments($studentId),
            'credits' => $studentId
                ? StudentCredit::where('source_fee_id', $fee->id)->get()
                : collect(),
            'setting' => Setting::first(),
            'currency' => $this->currency(),
            'verificationCode' => $handover['code'] ?? null,
            'verificationUrl' => $handover['url'] ?? null,
            'status' => $this->status($fee),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The handover
    |--------------------------------------------------------------------------
    |
    | A receipt is for money the student handed over, and one handover can write
    | several rows: a fee receipt per fee it settled, a plan payment per
    | instalment. Each kind of row carries a payment_group when it was one of
    | several, so from any of them the whole handover can be found again.
    |
    | The receipt for a fee shows the most recent handover that touched it,
    | whichever way the money came: an ordinary payment writes a fee receipt,
    | a payment on a plan writes plan payments. The receipt used to read fee
    | receipts only, so a fee paid through its plan printed the last payment
    | made BEFORE the plan, with that payment's date, amount and cashier.
    |
    */

    /** The most recent handover that put money on this fee, or null if none did. */
    public function handoverFor(Fee $fee): ?array
    {
        $receipt = PaymentReceipt::with('verifier')
            ->where('fee_id', $fee->id)
            // Only money the school accepted. A student's upload still waiting
            // for review, or one turned down, is not a receipt for anything.
            ->where('verification_status', 'approved')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->first();

        $planPayment = PaymentPlanPayment::with(['installment.paymentPlan', 'paidBy'])
            ->active()
            ->whereHas('installment.paymentPlan', fn ($q) => $q->where('fee_id', $fee->id))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->first();

        // Whichever was recorded last is the payment this receipt is for.
        if ($planPayment && (!$receipt || $planPayment->created_at >= $receipt->created_at)) {
            return $this->handoverFromPlanPayment($planPayment);
        }

        return $receipt ? $this->handoverFromReceipt($receipt) : null;
    }

    public function handoverFromReceipt(PaymentReceipt $receipt): array
    {
        $receipt->loadMissing(['verifier', 'fee.category', 'fee.studentEnroll']);

        return $this->assemble($receipt, [
            'kind' => 'receipt',
            'date' => $receipt->payment_date,
            'method' => $receipt->payment_method_name,
            'reference' => $receipt->payment_reference,
            'cashier' => optional($receipt->verifier)->name,
            'recorded_at' => $receipt->verified_at ?? $receipt->created_at,
            'student_id' => $receipt->student_id ?? optional(optional($receipt->fee)->studentEnroll)->student_id,
        ]);
    }

    public function handoverFromPlanPayment(PaymentPlanPayment $payment): array
    {
        $payment->loadMissing(['installment.paymentPlan.fee.category', 'paidBy']);
        $plan = optional($payment->installment)->paymentPlan;

        return $this->assemble($payment, [
            'kind' => 'plan',
            'date' => $payment->payment_date,
            'method' => $payment->payment_method_label,
            // A plan payment has no generated reference of its own; the one the
            // bursar typed wins, and failing that the row itself is named.
            'reference' => $payment->reference_no ?: 'PLAN-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            'cashier' => optional($payment->paidBy)->name,
            'recorded_at' => $payment->created_at,
            'student_id' => optional($plan)->student_id,
        ]);
    }

    /**
     * The handover around one of its rows: every line, the total, and the
     * code that lets a printed sheet be checked against it.
     */
    protected function assemble($anchor, array $details): array
    {
        $group = $anchor->payment_group;

        $receipts = $group
            ? PaymentReceipt::with('fee.category')->where('payment_group', $group)->where('verification_status', 'approved')->get()
            : ($anchor instanceof PaymentReceipt ? collect([$anchor]) : collect());

        $planPayments = $group
            ? PaymentPlanPayment::with('installment.paymentPlan.fee.category')->where('payment_group', $group)->active()->get()
            : ($anchor instanceof PaymentPlanPayment ? collect([$anchor]) : collect());

        $legs = collect();

        foreach ($planPayments as $payment) {
            $plan = optional($payment->installment)->paymentPlan;

            $legs->push([
                'kind' => 'plan',
                'id' => (int) $payment->id,
                'fee_id' => (int) optional($plan)->fee_id,
                'label' => trim((optional(optional(optional($plan)->fee)->category)->title ?? __('field_fees'))
                    . ' · ' . __('plan instalment :number of :count', [
                        'number' => optional($payment->installment)->installment_number,
                        'count' => optional($plan)->installments_count,
                    ])),
                'amount' => round((float) $payment->amount, 2),
                'order' => sprintf('0-%05d-%09d', (int) optional($payment->installment)->installment_number, (int) $payment->id),
            ]);
        }

        foreach ($receipts as $receipt) {
            $legs->push([
                'kind' => 'receipt',
                'id' => (int) $receipt->id,
                'fee_id' => (int) $receipt->fee_id,
                'label' => optional(optional($receipt->fee)->category)->title ?? __('field_fees'),
                'amount' => round((float) $receipt->amount, 2),
                'order' => sprintf('1-%05d-%09d', 0, (int) $receipt->id),
            ]);
        }

        // The plan's instalments first, in their order, then whatever the
        // money was carried onto, in the order it was written.
        $legs = $legs->sortBy('order')->values()->map(function ($leg) {
            unset($leg['order']);

            return $leg;
        });

        $handover = $details + [
            'anchor' => $anchor,
            'group' => $group,
            'legs' => $legs,
            'total' => round((float) $legs->sum('amount'), 2),
        ];

        // A single fee receipt keeps the code and address it has always had,
        // so every receipt already printed still verifies. Anything else is
        // verified as a handover, against everything it settled.
        if ($anchor instanceof PaymentReceipt && !$group) {
            $handover['code'] = $this->verificationCodeFor($anchor);
            $handover['url'] = route('verify-fee', $anchor->id) . '?code=' . $handover['code'];
        } else {
            $handover['code'] = $this->handoverCode($handover);
            $handover['url'] = route('verify-payment', ['kind' => $details['kind'], 'id' => $anchor->id])
                . '?code=' . $handover['code'];
        }

        return $handover;
    }

    /** What this handover put on one fee. */
    public function onFee(?array $handover, int $feeId): float
    {
        if (!$handover) {
            return 0.0;
        }

        return round((float) $handover['legs']->where('fee_id', $feeId)->sum('amount'), 2);
    }

    /**
     * A handover's signature: who paid, when, how much, and exactly which rows
     * it wrote. Changing any figure on the sheet, or adding a line to it,
     * stops it verifying.
     */
    public function handoverCode(array $handover): string
    {
        $legs = $handover['legs']
            ->map(fn ($leg) => $leg['kind'] . ':' . $leg['id'] . ':' . number_format($leg['amount'], 2, '.', ''))
            ->sort()->implode(',');

        $payload = implode('|', [
            'handover',
            (string) ($handover['student_id'] ?? ''),
            optional($handover['date'])->toDateString(),
            number_format($handover['total'], 2, '.', ''),
            $legs,
        ]);

        return strtoupper(substr(hash_hmac('sha256', $payload, config('app.key')), 0, 16));
    }

    /**
     * The payment plan behind this fee, kept on the receipt after it is paid.
     *
     * A completed plan is unlinked from its fee, so reading it through
     * fees.payment_plan_id made the schedule vanish from the receipt at the
     * very moment the plan was paid off. It is found by the plan's own
     * fee_id instead. A cancelled plan is not shown: it no longer describes
     * how the fee is being paid.
     */
    public function planFor(Fee $fee, ?array $handover = null): ?PaymentPlan
    {
        if ($handover && $handover['kind'] === 'plan') {
            $plan = optional($handover['anchor']->installment)->paymentPlan;

            if ($plan && (int) $plan->fee_id === (int) $fee->id) {
                return $plan->loadMissing('installments');
            }
        }

        return PaymentPlan::with('installments')
            ->where('fee_id', $fee->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The student's instalments, so one sheet answers "where do I stand".
     *
     * Found by student rather than by enrolment: for the 2025/2026 intake the
     * second instalment was raised months later against a different enrolment,
     * so looking by enrolment finds nothing for that cohort.
     */
    public function instalments(?int $studentId)
    {
        if (!$studentId) {
            return collect();
        }

        return Fee::with(['category', 'studentEnroll.session'])
            ->whereHas('studentEnroll', fn ($query) => $query->where('student_id', $studentId))
            ->whereHas('category', function ($query) {
                $query->where('is_first_installment', 1)->orWhere('is_second_installment', 1);
            })
            ->where('status', '!=', '3')
            ->get()
            ->sortBy(fn ($row) => optional($row->category)->is_first_installment ? 0 : 1)
            ->values();
    }

    /**
     * @return array{label: string, class: string}
     */
    public function status(Fee $fee): array
    {
        $paid = (float) ($fee->paid_amount ?? 0);
        $due = (float) $fee->total_amount;

        if ($paid >= $due - 0.005 && $paid > 0) {
            return ['label' => __('Fully Paid'), 'class' => ''];
        }

        if ($paid > 0.005) {
            return ['label' => __('Partial Payment'), 'class' => 'partial'];
        }

        return ['label' => __('Unpaid'), 'class' => 'unpaid'];
    }

    /**
     * The same signature the admission receipt uses, so both kinds of receipt
     * verify the same way and a scanner needs to know only one scheme.
     */
    public function verificationCodeFor(PaymentReceipt $receipt): string
    {
        $payload = implode('|', [
            $receipt->id,
            $receipt->fee_id,
            $receipt->student_id,
            $receipt->payment_reference,
            (string) $receipt->amount,
            optional($receipt->payment_date)->toDateString(),
        ]);

        return strtoupper(substr(hash_hmac('sha256', $payload, config('app.key')), 0, 16));
    }

    /**
     * Spelled out, because a figure alone can be altered on a printed sheet.
     */
    public function inWords(float $amount): string
    {
        if (class_exists(\NumberFormatter::class)) {
            try {
                $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::SPELLOUT);

                return ucfirst($formatter->format((int) floor($amount)));
            } catch (\Throwable $e) {
                // Fall through to the figure below.
            }
        }

        return number_format($amount, 0);
    }

    public function currency(): string
    {
        $setting = Setting::first();

        return $setting->currency_symbol ?? ($setting->currency ?? config('momo.currency', 'XAF'));
    }

    /**
     * The programme and level this fee belongs to, for the receipt's header
     * block. Taken from the enrolment, which is what the student was actually
     * put onto.
     */
    public function programme(Fee $fee): ?Program
    {
        return optional($fee->studentEnroll)->program;
    }
}
