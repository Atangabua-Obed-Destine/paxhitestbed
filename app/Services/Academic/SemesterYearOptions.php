<?php

namespace App\Services\Academic;

use App\Models\Semester;
use Illuminate\Support\Collection;

/**
 * The semesters a picker should offer, grouped by study year.
 *
 * A semester dropdown that lists every semester in the school is unusable once
 * a programme carries a full ladder plus resit twins. The fix is to choose a
 * year first, which needs the semesters grouped as `year => [{id, title}]`.
 *
 * That grouping was written out by hand in nine controllers. The copies agreed,
 * but each carried the same two faults, so fixing either meant finding all nine:
 *
 *   - `year` is a nullable VARCHAR, so `orderBy('year')` and `sortKeys()` sort
 *     lexically and put year 10 before year 2. The admin form caps year at 8,
 *     but a cap in a form is not a constraint in a database.
 *   - semesters with no year were dropped silently. While the list was flat they
 *     still showed up; once a year has to be chosen first they become
 *     unreachable, which is why orphans() exists rather than a wider filter.
 *
 * A note on the key type, because it is a trap: PHP coerces a numeric string
 * array key to an integer, so these come back as int keys however they are
 * written, while `json_encode` turns them back into strings for the browser.
 * The column itself is a VARCHAR. Compare as strings on both sides and the
 * difference never bites; use `===` against an integer and it will.
 */
class SemesterYearOptions
{
    /**
     * Group an already-loaded collection.
     *
     * @return array<string, array<int, array{id: int, title: string}>>
     */
    public static function fromCollection(Collection $semesters): array
    {
        $grouped = [];

        foreach ($semesters as $semester) {
            if (is_null($semester->year) || $semester->year === '') {
                continue;
            }

            $grouped[(string) $semester->year][] = [
                'id' => $semester->id,
                'title' => $semester->title,
            ];
        }

        // Numerically, so year 10 follows year 9 rather than year 1.
        uksort($grouped, fn ($a, $b) => (int) $a <=> (int) $b);

        foreach ($grouped as $year => $items) {
            usort($items, fn ($a, $b) => $a['id'] <=> $b['id']);
            $grouped[$year] = $items;
        }

        return $grouped;
    }

    /**
     * The semesters one programme teaches, grouped by year.
     */
    public static function forProgram($programId): array
    {
        return static::fromCollection(static::collectionForProgram($programId));
    }

    /**
     * The collection behind forProgram(), for callers that also need the rows.
     */
    public static function collectionForProgram($programId): Collection
    {
        if (empty($programId)) {
            return collect();
        }

        return Semester::where('status', 1)
            ->with('programs')
            ->whereHas('programs', fn ($query) => $query->where('program_id', $programId))
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Every active semester, grouped by year.
     *
     * For the handful of screens that genuinely have no programme in scope.
     * Note that this still crosses every programme: choosing a year makes the
     * list shorter, not correct.
     */
    public static function all(): array
    {
        return static::fromCollection(
            Semester::where('status', 1)->orderBy('id', 'asc')->get()
        );
    }

    /**
     * Semesters that carry no year, and so belong to no group.
     *
     * A picker that gates on year must offer these somewhere or they cannot be
     * chosen at all.
     */
    public static function orphans(Collection $semesters): Collection
    {
        return $semesters->filter(
            fn ($semester) => is_null($semester->year) || $semester->year === ''
        )->values();
    }

    /**
     * The year keys alone, in the same order.
     *
     * @return array<int, string>
     */
    public static function years(Collection $semesters): array
    {
        return array_keys(static::fromCollection($semesters));
    }
}
