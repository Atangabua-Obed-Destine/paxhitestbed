<?php

namespace App\Services\Academic;

use App\Models\StudentAttendance;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Support\CourseClassFilter;
use Illuminate\Support\Collection;

/**
 * Who is in a course, and how they have been attending it.
 *
 * The screen, the PDF and the workbook all read this one array, so a number
 * cannot say one thing on the page and another on the paper.
 *
 * Five rules the arithmetic has to follow. Each is a way of getting a wrong
 * answer that THIS data would really produce, not a hypothetical:
 *
 * 1. AGGREGATE TO THE STUDENT, not to the enrolment. A student holds one
 *    student_enrolls row per semester, and their attendance is split across
 *    them: on this install all 63 multi-enrolment students have attendance on
 *    more than one row. Listing enrolments shows the same person several times,
 *    each with a fraction of their record.
 *
 * 2. NEVER FILTER enrolment status = '1'. It means "the semester this student
 *    is in now". See CourseClassFilter for the measurement; the short version
 *    is that it empties the register of any course whose students have moved on.
 *
 * 3. A HOLIDAY IS NOT A WORKING DAY. The rate is present / (present + absent +
 *    leave), which is what the existing month-grid report computes. Counting
 *    holidays in the denominator would quietly lower everyone's rate, and the
 *    two screens would disagree.
 *
 * 4. UNMARKED IS NOT ABSENT. A student with no row for a date the register was
 *    taken was not marked -- they may have joined late, or the lecturer may
 *    have missed them. Folding that into "absent" invents absences and can cost
 *    a student an exam seat. It gets its own column and stays out of the rate.
 *
 * 5. COUNT WHAT THE SCOPE LEAVES OUT. Attendance can sit on an enrolment that
 *    the chosen narrowing excludes. Rather than drop it silently the way the
 *    older screens do, outside() counts it so the report says so on its face.
 */
class CourseRoster
{
    /** attendance column => key. 3 is unused on this install but documented. */
    public const CODES = [
        1 => 'present',
        2 => 'absent',
        3 => 'leave',
        4 => 'holiday',
    ];

    /**
     * Ordered severity: attended, excused, did not attend. Holiday is not a
     * state of the student, so it takes a neutral.
     *
     * Validated as a set (worst adjacent CVD dE 9.8 protan, normal-vision 20.7,
     * all >= 3:1 on white). Never the only carrier of meaning -- every strip
     * sits beside the same counts in figures.
     */
    public const COLOURS = [
        'present' => '#0f7a3d',
        'leave' => '#b8860b',
        'absent' => '#b3261e',
        'holiday' => '#6b7480',
        'unmarked' => '#d7dbe0',
    ];

    public function build(CourseClassFilter $filter, ?Subject $subject = null): array
    {
        $groups = $filter->allowedGroups();
        $enrolls = $this->enrolments($filter, $groups);
        $enrollIds = $enrolls->pluck('id')->all();

        $attendance = $this->attendance($filter, $enrollIds);
        $dates = $attendance->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->sort()->values();

        $students = $this->students($enrolls, $attendance, $dates);

        return [
            'institution' => $this->institution(),
            'generated_at' => now(),
            'date_format' => 'd/m/Y',
            'subject' => $subject,
            'filters' => $filter->describe($subject),
            'narrowed' => $filter->isNarrowed(),
            'classes' => $filter->classesForSubject(),
            'dates' => $dates->all(),
            'sessions_held' => $dates->count(),
            'students' => $students,
            'totals' => $this->totals($students, $dates->count()),
            'by_class' => $this->byClass($students),
            'by_date' => $this->byDate($attendance, $dates),
            'at_risk' => $this->atRisk($students),
            'outside' => $this->outside($filter, $enrollIds, $groups),
            'has_data' => $students->isNotEmpty(),
            'colours' => self::COLOURS,
        ];
    }

    // ------------------------------------------------------------- the data

    /**
     * Every enrolment taking this subject in this session that this user may
     * see. Note the absence of a status filter -- rule 2.
     *
     * $groups is the access boundary (null = see everything); the programme,
     * semester and section are the user's own narrowing INSIDE it. Keeping the
     * two apart is what lets outside() report "your filter hid this" without
     * ever reporting "another lecturer's class is hidden", which is not the
     * reader's business and would be a leak if it were.
     */
    protected function enrolments(CourseClassFilter $filter, ?array $groups): Collection
    {
        $query = $this->scoped($filter, $groups);

        if ($filter->programId()) {
            $query->where('program_id', $filter->programId());
        }

        if ($filter->semesterId()) {
            $query->where('semester_id', $filter->semesterId());
        }

        if ($filter->sectionId()) {
            $query->where('section_id', $filter->sectionId());
        }

        return $query->get();
    }

