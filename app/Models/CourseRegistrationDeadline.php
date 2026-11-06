<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * The moment after which students of one semester type can no longer register
 * courses on the portal for one academic session.
 *
 * A First Semester deadline reaches every student in a first semester that
 * year, whatever their year of study: class and exam lists are drawn up for
 * the whole semester at once. Resit semesters are not concerned, because
 * students never register on them anyway.
 *
 * No row means no deadline. A school that never opens the setting sees nothing
 * change.
 */
class CourseRegistrationDeadline extends Model
{
    use Auditable;

    protected $fillable = ['session_id', 'semester_type', 'closes_at', 'note', 'set_by'];

    protected $casts = [
        'closes_at' => 'datetime',
    ];

    public const TYPE_FIRST = 1;
    public const TYPE_SECOND = 2;

    /** How close a deadline has to be before it is flagged. */
    public const SOON_DAYS = 7;
    public const IMMINENT_HOURS = 48;

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function setter()
    {
        return $this->belongsTo(\App\User::class, 'set_by');
    }

    public static function typeLabel(int $semesterType): string
    {
        return $semesterType === self::TYPE_SECOND
            ? __('Second Semester')
            : __('First Semester');
    }

    /** The deadline for one sitting, if the school has set one. */
    public static function for(?int $sessionId, ?int $semesterType): ?self
    {
        if (!$sessionId || !$semesterType) {
            return null;
        }

        return static::where('session_id', $sessionId)
            ->where('semester_type', $semesterType)
            ->first();
    }

    /**
     * The deadline governing one enrolment: its session and its semester's
     * type. None for a resit semester, where the question never arises.
     */
    public static function forEnrollment(?StudentEnroll $enrollment): ?self
    {
        if (!$enrollment || !$enrollment->session_id) {
            return null;
        }

        $semester = $enrollment->semester;

        if (!$semester || $semester->is_resit || !$semester->semester_type) {
            return null;
        }

        return static::for((int) $enrollment->session_id, (int) $semester->semester_type);
    }

    /** May a student still register or drop on this enrolment? */
    public static function allowsRegistration(?StudentEnroll $enrollment): bool
    {
        $deadline = static::forEnrollment($enrollment);

        return $deadline === null || !$deadline->isPast();
    }

    public function isPast(): bool
    {
        return $this->closes_at->isPast();
    }

    /**
     * How loudly to flag it: open, soon (inside a week), imminent (inside two
     * days), or closed. One word that every screen colours the same way.
     */
    public function urgency(): string
    {
        if ($this->isPast()) {
            return 'closed';
        }

        $hoursLeft = now()->diffInHours($this->closes_at, false);

        if ($hoursLeft <= self::IMMINENT_HOURS) {
            return 'imminent';
        }

        if ($hoursLeft <= self::SOON_DAYS * 24) {
            return 'soon';
        }

        return 'open';
    }

    /** Bootstrap colour for the urgency, so the chips and banners agree. */
    public function colour(): string
    {
        return match ($this->urgency()) {
            'closed' => 'dark',
            'imminent' => 'danger',
            'soon' => 'warning',
            default => 'success',
        };
    }

    /** "20/11/2026 23:59", in the school's own date format. */
    public function closesAtLabel(): string
    {
        $format = optional(Setting::first())->date_format ?: 'd/m/Y';

        return $this->closes_at->format($format . ' H:i');
    }

    /** "Friday 20 November 2026 at 23:59": the long form for students. */
    public function closesAtLong(): string
    {
        return $this->closes_at->translatedFormat('l j F Y') . ' ' . __('at') . ' ' . $this->closes_at->format('H:i');
    }

    /**
     * "14 days left", "6 hours left", "closed 3 days ago": the countdown in
     * words, counting down to the hour once a day is all that remains.
     */
    public function countdown(): string
    {
        $now = now();

        if ($this->isPast()) {
            return __('closed :ago', ['ago' => $this->closes_at->diffForHumans($now, ['syntax' => Carbon::DIFF_RELATIVE_TO_NOW])]);
        }

        $hours = $now->diffInHours($this->closes_at);

        if ($hours < 1) {
            return __('less than an hour left');
        }

        if ($hours < 24) {
            return trans_choice('{1} :count hour left|[2,*] :count hours left', $hours, ['count' => $hours]);
        }

        $days = (int) ceil($hours / 24);

        return trans_choice('{1} :count day left|[2,*] :count days left', $days, ['count' => $days]);
    }

    /**
     * What a student reads once it has passed: the school's own words where it
     * wrote any, a plain fallback otherwise.
     */
    public function noteFor(): string
    {
        $note = trim((string) $this->note);

        if ($note !== '') {
            return $note;
        }

        return __('Course registration for this semester closed on :when. If you still need a course added or removed, speak to your department.', [
            'when' => $this->closesAtLong(),
        ]);
    }

    /**
     * The enrolments this deadline reaches: students of this session whose
     * semester is of this type and not a resit.
     */
    public function enrolments()
    {
        return StudentEnroll::where('session_id', $this->session_id)
            ->whereNotNull('student_id')
            ->where('status', 1)
            ->whereHas('semester', fn ($q) => $q->where('semester_type', $this->semester_type)->where('is_resit', 0));
    }
}
