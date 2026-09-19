<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A student's national exam code for one sitting.
 *
 * CNOENC issues the school a pre-registration code list each year — one code per
 * student registered for the HND exam. This is where those codes are kept, so
 * the school can print the list from the system instead of by hand.
 */
class StudentExamCode extends Model
{
    use Auditable;

    /** The commission's format: HND followed by 10 hexadecimal characters. */
    public const CODE_PATTERN = '/^HND[0-9A-F]{10}$/';

    protected $fillable = [
        'student_id', 'session_id', 'level', 'code', 'program_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    /** As the commission writes it: upper case, no spaces. */
    public static function normalise(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }

    public static function looksLikeCommissionCode(?string $code): bool
    {
        return (bool) preg_match(self::CODE_PATTERN, self::normalise($code));
    }

    public function scopeForSitting($query, $sessionId, $level)
    {
        return $query->where('session_id', $sessionId)->where('level', $level);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
