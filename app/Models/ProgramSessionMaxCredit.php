<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramSessionMaxCredit extends Model
{
    /**
     * @var string
     */
    protected $table = 'program_session_max_credits';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'faculty_id',
        'program_id',
        'session_id',
        'max_credit_hours',
    ];

    /**
     * Get the faculty for the configuration.
     */
    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    /**
     * Get the program for the configuration.
     */
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /**
     * Get the session for the configuration.
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    /**
     * Resolve the configured credit-hour limit for the supplied academic combination.
     */
    public static function resolveLimit(int $programId, int $sessionId, ?int $facultyId = null): ?int
    {
        $query = static::where('program_id', $programId)
            ->where('session_id', $sessionId);

        if ($facultyId !== null) {
            $query->where('faculty_id', $facultyId);
        }

        $configuration = $query->first();

        if (!$configuration) {
            return null;
        }

        $value = $configuration->max_credit_hours;

        return ($value !== null && (int) $value > 0)
            ? (int) $value
            : null;
    }
}
