<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Traits\Auditable;
use Carbon\Carbon;

class PaymentPlanInstallment extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_plan_id',
        'installment_number',
        'amount',
        'due_date',
        'paid_amount',
        'late_fee',
        'status',
        'grace_period_ends',
        'paid_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'due_date' => 'date',
        'grace_period_ends' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the payment plan this installment belongs to.
     */
    public function paymentPlan()
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    /**
     * Get all payments for this installment.
     */
    public function payments()
    {
        return $this->hasMany(PaymentPlanPayment::class, 'installment_id');
    }

    /**
     * Get all payment receipts submitted by students for this installment.
     */
    public function paymentReceipts()
    {
        return $this->hasMany(InstallmentPaymentReceipt::class, 'installment_id');
    }

    /**
     * Get pending payment receipts for this installment.
     */
    public function pendingReceipts()
    {
        return $this->hasMany(InstallmentPaymentReceipt::class, 'installment_id')->where('status', 'pending');
    }

    /**
     * Get the most recent pending receipt for this installment.
     */
    public function getPendingReceiptAttribute()
    {
        return $this->paymentReceipts()->where('status', 'pending')->latest()->first();
    }

    /**
     * Get pending multi-payment distributions for this installment.
     */
    public function pendingMultiPaymentDistributions()
    {
        return $this->hasMany(\App\Models\MultiPaymentDistribution::class, 'installment_id')
            ->whereHas('multiPayment', function($query) {
                $query->where('status', 'pending');
            })
            ->where('amount_applied', '>', 0);
    }

    /**
     * Check if installment has pending multi-payment.
     */
    public function hasPendingMultiPayment()
    {
        return $this->pendingMultiPaymentDistributions()->exists();
    }

    /**
     * Get pending multi-payment total amount for this installment.
     */
    public function getPendingMultiPaymentAmount()
    {
        return $this->pendingMultiPaymentDistributions()->sum('amount_applied');
    }

    /**
     * Get remaining balance for this installment.
     */
    public function getRemainingBalanceAttribute()
    {
        $total = $this->amount + ($this->late_fee ?? 0);
        return max(0, $total - ($this->paid_amount ?? 0));
    }

    /**
     * Check if installment is overdue.
     */
    public function getIsOverdueAttribute()
    {
        if ($this->status === 'paid') {
            return false;
        }

        $checkDate = $this->grace_period_ends ?? $this->due_date;
        return Carbon::parse($checkDate)->isPast();
    }

    /**
     * Check if grace period is active.
     */
    public function getIsGracePeriodActiveAttribute()
    {
        if (!$this->grace_period_ends || $this->status === 'paid') {
            return false;
        }

        return Carbon::now()->between($this->due_date, $this->grace_period_ends);
    }

    /**
     * Check if installment is pending.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if installment is partially paid.
     */
    public function isPartial()
    {
        return $this->status === 'partial';
    }

    /**
     * Check if installment is fully paid.
     */
    public function isPaid()
    {
        return $this->status === 'paid';
    }

    /**
     * Check if installment is overdue.
     */
    public function isOverdue()
    {
        return $this->status === 'overdue';
    }

    /**
     * Record a payment for this installment.
     *
     * Every route to instalment money ends here — the admin's Record Payment
     * form and an admin approving a receipt the student uploaded — so this is
     * where the money is checked and where all of its consequences happen. Both
     * callers used to do part of the work themselves and had drifted apart: one
     * credited the payment account and not the student's statement, the other
     * the statement and not the account.
     *
     * @throws \DomainException when the payment is not one this instalment can take
     */
    public function recordPayment($amount, $paymentData = [])
    {
        $amount = round((float) $amount, 2);
        $plan = $this->paymentPlan;

        $this->refuseImpossiblePayment($amount, $plan);

        $payDate = $paymentData['payment_date'] ?? now();

        // The payment row is written before the fee is touched, and that order
        // matters: FeeLedgerPosting takes plan money off what the fee posts, so
        // the payment has to be on record by the time the fee's own posting is
        // worked out. The other way round, the fee would post this instalment's
        // cash and the instalment would post it again.
        $payment = $this->payments()->create(array_merge($paymentData, [
            'amount' => $amount,
            'payment_date' => $payDate,
            'status' => 'active',
        ]));

        $this->applyPaymentToSelf($amount);
        $this->applyPaymentToFee($plan, $amount, $payDate, $paymentData);

        $this->creditPaymentAccount($plan, $payment);
        $this->recordOnStudentStatement($plan, $amount);

        // Check if payment plan is now complete
        $this->checkPaymentPlanCompletion();

        return $payment;
    }

    /**
     * Money this instalment cannot take is refused outright, with the figure
     * that makes sense of it, and nothing at all is written.
     */
    protected function refuseImpossiblePayment(float $amount, ?PaymentPlan $plan): void
    {
        if ($amount <= 0) {
            throw new \DomainException(__('A payment has to be more than zero.'));
        }

        if (!$plan) {
            throw new \DomainException(__('This instalment no longer belongs to a payment plan.'));
        }

        if (!$plan->isActive()) {
            throw new \DomainException(__('This payment plan is :status, so it cannot take any more money.', [
                'status' => $plan->status,
            ]));
        }

        $balance = round((float) $this->remaining_balance, 2);

        if ($amount > $balance + 0.009) {
            throw new \DomainException(__('Instalment :number has :balance left to pay, and this payment is :amount. Record :balance or less.', [
                'number' => $this->installment_number,
                'balance' => number_format($balance, 2),
                'amount' => number_format($amount, 2),
            ]));
        }
    }

    protected function applyPaymentToSelf(float $amount): void
    {
        $newPaidAmount = round((float) ($this->paid_amount ?? 0) + $amount, 2);
        $this->paid_amount = $newPaidAmount;

        $totalDue = (float) $this->amount + (float) ($this->late_fee ?? 0);

        if ($newPaidAmount >= $totalDue - 0.009) {
            $this->status = 'paid';
            $this->paid_at = now();
        } else {
            $this->status = 'partial';
        }

        $this->save();
    }

    /**
     * The fee the plan was built from carries the money too, with the date and
     * method behind it — without a pay date the ledger has nowhere to file the
     * cash and the daybook cannot place it.
     */
    protected function applyPaymentToFee(?PaymentPlan $plan, float $amount, $payDate, array $paymentData): void
    {
        if (!$plan || !$plan->fee) {
            return;
        }

        $fee = $plan->fee;
        $newFeePaid = round((float) ($fee->paid_amount ?? 0) + $amount, 2);

        $update = [
            'paid_amount' => $newFeePaid,
            'status' => ($newFeePaid >= (float) $fee->total_amount - 0.009) ? 1 : 2,
            'pay_date' => $payDate instanceof \DateTimeInterface
                ? $payDate->format('Y-m-d')
                : date('Y-m-d', strtotime((string) $payDate)),
        ];

        if (!empty($paymentData['payment_method'])) {
            $update['payment_method'] = $paymentData['payment_method'];
        }

        if (!empty($paymentData['payment_account_id']) && !$fee->payment_account_id) {
            $update['payment_account_id'] = $paymentData['payment_account_id'];
        }

        $fee->update($update);
    }

    /**
     * Money into a payment account raises its balance and shows in the cash
     * book, the same shape as a direct fee payment. A payment recorded without
     * an account stays visible under Unlinked Transactions, which can attach one
     * later.
     */
    protected function creditPaymentAccount(?PaymentPlan $plan, PaymentPlanPayment $payment): void
    {
        if (empty($payment->payment_account_id)) {
            return;
        }

        $account = PaymentAccount::find($payment->payment_account_id);

        if (!$account) {
            return;
        }

        $fee = $plan->fee ?? null;
        $student = $plan->student ?? null;
        $studentName = $student ? trim($student->first_name . ' ' . $student->last_name) : 'Student';
        $newBalance = round((float) $account->current_balance + (float) $payment->amount, 2);

        $transaction = new PaymentAccountTransaction;
        $transaction->payment_account_id = $account->id;
        $transaction->transaction_type = 'credit'; // Money coming IN
        $transaction->amount = $payment->amount;
        $transaction->transaction_date = $payment->payment_date;
        $transaction->title = 'Installment Payment #' . $this->installment_number . ' - ' . $studentName;
        $transaction->description = 'Payment plan instalment for ' . ($fee->category->title ?? $fee->category->name ?? 'Fee');
        $transaction->payment_method = $payment->payment_method;
        $transaction->reference_type = 'payment_plan_payments';
        $transaction->reference_id = $payment->id;
        $transaction->balance_after = $newBalance;
        $transaction->created_by = Auth::guard('web')->id();
        $transaction->save();

        $account->current_balance = $newBalance;
        $account->save();
    }

    /**
     * The student's statement lists what they have paid. The row used to be
     * hung off the payment itself, which the statement never reads, so plan
     * money was missing from it entirely.
     */
    protected function recordOnStudentStatement(?PaymentPlan $plan, float $amount): void
    {
        $student = $plan->student ?? null;

        if (!$student) {
            return;
        }

        $transaction = new Transaction;
        $transaction->transaction_id = Str::random(16);
        $transaction->amount = $amount;
        $transaction->type = '1';
        $transaction->created_by = Auth::guard('web')->id();
        $student->transactions()->save($transaction);
    }

    /**
     * Apply late fee to this installment.
     *
     * The charge lands on the fee as well, as a fine. The instalment's own total
     * used to grow while the fee's stayed as assigned, so a student who paid
     * every instalment including its late fees ended up with a fee that read as
     * overpaid — and an overpaid fee is exactly what the fees report and the
     * credit audit are there to flag.
     */
    public function applyLateFee()
    {
        // Late fees are switched off (config/payment_plan.php). This is the one
        // place money is charged for paying late, so refusing here means no
        // route to it exists — not the command, not a stored percentage on an
        // older plan, not a future caller.
        if (!config('payment_plan.late_fees_enabled', false)) {
            return false;
        }

        if ($this->status === 'paid' || $this->late_fee > 0) {
            return false;
        }

        $plan = $this->paymentPlan;
        if (!$plan || $plan->late_fee_percentage <= 0) {
            return false;
        }

        // Calculate late fee
        $lateFee = round(((float) $this->amount * (float) $plan->late_fee_percentage) / 100, 2);

        if ($lateFee <= 0) {
            return false;
        }

        $this->late_fee = $lateFee;
        $this->save();

        if ($plan->fee) {
            $plan->fee->update([
                'fine_amount' => round((float) ($plan->fee->fine_amount ?? 0) + $lateFee, 2),
            ]);
        }

        return true;
    }

    /**
     * Take a late fee back off, on the instalment and on the fee with it.
     */
    public function removeLateFee()
    {
        $lateFee = (float) ($this->late_fee ?? 0);

        if ($lateFee <= 0) {
            return false;
        }

        $this->late_fee = 0;
        $this->save();

        $plan = $this->paymentPlan;

        if ($plan && $plan->fee) {
            $plan->fee->update([
                'fine_amount' => max(0, round((float) ($plan->fee->fine_amount ?? 0) - $lateFee, 2)),
            ]);
        }

        return true;
    }

    /**
     * Update status based on due date and grace period.
     */
    public function updateStatus()
    {
        if ($this->status === 'paid') {
            return;
        }

        if ($this->is_overdue) {
            $this->status = 'overdue';
            $this->save();

            // Marking an instalment late does not charge for it. Charging money
            // is a decision the school makes — applyLateFee() is called
            // deliberately, never as a side effect of noticing a date has passed.
        }
    }

    /**
     * Check if entire payment plan is completed.
     */
    protected function checkPaymentPlanCompletion()
    {
        $plan = $this->paymentPlan;
        
        if (!$plan) {
            return;
        }

        // Check if all installments are paid
        $allPaid = $plan->installments()
            ->where('status', '!=', 'paid')
            ->count() === 0;

        if ($allPaid && $plan->isActive()) {
            $plan->markAsCompleted();
            
            // Update fee status to fully paid and clear payment plan link
            if ($plan->fee) {
                $plan->fee->update([
                    'status' => 1, // 1 = Fully paid
                    'payment_plan_id' => null, // Clear payment plan link
                ]);
            }
        }
    }

    /**
     * Get status badge HTML.
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">Pending</span>',
            'partial' => '<span class="badge badge-info">Partial</span>',
            'paid' => '<span class="badge badge-success">Paid</span>',
            'overdue' => '<span class="badge badge-danger">Overdue</span>',
        ];

        return $badges[$this->status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }
}
