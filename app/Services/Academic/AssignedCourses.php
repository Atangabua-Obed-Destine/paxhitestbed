<?php

namespace App\Services\Academic;

use App\Models\ClassRoutine;
use App\Models\Semester;
use App\Models\Session;
use App\Models\StaffAssignment;
use App\User;
use Illuminate\Support\Collection;

/**
 * The courses a member of staff is actually scheduled to teach this session.
 *
 * This is what lets a lecturer land on the screen and see their own subjects
 * instead of working the faculty/programme/session/semester/section filters
 * every single time.
 *
 * It existed already - three times, in ExamMarkingController,
 * ExamAttendanceController and ExamRoutineController, at 136, 106 and 109
 * lines. The attendance and routine copies had already drifted apart on how
 * they decide who is a super admin: one asks the role by name, the other by
 * slug, so the same person could be treated differently depending on which
 * screen they opened. That is the cost of the fourth copy nobody wrote down,
 * and the reason this is a service rather than a paste.
 *
 * The shape returned is the one the card blocks already expect, so the views
 * need no new vocabulary:
 *
 *   [ ['program_id', 'program_title', 'program_code', 'faculty_id',
 *      'faculty_title', 'subjects' => [ ['subject_id', 'subject_code',
 *      'subject_title', 'semesters', 'years', 'first_year',
 *      'first_semester_id', 'first_section_id', 'first_section_title',
 *      'sections'] ] ] ]
 */
class AssignedCourses
{
    /**
     * Every course this user teaches in the current session, grouped by
     * programme. An empty collection when there is no current session.
     */
    public static function forUser(?int $userId): Collection
    {
        $currentSession = static::currentSession();

        if (!$currentSession || !$userId) {
            return collect();
        }

        $routines = static::routinesFor($userId, $currentSession->id);

        return static::group($routines);
    }

    /** The session the school is currently running. */
    public static function currentSession(): ?Session
    {
        return Session::where('status', '1')->where('current', '1')->first();
    }

    /**
     * The class routines this user may see.
     *
     * Three cases, in the order they are decided:
     *   - a super admin sees everything;
     *   - a user with staff assignments sees what those assignments allow;
     *   - anyone else sees only what they are the teacher of.
     */
    protected static function routinesFor(int $userId, int $sessionId): Collection
    {
        $query = ClassRoutine::where('session_id', $sessionId)
            ->where('status', '1')
            ->with(['subject', 'program.faculty', 'semester', 'section']);

        $user = User::find($userId);

        // Asked by role name, which is how the rest of this system asks. One of
        // the old copies asked by slug instead, so a role whose slug did not
        // match treated a super admin as an ordinary teacher.
        $isSuperAdmin = $user ? $user->hasRole('Super Admin') : false;

        $assignments = StaffAssignment::where('user_id', $userId)->get();

        if (!$isSuperAdmin && $assignments->isEmpty()) {
            $query->where('teacher_id', $userId);
        }

        if ($assignments->isNotEmpty()) {
            $faculties = $assignments->pluck('faculty_id')->filter()->unique();
            $programs = $assignments->pluck('program_id')->filter()->unique();
            $subjects = $assignments->pluck('subject_id')->filter()->unique();

            $query->where(function ($inner) use ($faculties, $programs, $subjects) {
                if ($faculties->isNotEmpty()) {
                    $inner->whereHas('program', fn ($q) => $q->whereIn('faculty_id', $faculties));
                }

                if ($programs->isNotEmpty()) {
                    $inner->orWhereIn('program_id', $programs);
                }

                if ($subjects->isNotEmpty()) {
                    $inner->orWhereIn('subject_id', $subjects);
                }
            });
        }

        return $query->get();
    }

    /** Programme -> subjects, each subject carrying what its card needs. */
    protected static function group(Collection $routines): Collection
    {
        return $routines
            ->filter(fn ($routine) => $routine->program && $routine->subject)
            ->groupBy('program_id')
            ->map(function ($programRoutines) {
                $program = $programRoutines->first()->program;

                $subjects = $programRoutines
                    ->groupBy('subject_id')
                    ->map(fn ($subjectRoutines) => static::describeSubject($subjectRoutines))
                    ->sortBy('subject_code')
                    ->values();

                return [
                    'program_id' => $program->id,
                    'program_title' => $program->title,
                    'program_code' => $program->short_code ?? $program->title,
                    'faculty_id' => $program->faculty_id,
                    'faculty_title' => optional($program->faculty)->title ?? __('Unknown Faculty'),
                    'subjects' => $subjects,
                ];
            })
            ->sortBy('program_title')
            ->values();
    }

    protected static function describeSubject(Collection $subjectRoutines): array
    {
        $subject = $subjectRoutines->first()->subject;

        $regular = $subjectRoutines->pluck('semester')->filter()->unique('id')
            ->sortBy(['year', 'semester_type'])->values();

        // A resit is taught alongside the semester it resits, so it belongs on
        // the same card.
        $resits = $regular->isEmpty() ? collect() : Semester::where('is_resit', true)
            ->whereIn('parent_semester_id', $regular->pluck('id')->all())
            ->where('status', 1)
            ->orderBy('year')
            ->orderBy('semester_type')
            ->get();

        $all = $regular->concat($resits)->sortBy(['year', 'semester_type', 'is_resit'])->values();
        $years = $all->pluck('year')->filter()->unique()->sortBy(fn ($year) => (int) $year)->values();
        $sections = $subjectRoutines->pluck('section')->filter()->unique('id')->values();

        // The regular semester is the sensible default; a resit is the
        // exception a lecturer chooses deliberately.
        $firstSemester = $regular->first() ?? $all->first();

        return [
            'subject_id' => $subject->id,
            'subject_code' => $subject->code,
            'subject_title' => $subject->title,
            'semesters' => $all,
            'years' => $years,
            'first_year' => $years->first() ?? 1,
            'first_semester_id' => optional($firstSemester)->id,
            'first_section_id' => optional($sections->first())->id,
            'first_section_title' => optional($sections->first())->title ?? __('All'),
            'sections' => $sections,
        ];
    }

    /** How many courses are in a grouped result. */
    public static function countCourses(Collection $grouped): int
    {
        return (int) $grouped->sum(fn ($program) => $program['subjects']->count());
    }
}
