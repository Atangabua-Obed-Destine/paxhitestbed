<?php

namespace App\Services\Academic;

use App\Models\CourseGroup;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * The one answer to "is this the same course?" for everything that teaches:
 * the timetable clash check, joint classes, class sessions and clock-in, the
 * lecturer's timetable, exam timetables and the shared mark sheet.
 *
 * Two courses are the same course when they are the same record or sit in
 * the same course group. A course outside any group is only ever itself, so
 * nothing changes for courses nobody has grouped.
 *
 * Rules a group keeps (check() enforces them; the screen and the import both
 * go through it):
 *   - a course is in at most one group (one column on subjects);
 *   - no programme takes two courses of one group, in any semester: two
 *     courses one programme takes are two courses to it, and grouping them
 *     would make a clash look like a joint class.
 */
class CourseGroups
{
    /** @var array<int, int|null> subject id => group id, for this request */
    private static array $groupOf = [];

    /** @var array<int, int[]> group id => member subject ids, for this request */
    private static array $members = [];

    public static function forget(): void
    {
        self::$groupOf = [];
        self::$members = [];
    }

    /** The group of a course, or null. */
    public static function groupOf(?int $subjectId): ?int
    {
        if (!$subjectId) {
            return null;
        }
        if (!array_key_exists($subjectId, self::$groupOf)) {
            self::load([$subjectId]);
        }

        return self::$groupOf[$subjectId] ?? null;
    }

    /**
     * Every course that is the same course as this one, itself included.
     * Use it wherever a query used where('subject_id', $id) to find the other
     * programmes' sessions or classes of one joint lecture.
     *
     * @return int[]
     */
    public static function memberIds(?int $subjectId): array
    {
        if (!$subjectId) {
            return [];
        }
        $group = self::groupOf($subjectId);
        if (!$group) {
            return [(int) $subjectId];
        }
        if (!isset(self::$members[$group])) {
            self::$members[$group] = DB::table('subjects')->where('course_group_id', $group)->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach (self::$members[$group] as $id) {
                self::$groupOf[$id] = $group;
            }
        }

        return self::$members[$group] ?: [(int) $subjectId];
    }

    /** A key that is equal for two courses exactly when they are the same course. */
    public static function key(?int $subjectId): string
    {
        $group = self::groupOf($subjectId);

        return $group ? 'g' . $group : 's' . (int) $subjectId;
    }

    public static function same(?int $a, ?int $b): bool
    {
        return $a && $b && self::key($a) === self::key($b);
    }

