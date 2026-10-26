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
        'paid_via',
        'gateway_trans_id',
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

        $badge = $badges[$this->status] ?? '';

        // An approved gateway payment was approved by the gateway, not by
        // anybody here. Saying so on the badge keeps the verification queue and
        // the statistics readable now that two kinds of row sit in them.
        if ($badge !== '' && $this->isGatewayPaid()) {
            $badge .= ' <span class="badge badge-info">Online</span>';
        }

        return $badge;
    }

    /** Was this settled by the payment gateway rather than by an admin? */
    public function isGatewayPaid(): bool
    {
        return $this->paid_via === 'fapshi';
    }

    /**
     * The Fapshi transaction that settled this, if one did.
     *
     * No foreign key in either direction — see the create migration for why —
     * so this is matched on the id the payment row records.
     */
    public function fapshiTransaction()
    {
        return $this->belongsTo(FapshiTransaction::class, 'gateway_trans_id', 'trans_id');
    }
}
