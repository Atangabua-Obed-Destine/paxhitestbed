<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class InstallmentPaymentReceipt extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'installment_id',
        'student_id',
        'amount',
        'payment_date',
        'payment_method',
        'receipt_file',
        'note',
        'status', // pending, approved, rejected
        'verified_by',
        'verified_at',
        'verification_note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'payment_date' => 'date',
        'verified_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the installment that owns the receipt.
     */
    public function installment()
    {
        return $this->belongsTo(PaymentPlanInstallment::class, 'installment_id');
    }

    /**
     * Get the student that submitted the receipt.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Get the admin who verified the receipt.
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the status badge for display.
     *
     * @return string
     */
    public function getStatusBadgeAttribute()
    {
        switch ($this->status) {
            case 'pending':
                return '<span class="badge badge-warning">' . __('status_pending') . '</span>';
            case 'approved':
                return '<span class="badge badge-success">' . __('status_approved') . '</span>';
            case 'rejected':
                return '<span class="badge badge-danger">' . __('status_rejected') . '</span>';
            default:
                return '<span class="badge badge-secondary">' . ucfirst($this->status) . '</span>';
        }
    }

    /**
     * Scope a query to only include pending receipts.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved receipts.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
    
    /**
     * Custom audit description for better readability
     */
    public function getAuditDescription($event)
    {
        // Load student relationship if not loaded
        if (!$this->relationLoaded('student')) {
            try {
                $this->load('student');
            } catch (\Exception $e) {
                // Relationship loading failed
            }
        }
        
        $amount = $this->amount ?? 0;
        $status = $this->status ?? 'pending';
        
        // Get student name
        $studentName = 'Unknown Student';
        if ($this->student) {
            $studentName = $this->student->first_name . ' ' . $this->student->last_name;
        } elseif ($this->student_id) {
            // Fallback: try direct query
            $student = \App\Models\Student::find($this->student_id);
            if ($student) {
                $studentName = $student->first_name . ' ' . $student->last_name;
            } else {
                $studentName = 'Student #' . $this->student_id;
            }
        }
        
        return "Installment receipt {$event} for {$studentName}: Amount " . number_format($amount, 2) . ", Status: {$status}";
    }
}
