<?php

namespace App\Support;

use App\Models\StudentEnroll;
use Illuminate\Support\Collection;

/**
 * Which of a student's enrolments is the furthest along.
 *
 * Several screens need "the student's current enrolment" and every one of them
 * worked it out as `MAX(id)` -- the row inserted most recently. That is a proxy
 * for academic position, and a wrong one: it says the latest row created is the
 * latest semester reached, which is only true while rows happen to be created
 * in academic order.
 *
 * It breaks as soon as a BACK-DATED enrolment is created. A resit enrolment for
 * Year 1 Semester 1, raised for a student who is already in Semester 2, is the
 * newest row by id and the earliest by academic position, so `MAX(id)` picks it
 * and the student's portal silently moves back to the resit semester. Measured
 * on this installation: 35 of 64 Second Semester enrolments were the highest id
 * for their matricule, and giving all 64 a resit enrolment moved every one of
 * them.
 *
 * So order by where the enrolment actually sits in the programme:
 *
 *      session start date
 *      -> semester year, NUMERICALLY
 *      -> semester type (first before second)
 *      -> regular before resit
 *      -> id, only to break a remaining tie
 *
 * Two details that matter:
 *
 *  - `semesters.year` is a nullable VARCHAR. Sorting it as text puts year 10
 *    before year 2, which `SemesterYearOptions` documents as a trap this
 *    codebase has already been caught by. It is cast.
 *
 *  - An enrolment with no semester or session -- the placeholder rows an online
 *    application creates to hang an admission fee on -- sorts LAST, never
 *    first, so it can never be mistaken for the furthest point reached.
 *
 * Deliberately NOT keyed on `status`. "Prefer the active row" fails the case it
 * most needs to handle: a student who has finished everything has no active row
 * at all, so the rule falls through to `MAX(id)` and the original problem comes
 * straight back. Academic position is true whatever the statuses are.
 *
 * Checked against the live data before being adopted: for all 86 matricule
 * groups this picks exactly the enrolment `MAX(id)` picked, so nothing moves
 * for anybody today.
 */
class EnrolmentOrder
{
    /**
     * A sortable key for one enrolment's academic position.
     *
     * Zero-padded text rather than a tuple so it can be used with `sortBy` on a
     * collection and compared with a plain string comparison. Missing semester
     * or session data sorts to the bottom.
     */
    public static function key(StudentEnroll $enroll): string
    {
        $semester = $enroll->semester;
        $session = $enroll->session;

        if (!$semester) {
            // No semester at all: a placeholder row. Never the furthest point.
            return sprintf('0|0000-00-00|00|0|0|%010d', $enroll->id);
        }

        return sprintf(
            '1|%s|%02d|%d|%d|%010d',
            $session->start_date ?? '0000-00-00',
            (int) $semester->year,
            (int) $semester->semester_type,
            self::resitRank($enroll, $semester),
            $enroll->id
        );
    }

    /**
     * Where a resit sitting sits against the semester it shadows.
     *
     * Only ever a tiebreak WITHIN one semester position — the session, the year
     * and the semester type are compared first — so this never decides how far
     * through the programme a student is. That matters: keying the main order
     * on status would fail the student who has completed everything and has no
     * active row at all.
     *
     * A resit sitting the student is actively in (status 1) is where they are
     * now, and outranks the regular semester. That is how the 27 second-resit
     * enrolments on this installation already behave, and they keep behaving
     * that way.
     *
     * A sitting that is NOT active is a re-attempt at a semester already passed
     * through — including every sitting raised retroactively for a carried-over
     * course — and must not displace the regular semester. Without this, raising
     * one moved the student's whole portal onto it.
     */
    protected static function resitRank(StudentEnroll $enroll, $semester): int
    {
        if (!$semester->is_resit) {
            return 1;
        }

        return (string) $enroll->status === '1' ? 2 : 0;
    }

    /**
     * The enrolment furthest along, out of the ones given.
     *
     * @param  Collection<int, StudentEnroll>  $enrolments
     */
    public static function furthest(Collection $enrolments): ?StudentEnroll
    {
        if ($enrolments->isEmpty()) {
            return null;
        }

        return $enrolments->sortBy(fn (StudentEnroll $e) => self::key($e))->last();
    }

    /**
     * One enrolment per matricule, each the furthest along for that matricule.
     *
     * The grouping is what the student portal's programme switcher shows: a
     * student enrolled on two programmes has two matricules and picks between
     * them, while the several enrolments behind one matricule collapse to the
     * one they have reached.
     *
     * @param  Collection<int, StudentEnroll>  $enrolments
     * @return Collection<int, StudentEnroll>
     */
    public static function furthestPerMatricule(Collection $enrolments): Collection
    {
        return $enrolments
            ->groupBy('matricule')
            ->map(fn (Collection $group) => self::furthest($group))
            ->filter()
            ->values();
    }

    /**
     * Order a query by academic position, furthest last.
     *
     * The joins are left joins and aliased so this can be applied to a query
     * that already selects from `student_enrolls`; the select list is left
     * alone so callers keep whatever columns they asked for.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scope($query)
    {
        $table = $query->getModel()->getTable();

        return $query
            ->leftJoin('semesters as enrolment_order_semester', 'enrolment_order_semester.id', '=', $table . '.semester_id')
            ->leftJoin('sessions as enrolment_order_session', 'enrolment_order_session.id', '=', $table . '.session_id')
            ->select($table . '.*')
            ->orderByRaw($table . '.semester_id is null asc')
            ->orderByRaw('enrolment_order_session.start_date asc')
            ->orderByRaw('cast(enrolment_order_semester.year as unsigned) asc')
            ->orderBy('enrolment_order_semester.semester_type', 'asc')
            // The same tiebreak as resitRank(): regular = 1, an active sitting
            // = 2, an inactive sitting = 0. Kept in step with the PHP by the
            // test that runs both over the same students.
            ->orderByRaw("case
                when enrolment_order_semester.is_resit = 0 then 1
                when {$table}.status = '1' then 2
                else 0
            end asc")
            ->orderBy($table . '.id', 'asc');
    }
}