    /**
     * Read the groups of many courses in one query, so a screen that compares
     * a whole timetable does not ask once per class.
     *
     * @param int[] $subjectIds
     */
    public static function load(array $subjectIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $subjectIds))));
        $missing = array_values(array_diff($ids, array_keys(self::$groupOf)));
        if (!$missing) {
            return;
        }
        $rows = DB::table('subjects')->whereIn('id', $missing)->pluck('course_group_id', 'id');
        foreach ($missing as $id) {
            $g = $rows[$id] ?? null;
            self::$groupOf[$id] = $g ? (int) $g : null;
        }
    }

    /**
     * Programmes that take each course: linked on the course itself or
     * enrolled for it in some semester.
     *
     * @param int[] $subjectIds
     * @return array<int, int[]> subject id => programme ids
     */
    public static function programmesOf(array $subjectIds): array
    {
        $out = array_fill_keys(array_map('intval', $subjectIds), []);
        if (!$subjectIds) {
            return $out;
        }
        $pairs = DB::table('program_subject')->whereIn('subject_id', $subjectIds)->get(['subject_id', 'program_id'])
            ->concat(DB::table('enroll_subject_subject as es')->join('enroll_subjects as e', 'e.id', 'es.enroll_subject_id')
                ->whereIn('es.subject_id', $subjectIds)->get(['es.subject_id', 'e.program_id']));
        foreach ($pairs as $p) {
            $out[(int) $p->subject_id][] = (int) $p->program_id;
        }

        return array_map(fn ($ps) => array_values(array_unique($ps)), $out);
    }

    /**
     * What stops these courses from forming a group (empty = they may).
     *
     * @param int[] $subjectIds the courses the group would hold
     * @param int|null $groupId the group being edited, if any
     * @return string[] plain-language problems
     */
    public static function check(array $subjectIds, ?int $groupId = null): array
    {
        $subjectIds = array_values(array_unique(array_filter(array_map('intval', $subjectIds))));
        $problems = [];
        if (count($subjectIds) < 2) {
            $problems[] = __('A course group needs at least two courses.');
        }

        $courses = Subject::whereIn('id', $subjectIds)->with('courseGroup')->get()->keyBy('id');
        foreach ($courses as $c) {
            if ($c->course_group_id && (int) $c->course_group_id !== (int) $groupId) {
                $problems[] = __(':code is already in the group ":group". Take it out of that group first.', [
                    'code' => $c->code, 'group' => $c->courseGroup->title ?? '#' . $c->course_group_id,
                ]);
            }
        }

        $byProgramme = [];
        foreach (self::programmesOf($subjectIds) as $sid => $programmes) {
            foreach ($programmes as $p) {
                $byProgramme[$p][] = $sid;
            }
        }
        $names = DB::table('programs')->whereIn('id', array_keys($byProgramme))->pluck('title', 'id');
        foreach ($byProgramme as $p => $sids) {
            if (count($sids) > 1) {
                $problems[] = __(':programme takes both :codes. A programme can take only one course of a group; these are different courses to it.', [
                    'programme' => $names[$p] ?? '#' . $p,
                    'codes' => collect($sids)->map(fn ($s) => $courses[$s]->code ?? '#' . $s)->implode(' and '),
                ]);
            }
        }

        return $problems;
    }

    /**
     * Each enrolment's own course among a course and its group: the code the
     * student is registered for (AGE118H for an agric student, MGT1101H for an
     * accountancy one). Falls back to the course asked about for a student
     * registered for none of them.
     *
     * @param int[] $enrollIds
     * @return array<int, int> enrolment id => course id
     */
    public static function ownCourses(?int $subjectId, array $enrollIds): array
    {
        $enrollIds = array_values(array_unique(array_map('intval', $enrollIds)));
        $out = array_fill_keys($enrollIds, (int) $subjectId);
        $members = self::memberIds($subjectId);
        if (count($members) < 2 || !$enrollIds) {
            return $out;
        }
        foreach (array_chunk($enrollIds, 1000) as $chunk) {
            $rows = DB::table('student_enroll_subject')->whereIn('student_enroll_id', $chunk)->whereIn('subject_id', $members)
                ->get(['student_enroll_id', 'subject_id']);
            foreach ($rows as $r) {
                $out[(int) $r->student_enroll_id] = (int) $r->subject_id;
            }
        }

        return $out;
    }

    /**
     * The programmes that take a course or any course of its group, as a
     * Program query (active programmes only), for the "all programmes taking
     * this course" modes of the attendance, marking and exam screens.
     */
    public static function programmesQuery(?int $subjectId)
    {
        $members = self::memberIds($subjectId);

        return \App\Models\Program::where('programs.status', '1')
            ->whereHas('subjects', fn ($q) => $q->whereIn('subjects.id', $members))
            ->orderBy('programs.title');
    }

    /**
     * The course of a group that a programme takes, or null when the programme
     * takes none of them.
     */
    public static function courseForProgramme(int $groupId, int $programId): ?Subject
    {
        $ids = DB::table('subjects')->where('course_group_id', $groupId)->pluck('id')->all();
        foreach (self::programmesOf($ids) as $sid => $programmes) {
            if (in_array($programId, $programmes, true)) {
                return Subject::find($sid);
            }
        }

        return null;
    }

    /**
     * Save a group's members after check() found nothing.
     *
     * @param int[] $subjectIds
     */
    public static function setMembers(CourseGroup $group, array $subjectIds): void
    {
        DB::transaction(function () use ($group, $subjectIds) {
            Subject::where('course_group_id', $group->id)->whereNotIn('id', $subjectIds)->update(['course_group_id' => null]);
            Subject::whereIn('id', $subjectIds)->update(['course_group_id' => $group->id]);
        });
        self::forget();
    }
}
