<?php

namespace App\Services\Resit;

use App\Models\Semester;
use App\Models\StudentEnroll;
use App\Services\Academic\SemesterProgressionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resit sittings that never received what they inherit.
 *
 * A resit sitting does not start blank. It carries over, from the semester the
 * resit semester shadows, the attendance already taken and the continuous
 * assessment already earned — the student is re-sitting the FINAL, not the
 * course. The student-initiated routes have always done that. The admin route
 * created the enrolment and stopped, so every sitting it raised holds no CA
 * mark and, worse, no exam row at all.
 *
 * THE SECOND CONSEQUENCE IS THE ONE PEOPLE SEE. With no exam row the student
 * is absent from the CA marking screen entirely, and the only thing that
 * brings a row into existence is saving exam attendance — which continuous
 * assessment does not otherwise require. So the symptom reads as "CA suddenly
 * demands attendance" rather than "the sitting is missing its marks".
 *
 * ResitEnrolmentBackfill now inherits on its own, so nothing new arrives in
 * this state. This is for the ones already raised, and it is the single answer
 * to "who is affected" for both the screen and `php artisan resits:inherit` —
 * two detectors would eventually disagree about who needs repairing.
 *
 * WHAT COUNTS AS AFFECTED. A course is missing only when the PARENT holds a CA
 * mark the sitting does not. A course the parent never had assessed has
 * nothing to inherit, so listing it would offer a repair that cannot do
 * anything. Everything is read in a fixed number of queries; nothing walks
 * students one at a time.
 */
class ResitInheritanceAudit
{
    public function __construct(protected SemesterProgressionService $progression)
    {
    }

