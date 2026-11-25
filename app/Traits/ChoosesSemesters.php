<?php

namespace App\Traits;

use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * A semester type (First Semester, ...) and any number of its semesters.
 *
 * The senate reviews a session one semester type at a time: "First Semester"
 * of 2026/2027 is FIRST SEMESTER - Year 1 and FIRST SEMESTER - Year 2 at once.
 * Figures stay per semester - a screen calls its single-semester builder once
 * for each semester chosen here and shows one block per semester.
 *
 * Request contract (backward compatible):
 *   semester_type  "FIRST SEMESTER"; empty or '0' means none
 *   semester       a single id (old links) or semester[] ids
 * semester[] ids win; else every semester of the type in the pool; else none.
 */
trait ChoosesSemesters
{
    /**
     * @param  Collection|null  $pool  the semesters this screen offers (default: all active)
     * @return array{type: string, ids: int[], semesters: Collection, pool: Collection, types: Collection}
     */
    protected function chooseSemesters(Request $request, ?Collection $pool = null): array
    {
        $pool = ($pool ?? Semester::where('status', 1)->get())
            ->sortBy([['year', 'asc'], ['id', 'asc']])->values();

        $type = trim((string) $request->input('semester_type', ''));
        $type = ($type === '' || $type === '0') ? '0' : strtoupper($type);

        $picked = collect((array) $request->input('semester'))
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)->unique()->values();

        if ($picked->isNotEmpty()) {
            $semesters = Semester::whereIn('id', $picked)->get();
        } elseif ($type !== '0') {
            $semesters = $pool->filter(fn ($s) => $s->type_name === $type);
        } else {
            $semesters = collect();
        }

        $semesters = $semesters->sortBy([['year', 'asc'], ['id', 'asc']])->values();

        // An old single-semester link carries no type: show the one its
        // semesters share, so the list opens on the right type.
        if ($type === '0' && $semesters->isNotEmpty() && $semesters->pluck('type_name')->unique()->count() === 1) {
            $type = $semesters->first()->type_name;
        }

        return [
            'type' => $type,
            'ids' => $semesters->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'semesters' => $semesters,
            'pool' => $pool,
            'types' => $pool->sortBy('id')->map(fn ($s) => $s->type_name)->unique()->values(),
        ];
    }

    /**
     * The view variables the shared filter (common.inc.semester_type_field)
     * reads, plus selected_semester (the first chosen id) for views and links
     * that still speak of one semester.
     */
    protected function semesterFilterData(array $choice): array
    {
        return [
            'semester_types' => $choice['types'],
            'semester_options' => $choice['pool'],
            'selected_semester_type' => $choice['type'],
            'selected_semesters' => $choice['ids'],
            'selected_semester' => $choice['ids'][0] ?? '0',
            'chosen_semesters' => $choice['semesters'],
        ];
    }
}
