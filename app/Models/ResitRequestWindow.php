<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether the school is still taking resit requests for one sitting.
 *
 * A sitting is an academic session and a semester type — the grain a resit
 * timetable actually has. Once the timetable is drawn up the school stops
 * accepting new requests for it, and students need telling rather than
 * discovering it when someone turns their request down.
 *
 * Absence of a row means open: a school that never touches this screen is
 * unaffected.
 */
class ResitRequestWindow extends Model
{
    use Auditable;

    protected $fillable = [
        'session_id', 'semester_type', 'is_open', 'note', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public const TYPE_FIRST = 1;
    public const TYPE_SECOND = 2;

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public static function typeLabel(int $semesterType): string
    {
        return $semesterType === self::TYPE_SECOND
            ? __('Second Semester')
            : __('First Semester');
    }

    /**
     * May a student still ask for a resit of this sitting?
     *
     * Open unless the school has said otherwise, so this changes nothing for an
     * institution that has not used the screen.
     */
    public static function acceptsRequests(?int $sessionId, ?int $semesterType): bool
    {
        if (!$sessionId || !$semesterType) {
            return true;
        }

        $window = static::where('session_id', $sessionId)
            ->where('semester_type', $semesterType)
            ->first();

        return $window === null || $window->is_open;
    }

    /**
     * What the student is told when it is closed.
     *
     * The school's own words where it has written any, so it can say when the
     * next sitting is; a plain fallback otherwise, so a closed window is never
     * just a missing button.
     */
    public static function noteFor(?int $sessionId, ?int $semesterType): string
    {
        $window = $sessionId && $semesterType
            ? static::where('session_id', $sessionId)->where('semester_type', $semesterType)->first()
            : null;

        $note = trim((string) optional($window)->note);

        if ($note !== '') {
            return $note;
        }

        return __('Resit requests for this semester are closed. You can still decline a course to carry it over instead.');
    }

    /** The window governing a given enrolment, if the school has set one. */
    public static function forEnrollment(?StudentEnroll $enrollment): ?self
    {
        if (!$enrollment || !$enrollment->session_id) {
            return null;
        }

        $semesterType = optional($enrollment->semester)->semester_type;

        if (!$semesterType) {
            return null;
        }

        return static::where('session_id', $enrollment->session_id)
            ->where('semester_type', $semesterType)
            ->first();
    }
}
