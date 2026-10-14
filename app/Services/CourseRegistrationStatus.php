<?php

namespace App\Services;

use App\Models\EnrollSubject;
use App\Models\ProgramSessionMaxCredit;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Whether this student still has courses to register, and what to tell them.
 *
 * The answer was buried inside CourseRegistrationController::index(), which is
 * fine while the registration page is the only thing that needs it. The
 * dashboard now needs the same answer, and two copies of "which courses may
 * this student still take" would drift apart within a term - so the rule lives
 * here and both screens ask it.
 *
 * It also answers WHY a student cannot register, because "you have courses to
 * register" is useless to someone the portal is about to redirect to the
 * platform fee page. Each state carries the sentence a student should actually
 * read.
 */
class CourseRegistrationStatus
{
    public const OPEN = 'open';
    public const PARTLY_DONE = 'partly_done';
    public const DONE = 'done';
    public const RESIT = 'resit';
    public const NOTHING_OFFERED = 'nothing_offered';
    public const BLOCKED_BY_FEE = 'blocked_by_fee';
    public const NOT_ENROLLED = 'not_enrolled';

    protected PlatformFeeAccess $access;

    public function __construct(PlatformFeeAccess $access)
    {
        $this->access = $access;
    }

    /**
     * The enrolment the portal is currently showing, by the same rule the
     * registration page uses: whatever the student picked, else their current
     * one.
     */
    public function currentEnrollment(Student $student): ?StudentEnroll
    {
        $selected = session('selected_enrollment_id');

        if ($selected) {
            $enroll = StudentEnroll::with(['subjects', 'subjectMarks.subject', 'session', 'semester', 'section', 'program'])
                ->where('id', $selected)
                ->where('student_id', $student->id)
                ->first();

            if ($enroll) {
                return $enroll;
            }
        }

        $student->loadMissing([
            'currentEnroll.subjects',
            'currentEnroll.subjectMarks.subject',
            'currentEnroll.session',
            'currentEnroll.semester',
            'currentEnroll.section',
            'currentEnroll.program',
        ]);

        return $student->currentEnroll;
    }

    /**
     * Everything the two screens need to say the same thing.
     *
     * @return array{state: string, registered: int, available: int, assignable: Collection, max_credit: ?float, is_resit: bool, enrollment: ?StudentEnroll, headline: string, detail: string, action: ?string}
     */
    public function for(Student $student, ?StudentEnroll $enroll = null): array
    {
        $enroll = $enroll ?: $this->currentEnrollment($student);

        $answer = [
            'state' => self::NOT_ENROLLED,
            'registered' => 0,
            'available' => 0,
            'assignable' => collect(),
            'max_credit' => null,
            'is_resit' => false,
            'enrollment' => $enroll,
            'headline' => __('You are not enrolled in a semester yet.'),
            'detail' => __('Course registration opens once the school has enrolled you for the semester.'),
            'action' => null,
        ];

        if (!$enroll) {
            return $answer;
        }

        $answer['registered'] = $enroll->subjects->count();
        $answer['is_resit'] = (bool) optional($enroll->semester)->is_resit;

        // Asked before anything else: a student the portal will redirect to the
        // payment page must not be told to go and register courses.
        if (!$this->access->grantsAccess($student)) {
            return array_merge($answer, [
                'state' => self::BLOCKED_BY_FEE,
                'headline' => __('Your portal access fee is outstanding.'),
                'detail' => __('Course registration opens as soon as that payment is recorded.'),
                'action' => route('student.platform-fee.payment'),
            ]);
        }

        if ($answer['is_resit']) {
            return array_merge($answer, [
                'state' => self::RESIT,
                'headline' => __('This is a resit semester.'),
                'detail' => __('Resit courses are entered by the school, so there is nothing for you to register.'),
            ]);
        }

        $answer['assignable'] = $this->assignableSubjects($student, $enroll);
        $answer['available'] = $answer['assignable']->count();
        $answer['max_credit'] = $this->maxCredit($enroll);

        if ($answer['available'] > 0) {
            $partly = $answer['registered'] > 0;

            return array_merge($answer, [
                'state' => $partly ? self::PARTLY_DONE : self::OPEN,
                'headline' => $partly
                    ? __('You still have :count course(s) left to register.', ['count' => $answer['available']])
                    : __('You have :count course(s) to register for this semester.', ['count' => $answer['available']]),
                'detail' => $partly
                    ? __('You have registered :done so far. The rest are still waiting.', ['done' => $answer['registered']])
                    : __('Registering tells the school which classes you are taking, so you appear on the class and exam lists.'),
                'action' => route('student.course-registration.index'),
            ]);
        }

        if ($answer['registered'] > 0) {
            return array_merge($answer, [
                'state' => self::DONE,
                'headline' => __('Your courses are registered.'),
                'detail' => __('You are registered for :count course(s) this semester.', ['count' => $answer['registered']]),
            ]);
        }

        return array_merge($answer, [
            'state' => self::NOTHING_OFFERED,
            'headline' => __('No courses are open to you yet.'),
            'detail' => __('Your programme has no courses set for this semester yet. The school has to assign them before you can register.'),
        ]);
    }