    /** Everything inside the access boundary, before the user's own filter. */
    protected function scoped(CourseClassFilter $filter, ?array $groups)
    {
        $query = StudentEnroll::with(CourseClassFilter::RELATIONS)
            ->where('session_id', $filter->sessionId())
            ->whereHas('subjects', fn ($q) => $q->where('subject_id', $filter->subjectId()));

        if ($groups !== null) {
            // An empty allowed set means this user teaches no group of this
            // course. whereRaw('0=1') rather than leaving the query open:
            // an empty whereIn is the kind of mistake that returns everything.
            if (!$groups) {
                return $query->whereRaw('1 = 0');
            }

            $query->where(function ($outer) use ($groups) {
                foreach ($groups as $group) {
                    [$program, $semester, $section] = array_pad(explode('|', $group), 3, null);

                    $outer->orWhere(function ($q) use ($program, $semester, $section) {
                        $q->where('program_id', $program)
                            ->where('semester_id', $semester)
                            ->where('section_id', $section);
                    });
                }
            });
        }

        return $query;
    }

    protected function attendance(CourseClassFilter $filter, array $enrollIds): Collection
    {
        if (!$enrollIds) {
            return collect();
        }

        $query = StudentAttendance::where('subject_id', $filter->subjectId())
            ->whereIn('student_enroll_id', $enrollIds);

        if ($filter->from()) {
            $query->whereDate('date', '>=', $filter->from());
        }

        if ($filter->to()) {
            $query->whereDate('date', '<=', $filter->to());
        }

        return $query->orderBy('date')->get();
    }

    /**
     * Attendance for this course that the chosen narrowing excludes.
     *
     * Two aggregates, no rows fetched: this exists to put a number on the page,
     * not to list it.
     */
    protected function outside(CourseClassFilter $filter, array $enrollIds, ?array $groups): array
    {
        // Measured against what this user MAY see, not against the whole
        // subject: a lecturer is told what their own group filter hid, never
        // that a colleague's class exists.
        $inScope = $this->scoped($filter, $groups)->pluck('id')->all();

        if (!$inScope) {
            return ['records' => 0, 'students' => 0];
        }

        $query = StudentAttendance::where('subject_id', $filter->subjectId())
            ->whereIn('student_enroll_id', $inScope);

        if ($enrollIds) {
            $query->whereNotIn('student_enroll_id', $enrollIds);
        }

        if ($filter->from()) {
            $query->whereDate('date', '>=', $filter->from());
        }

        if ($filter->to()) {
            $query->whereDate('date', '<=', $filter->to());
        }

        return [
            'records' => (clone $query)->count(),
            'students' => (clone $query)->distinct()->count('student_enroll_id'),
        ];
    }

    // -------------------------------------------------------- the roster row

    /**
     * One row per person, their enrolments folded together -- rule 1.
     */
    protected function students(Collection $enrolls, Collection $attendance, Collection $dates): Collection
    {
        $byEnroll = $attendance->groupBy('student_enroll_id');
        $held = $dates->count();

        return $enrolls
            ->filter(fn ($enroll) => $enroll->student)
            ->groupBy('student_id')
            ->map(function (Collection $group) use ($byEnroll, $dates, $held) {
                // The enrolment that best describes where this person sits now:
                // the one they are currently in, else the most recent.
                $primary = $group->firstWhere('status', '1') ?? $group->sortByDesc('id')->first();
                $student = $primary->student;

                $rows = $group->flatMap(fn ($enroll) => $byEnroll->get($enroll->id, collect()));

                $tally = array_fill_keys(array_values(self::CODES), 0);
                $marks = [];

                foreach ($rows as $row) {
                    $key = self::CODES[(int) $row->attendance] ?? null;

                    if ($key === null) {
                        continue;
                    }

                    $tally[$key]++;
                    $marks[substr((string) $row->date, 0, 10)] = $key;
                }

                // Rule 3: a holiday is not a working day. Rule 4: unmarked is
                // not absent, so it is absent from the denominator too.
                $working = $tally['present'] + $tally['absent'] + $tally['leave'];
                $marked = $working + $tally['holiday'];

                return [
                    'student_id' => $student->id,
                    'matricule' => $primary->matricule ?: ($student->student_id ?? ''),
                    'registration' => $student->student_id ?? '',
                    'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                    'gender' => $this->gender($student->gender ?? null),
                    // A phone number is a label, not a quantity. Left numeric it
                    // loses a leading zero and Excel right-aligns it as a figure.
                    'phone' => (string) ($student->phone ?? ''),
                    'email' => $student->email ?? '',
                    'photo' => $student->photo ?? null,
                    'program' => $primary->program->title ?? '',
                    'semester' => $primary->semester->title ?? '',
                    'section' => $primary->section->title ?? '',
                    'enrolments' => $group->count(),
                    'present' => $tally['present'],
                    'absent' => $tally['absent'],
                    'leave' => $tally['leave'],
                    'holiday' => $tally['holiday'],
                    'working' => $working,
                    'unmarked' => max(0, $held - $marked),
                    'rate' => $working > 0 ? round($tally['present'] / $working * 100, 1) : null,
                    'last_seen' => $this->lastPresent($marks),
                    // One cell per date the register was taken, so every strip
                    // lines up and can be compared down the column.
                    'strip' => $dates->map(fn ($date) => $marks[$date] ?? 'unmarked')->all(),
                ];
            })
            ->sortBy([['matricule', 'asc']])
            ->values();
    }

