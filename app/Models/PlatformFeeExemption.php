<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PlatformFeeExemption extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'exemption_type',
        'student_enroll_id',
        'session_id',
        'reason',
        'created_by',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the student enrollment (if exemption_type = student).
     */
    public function studentEnroll()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    /**
     * Get the session (if exemption_type = session).
     */
    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the user who created the exemption.
     */
    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Scope to get active exemptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get student exemptions.
     */
    public function scopeForStudent($query)
    {
        return $query->where('exemption_type', 'student');
    }

    /**
     * Scope to get session exemptions.
     */
    public function scopeForSession($query)
    {
        return $query->where('exemption_type', 'session');
    }
}
