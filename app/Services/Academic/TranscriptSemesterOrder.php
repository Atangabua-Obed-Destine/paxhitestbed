<?php

namespace App\Services\Academic;

use App\Models\Semester;
use App\Models\Session;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * The order a transcript prints its semester sections in.
 *
 * Every screen that shows a transcript built its list of semesters by walking
 * the student's enrolments in whatever order the relation handed them back —
 * which is the order they were CREATED. For years that was right by accident:
 * enrolments were raised as the student moved through the programme, so
 * creation order and academic order were the same sequence.
 *
 * Raising an enrolment BACKWARDS breaks the coincidence. A first-semester resit
 * raised in October, once the second semester and its own resit are already on
 * file, carries the highest id of the four and printed last, so the transcript
 * read FIRST, SECOND, SECOND RESIT, FIRST RESIT. The ordering was never right;
 * nothing had made it wrong before.
 *
 * WHAT THE ORDER IS. Session, then academic year, then first semester before
 * second, with a resit directly behind the semester it shadows rather than on
 * its own footing: a resit is the same course sat again, so it belongs beside
 * the fail it answers and nowhere else.
 *
 * A RESIT TAKES ITS PARENT'S PLACE, the parent's session included, whenever
 * that enrolment is on the same transcript. Ordering a resit by its own session
 * would part it from its parent again the first time a school raises a Year 1
 * resit during the following academic year — the same bug in a new disguise.
 * Where a year has been repeated and the parent semester appears in more than
 * one session, the resit joins the parent sharing its own session if there is
 * one, and the earliest otherwise.
 *
 * Nothing here reads marks or writes anything: it decides sequence only. The
 * cumulative GPA on the transcript is summed over every mark in a pass of its
 * own and so does not move. The GPA TREND does move — it accumulates as it
 * walks this list — which is exactly why it has to be ordered by the same rule
 * as the page it sits beside.
 */
class TranscriptSemesterOrder
{
    /**
     * One student's enrolments, in the order a transcript should print them.
     *
     * Costs no query where the caller has eager-loaded `semester` and `session`
     * (every transcript screen does) and the semesters a resit shadows are
     * themselves on the transcript (they are, bar a resit raised for a course
     * whose original enrolment has gone). At worst it is two.
     *
     * @param  iterable  $enrolments
     * @return Collection
     */
    public function sort($enrolments): Collection
    {
        $enrolments = $enrolments instanceof Collection ? $enrolments : collect($enrolments);

        if ($enrolments->count() < 2) {
            return $enrolments->values();
        }

        $semesters = $this->semestersFor($enrolments);
        $sessionKeys = $this->sessionKeysFor($enrolments);
        $anchors = $this->anchorSessionsFor($enrolments, $semesters, $sessionKeys);

        // PHP's sorts have been stable since 8.0, so enrolments landing on the
        // same key keep the order they arrived in instead of shuffling between
        // one render of the transcript and the next.
        return $enrolments
            ->sortBy(
                fn ($enrolment) => $this->keyFor($enrolment, $semesters, $sessionKeys, $anchors),
                SORT_STRING
            )
            ->values();
    }

    /**
     * Where one enrolment sits in the sequence.
     */
    protected function keyFor($enrolment, Collection $semesters, array $sessionKeys, array $anchors): string
    {
        $semesterId = (int) $enrolment->semester_id;
        $semester = $semesters->get($semesterId);

        if (!$semester) {
            // An enrolment whose semester cannot be read cannot be placed. It
            // sorts LAST rather than first, so a broken row never heads a
            // transcript.
            return '9999-99-99|9999|9999|9';
        }

        $isResit = (bool) $semester->is_resit;

        // A parentless resit still sorts behind a regular semester of the same
        // year and type; it simply has nothing better to anchor to.
        $anchorId = ($isResit && $semester->parent_semester_id)
            ? (int) $semester->parent_semester_id
            : $semesterId;

        $anchor = $semesters->get($anchorId) ?? $semester;
        $own = $sessionKeys[(int) $enrolment->session_id] ?? '9999-99-99';

        return sprintf(
            '%s|%04d|%04d|%d',
            $isResit ? $this->anchorSession($anchors[$anchorId] ?? [], $own) : $own,
            (int) $anchor->year,
            (int) $anchor->semester_type,
            $isResit ? 1 : 0
        );
    }

