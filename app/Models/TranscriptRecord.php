<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A transcript as it was issued.
 *
 * The snapshot is the whole point: verification has to answer "is this the
 * document we issued", not "does this student exist". Reading the marks live
 * would let an altered PDF verify as genuine.
 *
 * Follows FormA3Record, which does the same for that document.
 */
class TranscriptRecord extends Model
{
    protected $fillable = [
        'student_id',
        'student_enroll_id',
        'program_id',
        'verification_code',
        'courses_snapshot',
        'matricule',
        'student_name',
        'programme_name',
        'cumulative_gpa',
        'total_credits',
        'credits_earned',
        'total_courses',
        'standing',
        'issued_at',
        'issued_by',
    ];

    protected $casts = [
        'courses_snapshot' => 'array',
        'issued_at' => 'datetime',
        'cumulative_gpa' => 'decimal:2',
        'total_credits' => 'decimal:1',
        'credits_earned' => 'decimal:1',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($record) {
            if (empty($record->verification_code)) {
                // Random rather than sequential: a predictable code would let
                // anyone enumerate every transcript the institution has issued.
                $record->verification_code = 'TRN-' . strtoupper(Str::random(16));
            }

            if (empty($record->issued_at)) {
                $record->issued_at = now();
            }
        });
    }

    /** The address printed into the QR code. */
    public function getVerificationUrlAttribute(): string
    {
        return url('verify-transcript/' . $this->verification_code);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(StudentEnroll::class, 'student_enroll_id');
    }
}
