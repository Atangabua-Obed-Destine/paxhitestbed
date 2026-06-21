<?php

namespace App\Models;

use App\Models\Fee;
use App\Models\Semester;
use App\Models\StudentEnroll;
use App\Services\Resit\ResitFeeService;
use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResitRequest extends Model
{
    use Auditable;

    public const STATE_REQUESTED = 'requested';
    public const STATE_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATE_FINANCE_REVIEW = 'finance_review';
    public const STATE_APPROVED = 'approved';
    public const STATE_SCHEDULED = 'scheduled';
    public const STATE_REJECTED = 'rejected';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_DECLINED = 'declined'; // Student chose not to resit

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_WAIVED = 'waived';
    public const PAYMENT_CANCELLED = 'cancelled';

    /**
     * Fetch the configured baseline resit fee.
     */
    public static function defaultFee(): float
    {
        return (float) config('resit.default_fee', 0.0);
    }

    protected $fillable = [
        'student_enroll_id',
        'subject_id',
        'session_id',
        'resit_session_id',
    'resit_semester_id',
    'resit_enroll_id',
        'fee_amount',
        'payment_status',
        'payment_id',
        'fee_id',
        'workflow_state',
        'approved_by',
        'approved_at',
        'state_changed_at',
        'state_changed_by',
        'notes',
    ];

    protected $casts = [
        'fee_amount' => 'float',
        'approved_at' => 'datetime',
        'state_changed_at' => 'datetime',
    ];

    public function studentEnroll(): BelongsTo
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class, 'fee_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function resitSession(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'resit_session_id');
    }

    public function resitSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'resit_semester_id');
    }

    public function resitEnroll(): BelongsTo
    {
        return $this->belongsTo(StudentEnroll::class, 'resit_enroll_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function stateChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'state_changed_by');
    }

    public function workflowLogs()
    {
        return $this->hasMany(ResitRequestWorkflowLog::class, 'resit_request_id');
    }

    public function paymentSettled(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_PAID, self::PAYMENT_WAIVED], true) || $this->fee_amount <= 0;
    }

    protected static function booted(): void
    {
        static::created(function (self $resitRequest): void {
            // Do NOT assign a fee when the student declines to resit
            if ($resitRequest->workflow_state === self::STATE_DECLINED) {
                return;
            }
            app(ResitFeeService::class)->ensureFee($resitRequest);
        });
    }
}