    /** The codes the rest of the app uses, so a sheet says Male, not 1. */
    protected function gender(mixed $code): string
    {
        return [
            1 => __('gender_male'),
            2 => __('gender_female'),
            3 => __('gender_other'),
        ][(int) $code] ?? '';
    }

    protected function lastPresent(array $marks): ?string
    {
        $present = array_keys(array_filter($marks, fn ($key) => $key === 'present'));

        if (!$present) {
            return null;
        }

        sort($present);

        return end($present);
    }

    // ------------------------------------------------------------- roll-ups

    protected function totals(Collection $students, int $held): array
    {
        $working = $students->sum('working');
        $present = $students->sum('present');

        return [
            'students' => $students->count(),
            'sessions_held' => $held,
            'present' => $present,
            'absent' => $students->sum('absent'),
            'leave' => $students->sum('leave'),
            'holiday' => $students->sum('holiday'),
            'unmarked' => $students->sum('unmarked'),
            'working' => $working,
            'rate' => $working > 0 ? round($present / $working * 100, 1) : null,
        ];
    }

    /**
     * The same figures per teaching group, which for a service course is the
     * only way to see that one programme is drifting and the rest are fine.
     */
    protected function byClass(Collection $students): array
    {
        return $students
            ->groupBy(fn ($row) => $row['program'] . '|' . $row['semester'] . '|' . $row['section'])
            ->map(function (Collection $group) {
                $working = $group->sum('working');
                $present = $group->sum('present');
                $first = $group->first();

                return [
                    'program' => $first['program'],
                    'semester' => $first['semester'],
                    'section' => $first['section'],
                    'students' => $group->count(),
                    'present' => $present,
                    'absent' => $group->sum('absent'),
                    'leave' => $group->sum('leave'),
                    'unmarked' => $group->sum('unmarked'),
                    'rate' => $working > 0 ? round($present / $working * 100, 1) : null,
                ];
            })
            ->sortBy([['program', 'asc'], ['semester', 'asc'], ['section', 'asc']])
            ->values()
            ->all();
    }

    /** Turnout for each date the register was taken. */
    protected function byDate(Collection $attendance, Collection $dates): array
    {
        $byDate = $attendance->groupBy(fn ($row) => substr((string) $row->date, 0, 10));

        return $dates->map(function ($date) use ($byDate) {
            $rows = $byDate->get($date, collect());
            $counts = array_fill_keys(array_values(self::CODES), 0);

            foreach ($rows as $row) {
                $key = self::CODES[(int) $row->attendance] ?? null;

                if ($key !== null) {
                    $counts[$key]++;
                }
            }

            $working = $counts['present'] + $counts['absent'] + $counts['leave'];

            return [
                'date' => $date,
                'present' => $counts['present'],
                'absent' => $counts['absent'],
                'leave' => $counts['leave'],
                'holiday' => $counts['holiday'],
                'marked' => $rows->count(),
                'rate' => $working > 0 ? round($counts['present'] / $working * 100, 1) : null,
            ];
        })->all();
    }

    /**
     * The people the lecturer should actually do something about.
     *
     * Deliberately not a percentage alone: someone marked twice and absent once
     * is at 33% and needs no letter, while someone absent the last six running
     * does, whatever their average says. So both tests, and the reason is
     * carried on the row rather than left for the reader to infer.
     */
    protected function atRisk(Collection $students): array
    {
        return $students
            ->map(function ($row) {
                $reasons = [];

                if ($row['working'] >= 4 && $row['rate'] !== null && $row['rate'] < 75) {
                    $reasons[] = __('Below 75%');
                }

                $trailing = 0;

                foreach (array_reverse($row['strip']) as $mark) {
                    if ($mark === 'unmarked') {
                        continue;
                    }

                    if ($mark === 'absent') {
                        $trailing++;
                        continue;
                    }

                    break;
                }

                if ($trailing >= 3) {
                    $reasons[] = __('Absent :n sessions running', ['n' => $trailing]);
                }

                if ($row['working'] === 0 && $row['unmarked'] > 0) {
                    $reasons[] = __('Never marked');
                }

                return $reasons ? $row + ['reasons' => $reasons, 'trailing' => $trailing] : null;
            })
            ->filter()
            ->sortBy([['rate', 'asc'], ['matricule', 'asc']])
            ->values()
            ->all();
    }

    protected function institution(): string
    {
        return (string) (optional(\App\Models\Setting::first())->title ?? config('app.name'));
    }
}
