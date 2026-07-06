<?php

namespace App\Services\Payment;

use App\Models\Fee;
use App\Models\MomoTransaction;
use App\Models\PaymentReceipt;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Auto-credit a Fee once a MoMo transaction has been confirmed by the provider.
 *
 * This mirrors Admin\PaymentVerificationController@approveFeeReceipt but is
 * callable from the payment gateway callbacks (no Request/no admin user
 * context needed) and is safe to call across all three guards (applicant,
 * student, web). It is idempotent per PaymentReceipt.
 */
class ApplicantFeePaymentService
{
    /**
     * Mark a MomoTransaction successful, create/update a PaymentReceipt already in
     * "approved" state, and credit the Fee. Returns the PaymentReceipt.
     */
    public function markMomoPaid(MomoTransaction $tx, array $providerPayload = []): PaymentReceipt
    {
        return DB::transaction(function () use ($tx, $providerPayload) {
            $tx->refresh();

            // Idempotency guard.
            if ($tx->status === 'successful' && $tx->payment_receipt_id) {
                return PaymentReceipt::findOrFail($tx->payment_receipt_id);
            }

            $fee = Fee::findOrFail($tx->fee_id);

            $receipt = new PaymentReceipt();
            $receipt->fee_id            = $fee->id;
            // For admission fees the payer is the applicant, not a Student.
            // Preserve provenance via applicant_id; keep student_id NULL until the
            // admin converts the applicant into a Student.
            $receipt->applicant_id      = $fee->applicant_id;
            $receipt->student_id        = $fee->applicant_id
                ? null
                : ($fee->studentEnroll->student_id ?? null);
            $receipt->receipt_file      = null;
            $receipt->payment_reference = $tx->reference_id;
            $receipt->payment_date      = now()->toDateString();
            $receipt->amount            = $tx->amount;
            $receipt->payment_method    = $tx->provider === 'orange' ? 7 : 6; // 6=MTN MoMo, 7=Orange
            $receipt->student_note      = 'Auto-paid via ' . strtoupper($tx->provider) . ' Mobile Money';
            $receipt->verification_status = 'approved';
            $receipt->verification_note = 'Auto-verified by ' . strtoupper($tx->provider) . ' MoMo. Ref ' . $tx->reference_id
                . (!empty($providerPayload['financialTransactionId']) ? ' / FT ' . $providerPayload['financialTransactionId'] : '');
            $receipt->verified_by       = Auth::guard('web')->id(); // null when initiated by applicant/student — that's OK
            $receipt->verified_at       = now();
            $receipt->save();

            // Cumulative payment on the fee.
            $newPaid = ($fee->paid_amount ?? 0) + $tx->amount;
            $totalDue = ($fee->fee_amount ?? 0) + ($fee->fine_amount ?? 0) - ($fee->discount_amount ?? 0);

            if ($newPaid >= $totalDue) {
                $status = 1; // Fully Paid
                $newPaid = $totalDue;
            } elseif ($newPaid > 0) {
                $status = 2; // Partially Paid
            } else {
                $status = 0;
            }

            $fee->paid_amount     = $newPaid;
            $fee->pay_date        = $receipt->payment_date;
            $fee->payment_method  = $receipt->payment_method;
            $fee->status          = $status;
            $fee->note            = 'Payment received via ' . strtoupper($tx->provider) . ' Mobile Money (ref ' . $tx->reference_id . ')';
            $fee->save();

            // Bookkeeping transaction row (mirrors Traits\FeesStudent::payStudentFee).
            $transaction = new Transaction();
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $tx->amount;
            $transaction->type = '1';
            $transaction->created_by = Auth::id();
            if ($fee->studentEnroll && $fee->studentEnroll->student) {
                $fee->studentEnroll->student->transactions()->save($transaction);
            } else {
                $transaction->save();
            }

            $tx->status = 'successful';
            $tx->payment_receipt_id = $receipt->id;
            $tx->completed_at = now();
            if (!empty($providerPayload['financialTransactionId'])) {
                $tx->financial_transaction_id = $providerPayload['financialTransactionId'];
            }
            $tx->save();

            return $receipt;
        });
    }

    /** Mark a MomoTransaction as failed with a reason. */
    public function markMomoFailed(MomoTransaction $tx, string $reason, array $providerPayload = []): void
    {
        $tx->refresh();
        if ($tx->status === 'successful') {
            return; // never downgrade a successful tx
        }
        $tx->status = 'failed';
        $tx->failure_reason = Str::limit($reason, 250, '');
        $tx->completed_at = now();
        if (!empty($providerPayload)) {
            $tx->raw_response = array_merge($tx->raw_response ?? [], ['final' => $providerPayload]);
        }
        $tx->save();
    }
}
