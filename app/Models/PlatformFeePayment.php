<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PlatformFeePayment extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_enroll_id',
        'session_id',
        'fee_amount',
        'paid_amount',
        'receipt_path',
        'student_note',
        'status',
        'admin_note',
        'verified_by',
        'verified_at',
        'payment_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'fee_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'verified_at' => 'datetime',
        'payment_date' => 'datetime',
    ];

    /**
     * Get the student enrollment that owns the payment.
     */
    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    /**
     * Get the session for the payment.
     */
    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the admin who verified the payment.
     */
    public function verifiedBy()
    {
        return $this->belongsTo(\App\User::class, 'verified_by');
    }

    /**
     * Get the student through enrollment.
     */
    public function student()
    {
        return $this->hasOneThrough(Student::class, StudentEnroll::class, 'id', 'id', 'student_enroll_id', 'student_id');
    }

    /**
     * Get status badge HTML.
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">Pending</span>',
            'approved' => '<span class="badge badge-success">Approved</span>',
            'rejected' => '<span class="badge badge-danger">Rejected</span>',
        ];
        
        return $badges[$this->status] ?? '';
    }
}
