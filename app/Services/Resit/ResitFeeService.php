<?php

namespace App\Services\Resit;

use App\Models\Fee;
use App\Models\FeesCategory;
use App\Models\FeesMaster;
use App\Models\ResitRequest;
use App\Services\Resit\ResitRequestWorkflowService;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ResitFeeService
{
    /**
     * Ensure that a fee record exists for the provided resit request.
     */
    public function ensureFee(ResitRequest $resitRequest): Fee
    {
        if ($resitRequest->fee) {
            $this->syncFromFee($resitRequest->fee, $resitRequest);

            return $resitRequest->fee;
        }

        $category = $this->resolveCategory();
        $feeAmount = $this->determineFeeAmount($resitRequest, $category);
        $assignDate = Carbon::now();
        $dueDate = (clone $assignDate)->addDays((int) config('resit.fee_due_days', 7));
        $actorId = $this->determineActorId();

        $fee = Fee::create([
            'student_enroll_id' => $resitRequest->student_enroll_id,
            'category_id' => $category->id,
            'fee_amount' => $feeAmount,
            'fine_amount' => 0,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'assign_date' => $assignDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'status' => 0,
            'note' => $this->buildNote($resitRequest),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $resitRequest->fee_id = $fee->id;
        $resitRequest->fee_amount = $feeAmount;
        $resitRequest->payment_status = $resitRequest->payment_status ?: ResitRequest::PAYMENT_PENDING;

        if (empty($resitRequest->workflow_state) || $resitRequest->workflow_state === ResitRequest::STATE_REQUESTED) {
            $resitRequest->workflow_state = ResitRequest::STATE_AWAITING_PAYMENT;
            $resitRequest->state_changed_at = Carbon::now();
            // Only set state_changed_by if actor is from 'web' guard (not student)
            if (Auth::guard('web')->check()) {
                $resitRequest->state_changed_by = $actorId;
            }
        }

        $resitRequest->save();

        $this->autoApproveIfSettled($resitRequest);

        return $fee;
    }

    /**
     * Keep the resit request payment metadata aligned with the linked fee.
     */
    public function syncFromFee(Fee $fee, ?ResitRequest $resitRequest = null): void
    {
        $resitRequest = $resitRequest ?? $fee->resitRequest;

        if (!$resitRequest) {
            return;
        }

        $resitRequest->fee_amount = $fee->fee_amount;
        $resitRequest->payment_status = $this->mapFeeStatus($fee);

        $resitRequest->save();

        $this->autoApproveIfSettled($resitRequest);
    }

    protected function autoApproveIfSettled(ResitRequest $resitRequest): void
    {
        if (!$resitRequest->paymentSettled()) {
            return;
        }

        // Only auto-approve requests in early states (not rejected, cancelled, declined, or already processed)
        if (!in_array($resitRequest->workflow_state, [
            ResitRequest::STATE_REQUESTED,
            ResitRequest::STATE_AWAITING_PAYMENT,
        ], true)) {
            return;
        }

        app(ResitRequestWorkflowService::class)->transition($resitRequest, ResitRequest::STATE_APPROVED, [
            'auto' => true,
            'actor_id' => $this->determineActorId(),
        ]);
    }

    protected function resolveCategory(): FeesCategory
    {
        // First try to find a category with is_resit = 1
        $category = FeesCategory::where('is_resit', 1)
            ->where('status', 1)
            ->first();
        
        if ($category) {
            return $category;
        }
        
        // Fallback to slug-based lookup (for backward compatibility)
        $slug = (string) config('resit.fee_category_slug', 'resit-fee');
        $title = (string) config('resit.fee_category_name', 'Resit Fee');

        return FeesCategory::firstOrCreate(
            ['slug' => $slug],
            [
                'title' => $title,
                'description' => 'Automatically generated fee category for course resits.',
                'status' => 1,
                'is_resit' => 1,
            ]
        );
    }

    /**
     * Determine the fee amount for a resit request
     */
    protected function determineFeeAmount(ResitRequest $resitRequest, FeesCategory $category): float
    {
        // If fee amount is already set in the request, use it
        if ($resitRequest->fee_amount > 0) {
            return (float) $resitRequest->fee_amount;
        }
        
        // Try to find a fees master record for this enrollment
        $enrollment = $resitRequest->studentEnroll;
        if ($enrollment) {
            $feesMaster = FeesMaster::where('category_id', $category->id)
                ->where('status', 1)
                ->where(function($query) use ($enrollment) {
                    $query->where('program_id', $enrollment->program_id)
                        ->orWhereNull('program_id');
                })
                ->where(function($query) use ($enrollment) {
                    $query->where('session_id', $enrollment->session_id)
                        ->orWhereNull('session_id');
                })
                ->where(function($query) use ($enrollment) {
                    $query->where('semester_id', $enrollment->semester_id)
                        ->orWhereNull('semester_id');
                })
                ->orderByRaw('program_id IS NULL, session_id IS NULL, semester_id IS NULL')
                ->first();
            
            if ($feesMaster && $feesMaster->amount > 0) {
                return (float) $feesMaster->amount;
            }
        }
        
        // Fallback to default fee from config
        return (float) ResitRequest::defaultFee();
    }

    protected function mapFeeStatus(Fee $fee): string
    {
        return match ((int) $fee->status) {
            1 => ResitRequest::PAYMENT_PAID,
            3 => ResitRequest::PAYMENT_CANCELLED,
            default => ResitRequest::PAYMENT_PENDING,
        };
    }

    protected function buildNote(ResitRequest $resitRequest): string
    {
        $subject = $resitRequest->subject;
        $subjectLabel = null;

        if ($subject) {
            $code = $subject->code ?: null;
            $name = $subject->name ?: null;
            $subjectLabel = trim(($code ? $code . ' ' : '') . ($name ?? ''));
        }

        $label = $subjectLabel ?: 'Subject #' . $resitRequest->subject_id;

        return sprintf('Resit fee for request #%d (%s)', $resitRequest->id, $label);
    }

    protected function determineActorId(): ?int
    {
        // First try to get the admin/staff user ID
        $actorId = Auth::guard('web')->id();
        
        if ($actorId) {
            return $actorId;
        }
        
        // If student is logged in, return system actor instead
        // (students aren't in users table and can't be used for foreign keys)
        if (Auth::guard('student')->check()) {
            return $this->resolveSystemActorId();
        }

        return $this->resolveSystemActorId();
    }

    protected function resolveSystemActorId(): ?int
    {
        static $cachedId;

        if ($cachedId !== null) {
            return $cachedId;
        }

        $cachedId = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['Super Admin', 'Admin']);
        })
            ->orderBy('id')
            ->value('id');

        if ($cachedId === null) {
            $cachedId = User::orderBy('id')->value('id');
        }

        return $cachedId;
    }
}
