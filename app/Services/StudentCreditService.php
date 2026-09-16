<?php

namespace App\Services;

use App\Models\StudentCredit;
use App\Models\CreditApplication;
use App\Models\Fee;
use App\Models\Student;
use App\Models\FeesCategory;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentCreditService
{
    /**
     * Create a credit from overpayment.
     *
     * @param Fee $fee The fee that was overpaid
     * @param float $overpaymentAmount The amount exceeding the fee balance
     * @param int|null $createdBy User ID who processed the payment
     * @return StudentCredit
     */
    public function createFromOverpayment(Fee $fee, float $overpaymentAmount, ?int $createdBy = null): StudentCredit
    {
        // Resolve student id defensively: prefer the eager/loaded enrollment,
        // fall back to fee.student_id if some legacy seeders set it directly.
        $studentId = optional($fee->studentEnroll)->student_id
            ?? $fee->student_id
            ?? null;

        if (!$studentId) {
            throw new \RuntimeException(
                "Cannot create overpayment credit: fee #{$fee->id} has no resolvable student."
            );
        }

        $credit = StudentCredit::create([
            'student_id' => $studentId,
            'original_amount' => $overpaymentAmount,
            'remaining_amount' => $overpaymentAmount,
            'source_fee_id' => $fee->id,
            'source_type' => StudentCredit::SOURCE_OVERPAYMENT,
            'status' => StudentCredit::STATUS_AVAILABLE,
            'note' => "Overpayment from " . ($fee->category->title ?? 'Fee') . " (Fee ID: {$fee->id})",
            'created_by' => $createdBy ?? Auth::id(),
        ]);

        Log::info("Student credit created from overpayment", [
            'credit_id' => $credit->id,
            'student_id' => $studentId,
            'amount' => $overpaymentAmount,
            'source_fee_id' => $fee->id,
        ]);

        return $credit;
    }

    /**
     * Create a manual credit (admin adjustment).
     *
     * @param int $studentId
     * @param float $amount
     * @param string|null $note
     * @param int|null $createdBy
     * @return StudentCredit
     */
    public function createManualCredit(int $studentId, float $amount, ?string $note = null, ?int $createdBy = null): StudentCredit
    {
        $credit = StudentCredit::create([
            'student_id' => $studentId,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'source_type' => StudentCredit::SOURCE_ADMIN_ADJUSTMENT,
            'status' => StudentCredit::STATUS_AVAILABLE,
            'note' => $note ?? 'Admin adjustment',
            'created_by' => $createdBy ?? Auth::id(),
        ]);

        Log::info("Manual student credit created", [
            'credit_id' => $credit->id,
            'student_id' => $studentId,
            'amount' => $amount,
        ]);

        return $credit;
    }

    /**
     * Get total available credit balance for a student.
     *
     * @param int $studentId
     * @return float
     */
    public function getAvailableBalance(int $studentId): float
    {
        return StudentCredit::where('student_id', $studentId)
            ->available()
            ->sum('remaining_amount');
    }

    /**
     * Get all available credits for a student.
     *
     * @param int $studentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableCredits(int $studentId)
    {
        return StudentCredit::where('student_id', $studentId)
            ->available()
            ->orderBy('created_at', 'asc') // FIFO - oldest first
            ->get();
    }

    /**
     * Apply credits to a fee.
     *
     * @param Fee $fee The fee to apply credits to
     * @param float|null $maxAmount Maximum amount to apply (null = apply as much as possible)
     * @param string $applicationType 'auto' or 'manual'
     * @param int|null $createdBy
     * @return array ['total_applied' => float, 'applications' => CreditApplication[]]
     */
    public function applyCreditsToFee(Fee $fee, ?float $maxAmount = null, string $applicationType = 'auto', ?int $createdBy = null): array
    {
        $studentId = $fee->studentEnroll->student_id;
        $remainingBalance = $fee->remaining_balance;
        
        if ($remainingBalance <= 0) {
            return ['total_applied' => 0, 'applications' => []];
        }

        $availableCredits = $this->getAvailableCredits($studentId);
        
        if ($availableCredits->isEmpty()) {
            return ['total_applied' => 0, 'applications' => []];
        }

        $amountToApply = $maxAmount !== null ? min($maxAmount, $remainingBalance) : $remainingBalance;
        $totalApplied = 0;
        $applications = [];

        // Resolve created_by: must be a valid users (admin) ID, not a student ID
        $resolvedCreatedBy = $createdBy ?? Auth::guard('web')->id();

        DB::beginTransaction();
        
        try {
            foreach ($availableCredits as $credit) {
                if ($amountToApply <= 0) {
                    break;
                }

                $applyFromThisCredit = min($credit->remaining_amount, $amountToApply);
                
                if ($applyFromThisCredit <= 0) {
                    continue;
                }

                // Create credit application record
                $application = CreditApplication::create([
                    'student_credit_id' => $credit->id,
                    'fee_id' => $fee->id,
                    'amount_applied' => $applyFromThisCredit,
                    'application_type' => $applicationType,
                    'note' => $applicationType === 'auto' 
                        ? 'Auto-applied at fee assignment' 
                        : 'Manually applied',
                    'created_by' => $resolvedCreatedBy,
                ]);

                // Update credit remaining amount
                $credit->remaining_amount -= $applyFromThisCredit;
                
                // Update credit status
                if ($credit->remaining_amount <= 0) {
                    $credit->status = StudentCredit::STATUS_FULLY_APPLIED;
                    $credit->remaining_amount = 0;
                } else {
                    $credit->status = StudentCredit::STATUS_PARTIALLY_APPLIED;
                }
                
                $credit->updated_by = $resolvedCreatedBy;
                $credit->save();

                // Update fee paid amount
                $fee->paid_amount = ($fee->paid_amount ?? 0) + $applyFromThisCredit;

                // Update fee status. Use the total_amount accessor so fine /
                // discount are honoured exactly the same way as the manual
                // applyToFee() path further below.
                $totalDue = $fee->total_amount ?? 0;
                if ($totalDue > 0 && $fee->paid_amount >= $totalDue) {
                    $fee->status = 1; // Fully paid
                    $fee->pay_date = now()->format('Y-m-d');
                } elseif ($fee->paid_amount > 0) {
                    $fee->status = 2; // Partially paid
                    $fee->pay_date = $fee->pay_date ?: now()->format('Y-m-d');
                }
                
                $fee->note = ($fee->note ? $fee->note . ' | ' : '') . 
                    "Credit applied: " . number_format($applyFromThisCredit, 2);
                $fee->save();

                // Create transaction record
                $transaction = new Transaction();
                $transaction->transaction_id = Str::random(16);
                $transaction->amount = $applyFromThisCredit;
                $transaction->type = '1'; // Credit
                $transaction->created_by = $resolvedCreatedBy;
                $fee->studentEnroll->student->transactions()->save($transaction);

                $totalApplied += $applyFromThisCredit;
                $amountToApply -= $applyFromThisCredit;
                $applications[] = $application;

                Log::info("Credit applied to fee", [
                    'credit_id' => $credit->id,
                    'fee_id' => $fee->id,
                    'amount_applied' => $applyFromThisCredit,
                    'credit_remaining' => $credit->remaining_amount,
                    'fee_paid' => $fee->paid_amount,
                ]);
            }

            DB::commit();

            return [
                'total_applied' => $totalApplied,
                'applications' => $applications,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to apply credits to fee", [
                'fee_id' => $fee->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Auto-apply credits to a newly assigned fee if eligible.
     * Only applies to tuition-type fees (is_first_installment or is_second_installment).
     *
     * @param Fee $fee
     * @param int|null $createdBy
     * @return array
     */
    public function autoApplyCreditsToNewFee(Fee $fee, ?int $createdBy = null): array
    {
        // Check if fee category is eligible for auto-application
        $category = $fee->category;
        
        if (!$category) {
            Log::debug("Fee has no category, skipping auto-apply", ['fee_id' => $fee->id]);
            return ['total_applied' => 0, 'applications' => [], 'eligible' => false];
        }

        // Only apply to tuition-type fees (first or second installment)
        if (!$category->is_first_installment && !$category->is_second_installment) {
            Log::debug("Fee category not eligible for credit auto-apply", [
                'fee_id' => $fee->id,
                'category' => $category->title,
                'is_first_installment' => $category->is_first_installment,
                'is_second_installment' => $category->is_second_installment,
            ]);
            return ['total_applied' => 0, 'applications' => [], 'eligible' => false];
        }

        // Check if student has available credits
        $studentId = $fee->studentEnroll->student_id;
        $availableBalance = $this->getAvailableBalance($studentId);

        if ($availableBalance <= 0) {
            Log::debug("Student has no available credits", [
                'fee_id' => $fee->id,
                'student_id' => $studentId,
            ]);
            return ['total_applied' => 0, 'applications' => [], 'eligible' => true];
        }

        Log::info("Auto-applying credits to new fee", [
            'fee_id' => $fee->id,
            'student_id' => $studentId,
            'available_credit' => $availableBalance,
            'fee_amount' => $fee->total_amount,
        ]);

        // Apply credits
        $result = $this->applyCreditsToFee($fee, null, CreditApplication::TYPE_AUTO, $createdBy);
        $result['eligible'] = true;

        return $result;
    }

    /**
     * Transfer a paid amount from one fee to another (same student).
     *
     * Modeled as: withdraw `amount` from the source fee, materialise it as a
     * StudentCredit (source_type=transfer), then immediately apply that
     * credit to the target fee. The credit + application chain provides a
     * tamper-evident audit trail and lets either side be reconciled later.
     *
     * Returns ['credit' => StudentCredit, 'application' => CreditApplication,
     *          'source' => Fee, 'target' => Fee].
     *
     * @throws \InvalidArgumentException on validation failure
     * @throws \RuntimeException on data integrity failure
     */
    public function transferBetweenFees(
        Fee $source,
        Fee $target,
        float $amount,
        string $reason,
        ?int $performedBy = null
    ): array {
        // ----- Validation (defensive: also enforced by controller) ---------
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException(__('source_and_target_must_differ'));
        }

        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('amount_must_be_positive'));
        }

        $sourceStudentId = optional($source->studentEnroll)->student_id;
        $targetStudentId = optional($target->studentEnroll)->student_id;

        if (!$sourceStudentId || !$targetStudentId) {
            throw new \RuntimeException(__('cannot_resolve_student_for_transfer'));
        }
        if ($sourceStudentId !== $targetStudentId) {
            throw new \InvalidArgumentException(__('transfer_requires_same_student'));
        }

        $sourcePaid = (float) ($source->paid_amount ?? 0);
        if ($amount > $sourcePaid) {
            throw new \InvalidArgumentException(__('amount_exceeds_source_paid'));
        }

        $targetDue       = (float) ($target->total_amount ?? 0);
        $targetPaid      = (float) ($target->paid_amount ?? 0);
        $targetRemaining = max(0, $targetDue - $targetPaid);

        if ($targetRemaining <= 0) {
            throw new \InvalidArgumentException(__('target_already_fully_paid'));
        }
        if ($amount > $targetRemaining) {
            throw new \InvalidArgumentException(__('amount_exceeds_target_balance'));
        }

        if (in_array((int) $target->status, [3], true)) {
            throw new \InvalidArgumentException(__('target_fee_cancelled'));
        }

        $performedBy = $performedBy ?? Auth::guard('web')->id();
        $stamp       = now()->format('Y-m-d H:i');

        // ----- Atomic execution -------------------------------------------
        return DB::transaction(function () use (
            $source, $target, $amount, $reason, $performedBy, $sourceStudentId,
            $sourcePaid, $targetPaid, $targetDue, $stamp
        ) {
            // 1. Withdraw from source: reduce paid_amount and recompute status.
            $newSourcePaid = round($sourcePaid - $amount, 2);
            $source->paid_amount = $newSourcePaid;

            $sourceDue = (float) ($source->total_amount ?? 0);
            if ($newSourcePaid <= 0) {
                $source->status = 0; // unpaid
                $source->pay_date = null;
            } elseif ($sourceDue > 0 && $newSourcePaid >= $sourceDue) {
                $source->status = 1; // fully paid
            } else {
                $source->status = 2; // partial
            }
            $source->note = ($source->note ? $source->note . ' | ' : '')
                . "Transferred {$amount} to Fee #{$target->id} by user#{$performedBy} on {$stamp} — Reason: {$reason}";
            $source->updated_by = $performedBy;
            $source->save();

            // 2. Materialise the moved money as a transfer-type credit. We
            //    create it at remaining_amount=0 so it never appears in the
            //    "available credits" pool — it's already earmarked for the
            //    target fee in step 3.
            $credit = StudentCredit::create([
                'student_id'       => $sourceStudentId,
                'original_amount'  => $amount,
                'remaining_amount' => 0,
                'source_fee_id'    => $source->id,
                'source_type'      => StudentCredit::SOURCE_TRANSFER,
                'status'           => StudentCredit::STATUS_FULLY_APPLIED,
                'note'             => "Transfer from Fee #{$source->id} to Fee #{$target->id}: {$reason}",
                'created_by'       => $performedBy,
            ]);

            // 3. Record the application against the target fee.
            $application = CreditApplication::create([
                'student_credit_id' => $credit->id,
                'fee_id'            => $target->id,
                'amount_applied'    => $amount,
                'application_type'  => CreditApplication::TYPE_MANUAL,
                'note'              => "Transfer from Fee #{$source->id}: {$reason}",
                'created_by'        => $performedBy,
            ]);

            // 4. Credit the target fee.
            $newTargetPaid = round($targetPaid + $amount, 2);
            $target->paid_amount = $newTargetPaid;

            if ($targetDue > 0 && $newTargetPaid >= $targetDue) {
                $target->status   = 1; // fully paid
                $target->pay_date = $target->pay_date ?: now()->format('Y-m-d');
            } else {
                $target->status   = 2; // partial
                $target->pay_date = $target->pay_date ?: now()->format('Y-m-d');
            }
            $target->note = ($target->note ? $target->note . ' | ' : '')
                . "Received {$amount} from Fee #{$source->id} by user#{$performedBy} on {$stamp} — Reason: {$reason}";
            $target->updated_by = $performedBy;
            $target->save();

            // The source was saved (and posted) before the transfer credit
            // existed, so its posting still counts the money moved out as its
            // own. Now both sides are recorded, post each at its cash received.
            $posting = app(FeeLedgerPosting::class);
            $posting->resync($source->fresh(), $performedBy);
            $posting->resync($target->fresh(), $performedBy);

            // 5. Audit transactions for both legs (debit on source, credit on target).
            $student = optional($source->studentEnroll)->student;
            if ($student) {
                $debit = new Transaction();
                $debit->transaction_id = Str::random(16);
                $debit->amount         = $amount;
                $debit->type           = '0'; // debit / withdraw
                $debit->created_by     = $performedBy;
                $student->transactions()->save($debit);

                $credTx = new Transaction();
                $credTx->transaction_id = Str::random(16);
                $credTx->amount         = $amount;
                $credTx->type           = '1'; // credit / apply
                $credTx->created_by     = $performedBy;
                $student->transactions()->save($credTx);
            }

            Log::info('Fee payment transferred', [
                'source_fee_id'   => $source->id,
                'target_fee_id'   => $target->id,
                'amount'          => $amount,
                'student_id'      => $sourceStudentId,
                'credit_id'       => $credit->id,
                'application_id'  => $application->id,
                'performed_by'    => $performedBy,
            ]);

            return [
                'credit'      => $credit,
                'application' => $application,
                'source'      => $source->fresh(),
                'target'      => $target->fresh(),
            ];
        });
    }

    /**
     * Request a refund for a credit.
     *
     * @param StudentCredit $credit
     * @param string|null   $reason       Optional reason supplied by the requester.
     * @param int|null      $requestedBy
     * @return StudentCredit
     */
    public function requestRefund(StudentCredit $credit, ?string $reason = null, ?int $requestedBy = null): StudentCredit
    {
        if (!$credit->canBeRefunded()) {
            throw new \Exception("This credit cannot be refunded");
        }

        $update = [
            'refund_requested' => true,
            'refund_requested_at' => now(),
            'refund_requested_by' => $requestedBy ?? Auth::id(),
            // A new request supersedes any previous rejection — clear the
            // rejection trail so the lifecycle is unambiguous, but keep the
            // old `refund_note` (which may contain admin context).
            'refund_rejected_at' => null,
            'refund_rejected_by' => null,
            'refund_rejected_reason' => null,
            'updated_by' => $requestedBy ?? Auth::id(),
        ];

        if ($reason !== null && trim($reason) !== '') {
            $update['refund_note'] = trim($reason);
        }

        $credit->update($update);

        Log::info("Refund requested for credit", [
            'credit_id' => $credit->id,
            'amount' => $credit->remaining_amount,
            'requested_by' => $requestedBy ?? Auth::id(),
            'has_reason' => $reason !== null && trim($reason) !== '',
        ]);

        return $credit->fresh();
    }

    /**
     * Approve a refund request.
     *
     * @param StudentCredit $credit
     * @param int|null $approvedBy
     * @return StudentCredit
     */
    public function approveRefund(StudentCredit $credit, ?int $approvedBy = null): StudentCredit
    {
        if (!$credit->refund_requested) {
            throw new \Exception("No refund request to approve");
        }

        $credit->update([
            'refund_approved' => true,
            'refund_approved_at' => now(),
            'refund_approved_by' => $approvedBy ?? Auth::id(),
            'updated_by' => $approvedBy ?? Auth::id(),
        ]);

        Log::info("Refund approved for credit", [
            'credit_id' => $credit->id,
            'amount' => $credit->remaining_amount,
            'approved_by' => $approvedBy ?? Auth::id(),
        ]);

        return $credit->fresh();
    }

    /**
     * Reject a refund request.
     *
     * @param StudentCredit $credit
     * @param string|null $reason
     * @param int|null $rejectedBy
     * @return StudentCredit
     */
    public function rejectRefund(StudentCredit $credit, ?string $reason = null, ?int $rejectedBy = null): StudentCredit
    {
        if (!$credit->refund_requested) {
            throw new \Exception("No active refund request to reject");
        }

        $credit->update([
            // Close the open request.
            'refund_requested' => false,
            'refund_requested_at' => null,
            'refund_requested_by' => null,
            // Persist the rejection on dedicated audit columns.
            'refund_rejected_at' => now(),
            'refund_rejected_by' => $rejectedBy ?? Auth::id(),
            'refund_rejected_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'updated_by' => $rejectedBy ?? Auth::id(),
        ]);

        Log::info("Refund rejected for credit", [
            'credit_id' => $credit->id,
            'reason' => $reason,
            'rejected_by' => $rejectedBy ?? Auth::id(),
        ]);

        return $credit->fresh();
    }

    /**
     * Process a refund (mark as completed).
     *
     * @param StudentCredit $credit
     * @param string $refundMethod
     * @param string|null $reference
     * @param string|null $note
     * @param int|null $processedBy
     * @return StudentCredit
     */
    public function processRefund(
        StudentCredit $credit, 
        string $refundMethod, 
        ?string $reference = null, 
        ?string $note = null,
        ?int $processedBy = null
    ): StudentCredit {
        if (!$credit->refund_approved) {
            throw new \Exception("Refund must be approved before processing");
        }

        DB::beginTransaction();
        
        try {
            $refundAmount = $credit->remaining_amount;
            
            $credit->update([
                'refund_processed_at' => now(),
                'refund_processed_by' => $processedBy ?? Auth::id(),
                'refund_method' => $refundMethod,
                'refund_reference' => $reference,
                'refund_note' => $note,
                'remaining_amount' => 0,
                'status' => StudentCredit::STATUS_REFUNDED,
                'updated_by' => $processedBy ?? Auth::id(),
            ]);

            // Create debit transaction for the refund
            $student = Student::find($credit->student_id);
            if ($student) {
                $transaction = new Transaction();
                $transaction->transaction_id = Str::random(16);
                $transaction->amount = $refundAmount;
                $transaction->type = '2'; // Debit
                $transaction->created_by = $processedBy ?? Auth::id();
                $student->transactions()->save($transaction);
            }

            DB::commit();

            Log::info("Refund processed for credit", [
                'credit_id' => $credit->id,
                'amount' => $refundAmount,
                'method' => $refundMethod,
                'reference' => $reference,
                'processed_by' => $processedBy ?? Auth::id(),
            ]);

            return $credit->fresh();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process refund", [
                'credit_id' => $credit->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get credit summary for a student.
     *
     * @param int $studentId
     * @return array
     */
    public function getCreditSummary(int $studentId): array
    {
        $credits = StudentCredit::where('student_id', $studentId)->get();
        
        return [
            'total_credits_received' => $credits->sum('original_amount'),
            'total_applied' => $credits->sum(function($credit) {
                return $credit->original_amount - $credit->remaining_amount;
            }),
            'total_available' => $credits->where('status', '!=', StudentCredit::STATUS_REFUNDED)
                                        ->sum('remaining_amount'),
            'total_refunded' => $credits->where('status', StudentCredit::STATUS_REFUNDED)
                                       ->sum('original_amount'),
            'pending_refund_requests' => $credits->where('refund_requested', true)
                                                 ->where('refund_approved', false)
                                                 ->count(),
            'credits' => $credits,
        ];
    }

    /**
     * Get credits applied to a specific fee.
     *
     * @param int $feeId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCreditsAppliedToFee(int $feeId)
    {
        return CreditApplication::where('fee_id', $feeId)
            ->with('studentCredit')
            ->get();
    }

    /**
     * Get total credits applied to a fee.
     *
     * @param int $feeId
     * @return float
     */
    public function getTotalCreditsAppliedToFee(int $feeId): float
    {
        return CreditApplication::where('fee_id', $feeId)->sum('amount_applied');
    }

    /**
     * Apply a specific credit to a specific fee with a specified amount.
     *
     * @param StudentCredit $credit
     * @param Fee $fee
     * @param float $amount
     * @param int|null $appliedBy
     * @param string|null $note
     * @return CreditApplication
     */
    public function applyToFee(StudentCredit $credit, Fee $fee, float $amount, ?int $appliedBy = null, ?string $note = null): CreditApplication
    {
        // Validate amount
        if ($amount <= 0) {
            throw new \Exception("Amount must be greater than zero");
        }

        if ($amount > $credit->remaining_amount) {
            throw new \Exception("Amount exceeds available credit balance");
        }

        // Verify student match
        $feeStudentId = $fee->studentEnroll->student_id ?? $fee->student_id;
        if ($credit->student_id != $feeStudentId) {
            throw new \Exception("Credit and fee belong to different students");
        }

        DB::beginTransaction();

        try {
            // Create credit application record
            $application = CreditApplication::create([
                'student_credit_id' => $credit->id,
                'fee_id' => $fee->id,
                'amount_applied' => $amount,
                'application_type' => CreditApplication::TYPE_MANUAL,
                'note' => $note ?? 'Manually applied by admin',
                'created_by' => $appliedBy ?? Auth::id(),
            ]);

            // Update credit remaining amount
            $credit->remaining_amount -= $amount;
            
            // Update credit status
            if ($credit->remaining_amount <= 0) {
                $credit->status = StudentCredit::STATUS_FULLY_APPLIED;
                $credit->remaining_amount = 0;
            } else {
                $credit->status = StudentCredit::STATUS_PARTIALLY_APPLIED;
            }
            
            $credit->updated_by = $appliedBy ?? Auth::id();
            $credit->save();

            // Update fee paid amount
            $fee->paid_amount = ($fee->paid_amount ?? 0) + $amount;
            
            // Update fee status
            $totalDue = $fee->net_amount + ($fee->fine ?? 0);
            if ($fee->paid_amount >= $totalDue) {
                $fee->status = 1; // Fully paid
                $fee->pay_date = now()->format('Y-m-d');
            } elseif ($fee->paid_amount > 0) {
                $fee->status = 2; // Partially paid
                if (!$fee->pay_date) {
                    $fee->pay_date = now()->format('Y-m-d');
                }
            }
            
            $fee->note = ($fee->note ? $fee->note . ' | ' : '') . 
                "Credit applied: " . number_format($amount, 2);
            $fee->save();

            // Create transaction record
            $transaction = new Transaction();
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $amount;
            $transaction->type = '1'; // Credit
            $transaction->note = 'Applied from student credit #' . $credit->id;
            $transaction->created_by = $appliedBy ?? Auth::id();
            
            $student = $fee->studentEnroll->student ?? Student::find($feeStudentId);
            $student->transactions()->save($transaction);

            DB::commit();

            Log::info("Credit manually applied to fee", [
                'credit_id' => $credit->id,
                'fee_id' => $fee->id,
                'amount_applied' => $amount,
                'credit_remaining' => $credit->remaining_amount,
                'fee_paid' => $fee->paid_amount,
            ]);

            return $application;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to apply credit to fee", [
                'credit_id' => $credit->id,
                'fee_id' => $fee->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
