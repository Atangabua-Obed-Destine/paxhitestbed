<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentReceipt;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Recording one payment against one fee.
 *
 * This is lifted verbatim out of FeesStudentController::pay(), where it was the
 * middle of a two-hundred-line method. Nothing about what it writes has changed:
 * the same discount and fine arithmetic, the same columns on the fee, the same
 * student transaction, payment receipt and payment-account entry, in the same
 * order.
 *
 * It was moved because a payment spread across a student's instalments has to
 * record each leg EXACTLY as an ordinary payment is recorded. Two copies of this
 * sequence would drift — and a leg that is recorded almost-but-not-quite like a
 * payment is the kind of fault that is only noticed at an audit.
 *
 * What is deliberately NOT here: whether an overpayment is allowed, and what
 * becomes of money beyond the fee's balance. Those are policy, they differ
 * between the single-fee and the distributed path, and they stay with the
 * caller.
 */
class FeePaymentRecorder
{
    /**
     * The discount this fee attracts today.
     *
     * Recomputed from the category on every payment rather than read off the
     * fee, which is how it has always worked here: a discount that has since
     * expired stops applying to the next payment.
     */
    public function discountFor(Fee $fee): float
    {
        $discount_amount = 0;
        $today = date('Y-m-d');

        if (isset($fee->category)) {
            foreach ($fee->category->discounts->where('status', '1') as $discount) {
                $availability = \App\Models\FeesDiscount::availability($discount->id, $fee->studentEnroll->student_id);

                if (isset($availability)) {
                    if ($discount->start_date <= $today && $discount->end_date >= $today) {
                        if ($discount->type == '1') {
                            $discount_amount = $discount_amount + $discount->amount;
                        } else {
                            $discount_amount = $discount_amount + (($fee->fee_amount / 100) * $discount->amount);
                        }
                    }
                }
            }
        }

        return (float) $discount_amount;
    }

    /**
     * The late fee this fee attracts today, by the category's fine bands.
     */
    public function fineFor(Fee $fee): float
    {
        $fine_amount = 0;

        if (empty($fee->pay_date) || $fee->due_date < $fee->pay_date) {
            $due_date = strtotime($fee->due_date);
            $today = strtotime(date('Y-m-d'));
            $days = (int) (($today - $due_date) / 86400);

            if ($fee->due_date < date('Y-m-d')) {
                if (isset($fee->category)) {
                    foreach ($fee->category->fines->where('status', '1') as $fine) {
                        if ($fine->start_day <= $days && $fine->end_day >= $days) {
                            if ($fine->type == '1') {
                                $fine_amount = $fine_amount + $fine->amount;
                            } else {
                                $fine_amount = $fine_amount + (($fee->fee_amount / 100) * $fine->amount);
                            }
                        }
                    }
                }
            }
        }

        return (float) $fine_amount;
    }

    /**
     * What the student is actually being asked for: the charge, less any
     * discount, plus any late fee.
     */
    public function netAmount(Fee $fee, float $discount, float $fine): float
    {
        return ($fee->fee_amount - $discount) + $fine;
    }

    /**
     * What this fee still needs, after the discount and fine as they stand today.
     */
    public function outstanding(Fee $fee): float
    {
        $net = $this->netAmount($fee, $this->discountFor($fee), $this->fineFor($fee));

        return round(max(0, $net - (float) ($fee->paid_amount ?? 0)), 2);
    }

    /**
     * Write the payment.
     *
     * $payment carries what the bursar entered: pay_date, payment_method,
     * payment_account_id, note, and optionally user_id and payment_group.
     *
     * Must be called inside a transaction — it writes to five tables and the
     * caller owns the boundary, exactly as the controller did before.
     *
     * @return array{net_amount: float, total_paid: float, status: string, overpaid: float, receipt: PaymentReceipt}
     */
    public function record(Fee $fee, float $amount, array $payment): array
    {
        $userId = $payment['user_id'] ?? Auth::guard('web')->user()->id;

        $discount_amount = $this->discountFor($fee);
        $fine_amount = $this->fineFor($fee);
        $net_amount = $this->netAmount($fee, $discount_amount, $fine_amount);

        $current_paid = $fee->paid_amount ?? 0;
        $new_payment = $amount;
        $total_paid = $current_paid + $new_payment;

        // Only ever 1 or 2 here: a payment never leaves a fee unpaid.
        $status = $total_paid >= $net_amount ? '1' : '2';

        $fee->discount_amount = $discount_amount;
        $fee->fine_amount = $fine_amount;
        $fee->paid_amount = $total_paid;
        $fee->pay_date = $payment['pay_date'];
        $fee->payment_method = $payment['payment_method'];
        $fee->payment_account_id = $payment['payment_account_id'] ?? null;
        $fee->note = $payment['note'] ?? null;
        $fee->status = $status;
        $fee->updated_by = $userId;
        $fee->save();

        // Student ledger.
        $transaction = new Transaction;
        $transaction->transaction_id = Str::random(16);
        $transaction->amount = $new_payment;
        $transaction->type = '1';
        $transaction->created_by = $userId;
        $fee->studentEnroll->student->transactions()->save($transaction);

        $receipt = PaymentReceipt::create([
            'fee_id' => $fee->id,
            'student_id' => $fee->studentEnroll->student_id,
            'payment_reference' => 'ADMIN-' . date('Ymd') . '-' . $fee->id . '-' . Str::random(4),
            'payment_date' => $payment['pay_date'],
            'amount' => $new_payment,
            'payment_method' => $payment['payment_method'],
            'verification_status' => 'approved', // Admin payments are auto-approved
            'verified_by' => $userId,
            'verified_at' => now(),
            'payment_account_id' => $payment['payment_account_id'] ?? null,
            'student_note' => 'Direct payment recorded by admin',
            'verification_note' => 'Admin direct payment',
            // Set only when one payment was spread over several fees, so the
            // receipt can later show every fee that one handover settled.
            'payment_group' => $payment['payment_group'] ?? null,
        ]);

        if (!empty($payment['payment_account_id'])) {
            $payment_account = PaymentAccount::findOrFail($payment['payment_account_id']);
            $new_balance = $payment_account->current_balance + $new_payment;

            $account_transaction = new PaymentAccountTransaction;
            $account_transaction->payment_account_id = $payment['payment_account_id'];
            $account_transaction->transaction_type = 'credit'; // Money coming IN
            $account_transaction->amount = $new_payment;
            $account_transaction->transaction_date = $payment['pay_date'];
            $account_transaction->title = 'Fee Payment - ' . ($fee->studentEnroll->student->first_name ?? '') . ' ' . ($fee->studentEnroll->student->last_name ?? '');
            $account_transaction->description = 'Fee payment for ' . ($fee->category->title ?? 'Fee');
            $account_transaction->payment_method = $payment['payment_method'];
            $account_transaction->reference_type = 'fees';
            $account_transaction->reference_id = $fee->id;
            $account_transaction->balance_after = $new_balance;
            $account_transaction->created_by = $userId;
            $account_transaction->save();

            $payment_account->current_balance = $new_balance;
            $payment_account->save();
        }

        return [
            'net_amount' => (float) $net_amount,
            'total_paid' => (float) $total_paid,
            'status' => $status,
            'overpaid' => round(max(0, $total_paid - $net_amount), 2),
            'receipt' => $receipt,
        ];
    }
}
