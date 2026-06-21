<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class MultiPayment extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'student_id',
        'total_amount',
        'amount_paid',
        'payment_method',
        'transaction_id',
        'receipt_path',
        'status',
        'admin_note',
        'payment_date',
        'verified_by',
        'verified_at'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'verified_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function distributions()
    {
        return $this->hasMany(MultiPaymentDistribution::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Helper methods
    public function getTotalDistributedAmount()
    {
        return $this->distributions->sum('amount_applied');
    }

    public function getStatusBadgeClass()
    {
        return match($this->status) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default => 'badge-warning',
        };
    }

    public function getStatusLabel()
    {
        return match($this->status) {
            'approved' => __('status_approved'),
            'rejected' => __('status_rejected'),
            default => __('status_pending'),
        };
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
        
        $totalAmount = $this->total_amount ?? 0;
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
        
        return "Multi-payment {$event} for {$studentName}: Amount " . number_format($totalAmount, 2) . ", Status: {$status}";
    }
}