    /**
     * Does this student need prompting on the dashboard?
     */
    public function needsAttention(array $status): bool
    {
        return in_array($status['state'], [self::OPEN, self::PARTLY_DONE], true);
    }

    /**
     * The courses this student may still register.
     *
     * Lifted unchanged from CourseRegistrationController::index(): the courses
     * set for their programme this semester, plus the ones from earlier years of
     * the same semester type, minus anything already validated at 50% or more,
     * minus whatever they have already registered.
     */
    public function assignableSubjects(Student $student, StudentEnroll $enroll): Collection
    {
        if (!$student->program_id || optional($enroll->semester)->is_resit) {
            return collect();
        }

        $currentSemester = $enroll->semester;

        if (!$currentSemester) {
            return collect();
        }

        $currentYear = $currentSemester->year;
        $currentSemesterType = $currentSemester->semester_type ?? 1;

        $enrollSubject = EnrollSubject::where('program_id', $enroll->program_id)
            ->where('semester_id', $enroll->semester_id)
            ->where('section_id', $enroll->section_id)
            ->first();

        $currentSemesterSubjectIds = $enrollSubject
            ? $enrollSubject->subjects()->pluck('subject_id')->toArray()
            : [];

        $previousYearsSemesterIds = Semester::where('status', 1)
            ->where('semester_type', $currentSemesterType)
            ->where('year', '<=', $currentYear)
            ->where('id', '!=', $enroll->semester_id)
            ->pluck('id')
            ->toArray();

        $previousYearsSubjectIds = [];

        if (!empty($previousYearsSemesterIds)) {
            $previousEnrollSubjects = EnrollSubject::where('program_id', $enroll->program_id)
                ->whereIn('semester_id', $previousYearsSemesterIds)
                ->where('section_id', $enroll->section_id)
                ->get();

            foreach ($previousEnrollSubjects as $enrollSub) {
                $previousYearsSubjectIds = array_merge(
                    $previousYearsSubjectIds,
                    $enrollSub->subjects()->pluck('subject_id')->toArray()
                );
            }
        }

        $allEligibleSubjectIds = array_unique(array_merge($currentSemesterSubjectIds, $previousYearsSubjectIds));

        if (empty($allEligibleSubjectIds)) {
            return collect();
        }

        $allSubjects = Subject::whereIn('id', $allEligibleSubjectIds)
            ->where('status', '1')
            ->orderBy('code', 'asc')
            ->get();

        // Only this programme's enrolments, and only marks the student can see.
        $student->loadMissing('studentEnrolls.subjectMarks.subject');
        $validatedSubjectIds = [];

        foreach ($student->studentEnrolls as $other) {
            if ($other->program_id != $enroll->program_id) {
                continue;
            }

            foreach ($other->subjectMarks ?? [] as $mark) {
                if ($mark->is_visible_to_student && $mark->total_marks >= 50) {
                    $validatedSubjectIds[] = $mark->subject_id;
                }
            }
        }

        $validatedSubjectIds = array_unique($validatedSubjectIds);

        $available = $allSubjects->reject(
            fn ($subject) => in_array($subject->id, $validatedSubjectIds)
        )->values();

        $alreadyRegistered = $enroll->subjects->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return $available->values();
    }

    public function maxCredit(StudentEnroll $enroll): ?float
    {
        $facultyId = optional($enroll->program)->faculty_id;

        $limit = ProgramSessionMaxCredit::resolveLimit(
            (int) $enroll->program_id,
            (int) $enroll->session_id,
            $facultyId ? (int) $facultyId : null
        );

        return $limit === null ? null : (float) $limit;
    }
}
