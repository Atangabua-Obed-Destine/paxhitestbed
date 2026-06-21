<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PaymentReceipt extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fee_id',
        'student_id',
        'receipt_file',
        'payment_reference',
        'payment_date',
        'amount',
        'payment_method',
        'student_note',
        'verification_status',
        'verification_note',
        'verified_by',
        'verified_at',
        'payment_account_id',
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
     * Get the fee that this receipt is for.
     */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * Get the student who uploaded the receipt.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user (admin) who verified the receipt.
     */
    public function verifier()
    {
        return $this->belongsTo(\App\User::class, 'verified_by');
    }

    /**
     * Check if receipt is pending verification.
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->verification_status === 'pending';
    }

    /**
     * Check if receipt is approved.
     *
     * @return bool
     */
    public function isApproved()
    {
        return $this->verification_status === 'approved';
    }

    /**
     * Check if receipt is rejected.
     *
     * @return bool
     */
    public function isRejected()
    {
        return $this->verification_status === 'rejected';
    }

    /**
     * Get the status badge HTML.
     *
     * @return string
     */
    public function getStatusBadgeAttribute()
    {
        switch ($this->verification_status) {
            case 'pending':
                return '<span class="badge badge-warning">Pending</span>';
            case 'approved':
                return '<span class="badge badge-success">Approved</span>';
            case 'rejected':
                return '<span class="badge badge-danger">Rejected</span>';
            default:
                return '<span class="badge badge-secondary">Unknown</span>';
        }
    }

    /**
     * Get the payment method name.
     *
     * @return string
     */
    public function getPaymentMethodNameAttribute()
    {
        $methods = [
            1 => __('payment_method_card'),
            2 => __('payment_method_cash'),
            3 => __('payment_method_cheque'),
            4 => __('payment_method_bank'),
            5 => __('payment_method_e_wallet'),
            6 => __('payment_method_manual'),
        ];

        return $methods[$this->payment_method] ?? 'Unknown';
    }

    /**
     * Get custom audit description.
     *
     * @param string $event
     * @return string
     */
    public function getAuditDescription($event)
    {
        // Get student name from first_name and last_name
        $studentName = 'Unknown Student';
        
        try {
            if ($this->student_id) {
                // Force reload the student from database
                $student = \App\Models\Student::find($this->student_id);
                
                if ($student) {
                    $firstName = $student->first_name ?? '';
                    $lastName = $student->last_name ?? '';
                    $fullName = trim($firstName . ' ' . $lastName);
                    
                    if (!empty($fullName)) {
                        $studentName = $fullName;
                    }
                }
            }
        } catch (\Exception $e) {
            // If there's any error, stick with 'Unknown Student'
        }

        $amount = $this->amount;
        $reference = $this->payment_reference ?? 'No Ref';

        if ($event === 'created') {
            return "Payment receipt uploaded by {$studentName} (Amount: {$amount}, Ref: {$reference})";
        } elseif ($event === 'updated') {
            if ($this->verification_status === 'approved') {
                return "Payment receipt approved for {$studentName} (Amount: {$amount})";
            } elseif ($this->verification_status === 'rejected') {
                return "Payment receipt rejected for {$studentName} (Amount: {$amount})";
            }
            return "Payment receipt updated for {$studentName}";
        } elseif ($event === 'deleted') {
            return "Payment receipt deleted for {$studentName} (Amount: {$amount})";
        }

        return "Payment receipt was {$event}";
    }

    /**
     * Scope a query to only include pending receipts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    /**
     * Scope a query to only include approved receipts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('verification_status', 'approved');
    }

    /**
     * Scope a query to only include rejected receipts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRejected($query)
    {
        return $query->where('verification_status', 'rejected');
    }
}
