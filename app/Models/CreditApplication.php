<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class CreditApplication extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_credit_id',
        'fee_id',
        'amount_applied',
        'application_type',
        'note',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount_applied' => 'decimal:2',
    ];

    /**
     * Application type constants
     */
    const TYPE_AUTO = 'auto';
    const TYPE_MANUAL = 'manual';

    /**
     * Get the student credit this application belongs to.
     */
    public function studentCredit()
    {
        return $this->belongsTo(StudentCredit::class, 'student_credit_id');
    }

    /**
     * Get the fee this credit was applied to.
     */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * Get the user who created this application.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the student through the credit.
     */
    public function getStudentAttribute()
    {
        return $this->studentCredit?->student;
    }

    /**
     * Get application type label.
     */
    public function getApplicationTypeLabelAttribute()
    {
        return $this->application_type === self::TYPE_AUTO ? 'Automatic' : 'Manual';
    }

    /**
     * Custom audit description.
     */
    public function getAuditDescription($event)
    {
        $studentName = 'Unknown Student';
        if ($this->studentCredit && $this->studentCredit->student) {
            $student = $this->studentCredit->student;
            $studentName = trim($student->first_name . ' ' . $student->last_name);
        }

        $amount = number_format($this->amount_applied, 2);
        $feeCategory = $this->fee?->category?->title ?? 'Unknown Fee';

        if ($event === 'created') {
            return "Credit of {$amount} applied to {$feeCategory} for {$studentName} ({$this->application_type_label})";
        } elseif ($event === 'deleted') {
            return "Credit application of {$amount} removed from {$feeCategory} for {$studentName}";
        }

        return "Credit application was {$event}";
    }
}