    /**
     * Every sitting that is missing inherited marks, worst first.
     *
     * @return Collection<int, array{
     *     enrolment: StudentEnroll,
     *     resit_semester: ?Semester,
     *     parent_enrolment: ?StudentEnroll,
     *     missing: list<array{subject_id: int, subject_code: string, subject_title: string, ca: string}>,
     *     attendance_missing: int,
     *     marking_missing: int,
     * }>
     */
    public function affected(?array $enrolmentIds = null): Collection
    {
        $resitSemesters = Semester::where('is_resit', 1)->get()->keyBy('id');

        if ($resitSemesters->isEmpty()) {
            return collect();
        }

        $sittings = StudentEnroll::whereIn('semester_id', $resitSemesters->keys())
            ->when($enrolmentIds !== null, fn ($q) => $q->whereIn('id', $enrolmentIds))
            ->with(['student', 'program'])
            ->orderBy('id')
            ->get();

        if ($sittings->isEmpty()) {
            return collect();
        }

        // The courses each sitting carries.
        $registered = DB::table('student_enroll_subject')
            ->whereIn('student_enroll_id', $sittings->pluck('id'))
            ->get()
            ->groupBy('student_enroll_id')
            ->map(fn ($rows) => $rows->pluck('subject_id')->map(fn ($id) => (int) $id)->all());

        // The enrolment each sitting inherits from: same student and programme,
        // in the semester the resit semester names as its parent.
        $parents = $this->parentsFor($sittings, $resitSemesters);

        $sittingCa = $this->caMarks($sittings->pluck('id')->all());
        $parentCa = $this->caMarks($parents->pluck('id')->all());

        $subjects = DB::table('subjects')
            ->select('id', 'code', 'title')->get()->keyBy('id');

        $sittingAttendance = DB::table('student_attendances')
            ->whereIn('student_enroll_id', $sittings->pluck('id'))
            ->select('student_enroll_id', 'subject_id', DB::raw('count(*) n'))
            ->groupBy('student_enroll_id', 'subject_id')->get()
            ->mapWithKeys(fn ($r) => [$r->student_enroll_id . ':' . $r->subject_id => (int) $r->n]);

        $parentAttendance = DB::table('student_attendances')
            ->whereIn('student_enroll_id', $parents->pluck('id'))
            ->select('student_enroll_id', 'subject_id', DB::raw('count(*) n'))
            ->groupBy('student_enroll_id', 'subject_id')->get()
            ->mapWithKeys(fn ($r) => [$r->student_enroll_id . ':' . $r->subject_id => (int) $r->n]);

        $sittingMarkings = DB::table('subject_markings')
            ->whereIn('student_enroll_id', $sittings->pluck('id'))
            ->get(['student_enroll_id', 'subject_id'])
            ->map(fn ($r) => $r->student_enroll_id . ':' . $r->subject_id)
            ->flip();

        $out = collect();

        foreach ($sittings as $sitting) {
            $courseIds = $registered[$sitting->id] ?? [];

            if ($courseIds === []) {
                continue;
            }

            $parent = $parents->first(fn ($p) => $this->isParentOf($p, $sitting, $resitSemesters));

            if (!$parent) {
                continue;
            }

            $missing = [];
            $attendanceMissing = 0;
            $markingMissing = 0;

            foreach ($courseIds as $subjectId) {
                $parentMark = $parentCa[$parent->id . ':' . $subjectId] ?? null;

                // Nothing assessed on the parent: nothing to carry over, so
                // this is not something a repair could put right.
                if ($parentMark === null) {
                    continue;
                }

                // The trigger is the MARK, not the row. Two states count as
                // not having inherited:
                //
                //  - no CA row at all, which is how a sitting raised by the
                //    old admin route looks, and why the student never appears
                //    on the CA screen;
                //  - a CA row carrying no mark, which is what saving CA exam
                //    attendance leaves behind. Registrars do take it, because
                //    taking it is the only thing that makes the student
                //    appear — so this is the state the first symptom leads
                //    people into, and it still needs the mark carried over.
                //
                // A row that holds a mark has inherited, or has been re-marked
                // since, and must not be touched. Attendance drifting behind
                // the parent is not a failed inheritance either: that is the
                // parent gaining days afterwards.
                $sittingMark = $sittingCa->get($sitting->id . ':' . $subjectId);

                if ($sittingMark !== null) {
                    continue;
                }

                $missing[] = [
                    'subject_id' => $subjectId,
                    'subject_code' => $subjects[$subjectId]->code ?? ('#' . $subjectId),
                    'subject_title' => $subjects[$subjectId]->title ?? '',
                    'ca' => (string) $parentMark,
                ];

                // What the repair will bring in alongside the mark, counted
                // only for the courses it will actually touch.
                $parentDays = $parentAttendance[$parent->id . ':' . $subjectId] ?? 0;
                $sittingDays = $sittingAttendance[$sitting->id . ':' . $subjectId] ?? 0;

                if ($parentDays > $sittingDays) {
                    $attendanceMissing += $parentDays - $sittingDays;
                }

                if (!$sittingMarkings->has($sitting->id . ':' . $subjectId)) {
                    $markingMissing++;
                }
            }

            if ($missing === []) {
                continue;
            }

            $out->push([
                'enrolment' => $sitting,
                'resit_semester' => $resitSemesters[$sitting->semester_id] ?? null,
                'parent_enrolment' => $parent,
                'missing' => $missing,
                'attendance_missing' => $attendanceMissing,
                'marking_missing' => $markingMissing,
            ]);
        }

        return $out->sortByDesc(fn ($row) => count($row['missing']))->values();
    }

