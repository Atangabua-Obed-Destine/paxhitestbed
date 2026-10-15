<?php

namespace App\Services;

use App\Models\Fee;
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

        $receipt = PaymentReceipt::with('verifier')
            ->where('fee_id', $fee->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->first();

        // One handover can settle more than one instalment. Those legs share a
        // payment group, so the receipt can show every fee that one payment
        // actually covered instead of pretending it covered this one alone.
        $legs = collect();

        if ($receipt) {
            $legs = $receipt->payment_group
                ? PaymentReceipt::with('fee.category')
                    ->where('payment_group', $receipt->payment_group)
                    ->orderBy('id')->get()
                : collect([$receipt->loadMissing('fee.category')]);
        }

        $studentId = optional($fee->studentEnroll)->student_id;

        return [
            'fee' => $fee,
            'receipt' => $receipt,
            'legs' => $legs,
            'instalments' => $this->instalments($studentId),
            'credits' => $studentId
                ? StudentCredit::where('source_fee_id', $fee->id)->get()
                : collect(),
            'setting' => Setting::first(),
            'currency' => $this->currency(),
            'verificationCode' => $receipt ? $this->verificationCodeFor($receipt) : null,
            'status' => $this->status($fee),
        ];
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
