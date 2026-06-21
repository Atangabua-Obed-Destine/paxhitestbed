<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FormA3Record extends Model
{
    protected $fillable = [
        'student_id',
        'student_enroll_id',
        'session_id',
        'semester_id',
        'subjects_snapshot',
        'total_credits',
        'hnd_coordinator_name',
        'dir_acad_name',
        'verification_code',
    ];

    protected $casts = [
        'subjects_snapshot' => 'array',
    ];

    /**
     * Boot method to auto-generate verification code on creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($record) {
            if (empty($record->verification_code)) {
                $record->verification_code = 'FA3-' . strtoupper(Str::random(16));
            }
        });
    }

    /**
     * Get the public verification URL for this Form A3
     */
    public function getVerificationUrlAttribute()
    {
        return url('verify-form-a3/' . $this->verification_code);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}