    /**
     * Carry the inheritance over for one sitting.
     *
     * Delegates to the same call the enrolment routes make, so a sitting
     * repaired here is indistinguishable from one raised correctly.
     *
     * Takes a row from affected() rather than an enrolment, and touches ONLY
     * the courses that row reports as missing. Passing every course the
     * sitting carries would also re-copy attendance for courses that already
     * hold their CA — which is the drift this detector deliberately does not
     * report, and would make the repair do more than the screen offered.
     *
     * @param  array{enrolment: StudentEnroll, missing: list<array{subject_id: int}>}  $row
     * @return array{repaired: int, attendance: int, marking: int}
     */
    public function repair(array $row): array
    {
        $sitting = $row['enrolment'];
        $resitSemester = Semester::find($sitting->semester_id);

        if (!$resitSemester || !$resitSemester->is_resit) {
            return ['repaired' => 0, 'attendance' => 0, 'marking' => 0];
        }

        $courseIds = collect($row['missing'])
            ->pluck('subject_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($courseIds === []) {
            return ['repaired' => 0, 'attendance' => 0, 'marking' => 0];
        }

        $before = $this->countsFor($sitting->id);
        $marksBefore = $this->caMarks([$sitting->id]);

        DB::transaction(function () use ($sitting, $resitSemester, $courseIds) {
            $this->progression->inheritParentSemesterData($sitting, $resitSemester, $courseIds);
        });

        $after = $this->countsFor($sitting->id);
        $marksAfter = $this->caMarks([$sitting->id]);

        // Count marks that arrived, not rows that were created. Where the
        // sitting already held an empty CA row — the one CA exam attendance
        // leaves behind — the mark is written into it and the row count does
        // not move, so counting rows would report a successful repair as a
        // failure and send the registrar back to a button that had in fact
        // just worked.
        $carried = 0;

        foreach ($courseIds as $subjectId) {
            $key = $sitting->id . ':' . $subjectId;

            if ($marksBefore->get($key) === null && $marksAfter->get($key) !== null) {
                $carried++;
            }
        }

        return [
            'repaired' => $carried,
            'attendance' => $after['attendance'] - $before['attendance'],
            'marking' => $after['marking'] - $before['marking'],
        ];
    }

    /**
     * The enrolments the sittings inherit from, in one query.
     *
     * @return Collection<int, StudentEnroll>
     */
    protected function parentsFor(Collection $sittings, Collection $resitSemesters): Collection
    {
        $parentSemesterIds = $resitSemesters->pluck('parent_semester_id')->filter()->unique()->values();

        if ($parentSemesterIds->isEmpty()) {
            return collect();
        }

        return StudentEnroll::whereIn('semester_id', $parentSemesterIds)
            ->whereIn('student_id', $sittings->pluck('student_id')->unique())
            ->whereIn('program_id', $sittings->pluck('program_id')->unique())
            // A superseded row holds the same marks but belongs to a programme
            // the student has left; prefer a live one when both exist.
            ->orderByRaw('CASE WHEN status = 0 THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->get();
    }

    protected function isParentOf(StudentEnroll $candidate, StudentEnroll $sitting, Collection $resitSemesters): bool
    {
        $resit = $resitSemesters[$sitting->semester_id] ?? null;

        return $resit
            && (int) $candidate->student_id === (int) $sitting->student_id
            && (int) $candidate->program_id === (int) $sitting->program_id
            && (int) $candidate->semester_id === (int) $resit->parent_semester_id;
    }

    /**
     * CA marks keyed "enrolment:subject", for a set of enrolments.
     */
    protected function caMarks(array $enrolmentIds): Collection
    {
        if ($enrolmentIds === []) {
            return collect();
        }

        return DB::table('exams')
            ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
            ->whereIn('exams.student_enroll_id', $enrolmentIds)
            ->where('exam_types.is_final', 0)
            ->get(['exams.student_enroll_id', 'exams.subject_id', 'exams.achieve_marks'])
            ->mapWithKeys(fn ($r) => [
                $r->student_enroll_id . ':' . $r->subject_id => $r->achieve_marks,
            ]);
    }

    /**
     * @return array{ca: int, attendance: int, marking: int}
     */
    public function countsFor(int $enrolmentId): array
    {
        return [
            'ca' => DB::table('exams')
                ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
                ->where('exams.student_enroll_id', $enrolmentId)
                ->where('exam_types.is_final', 0)
                ->count(),
            'attendance' => DB::table('student_attendances')
                ->where('student_enroll_id', $enrolmentId)->count(),
            'marking' => DB::table('subject_markings')
                ->where('student_enroll_id', $enrolmentId)->count(),
        ];
    }
}