    /**
     * The session a resit borrows from the semester it shadows.
     *
     * @param  list<string>  $parentSessions  session keys the parent is enrolled in
     * @param  string  $own  the resit's own session key
     */
    protected function anchorSession(array $parentSessions, string $own): string
    {
        if ($parentSessions === []) {
            return $own;
        }

        return in_array($own, $parentSessions, true) ? $own : $parentSessions[0];
    }

    /**
     * Every semester these enrolments sit in, and every semester those name as
     * a parent, keyed by id.
     */
    protected function semestersFor(Collection $enrolments): Collection
    {
        $byId = collect();

        foreach ($enrolments as $enrolment) {
            if ($enrolment->relationLoaded('semester') && $enrolment->semester) {
                $byId->put((int) $enrolment->semester->id, $enrolment->semester);
            }
        }

        $this->fill($byId, $enrolments->pluck('semester_id'));

        // The parents are only knowable once the semesters themselves are in
        // hand, so this cannot be folded into the call above.
        $this->fill($byId, $byId->pluck('parent_semester_id'));

        return $byId;
    }

    /**
     * Read whichever of these semesters are not already held.
     */
    protected function fill(Collection $byId, $ids): void
    {
        $missing = collect($ids)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $byId->has($id))
            ->values();

        if ($missing->isEmpty()) {
            return;
        }

        foreach (Semester::whereIn('id', $missing)->get() as $semester) {
            $byId->put((int) $semester->id, $semester);
        }
    }

    /**
     * A sortable key per session id.
     *
     * @return array<int, string>
     */
    protected function sessionKeysFor(Collection $enrolments): array
    {
        $keys = [];
        $missing = [];

        foreach ($enrolments as $enrolment) {
            $id = (int) $enrolment->session_id;

            if (!$id || isset($keys[$id])) {
                continue;
            }

            if ($enrolment->relationLoaded('session') && $enrolment->session) {
                $keys[$id] = $this->sessionKey($enrolment->session);
                continue;
            }

            $missing[$id] = $id;
        }

        if ($missing !== []) {
            foreach (Session::whereIn('id', $missing)->get() as $session) {
                $keys[(int) $session->id] = $this->sessionKey($session);
            }
        }

        return $keys;
    }

    /**
     * Chronology, by the date the year opens.
     *
     * A session with no start date falls back to its title, which on every
     * installation here reads "2025/2026" and sorts the same way; dated
     * sessions still come first, so one missing date cannot reshuffle the rest.
     */
    protected function sessionKey($session): string
    {
        $start = $session->start_date;

        if ($start instanceof DateTimeInterface) {
            return $start->format('Y-m-d');
        }

        if (is_string($start) && $start !== '') {
            return substr($start, 0, 10);
        }

        return '9999-99-99 ' . (string) $session->title;
    }

    /**
     * Which sessions each shadowable semester is enrolled in on this
     * transcript, so a resit can borrow its parent's place.
     *
     * @return array<int, list<string>>
     */
    protected function anchorSessionsFor(Collection $enrolments, Collection $semesters, array $sessionKeys): array
    {
        $anchors = [];

        foreach ($enrolments as $enrolment) {
            $semesterId = (int) $enrolment->semester_id;
            $semester = $semesters->get($semesterId);

            // Only semesters a resit can shadow, and only from the enrolment
            // that actually sits in one.
            if (!$semester || $semester->is_resit) {
                continue;
            }

            $key = $sessionKeys[(int) $enrolment->session_id] ?? null;

            if ($key !== null && !in_array($key, $anchors[$semesterId] ?? [], true)) {
                $anchors[$semesterId][] = $key;
            }
        }

        return $anchors;
    }
}
