<?php

namespace App\Services\Academic;

use App\Models\ClassRoutine;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Session;
use App\Models\TimetableBreak;
use Illuminate\Support\Collection;

/**
 * The timetable as the school writes it: one sheet per specialty — the
 * programmes that share one week are printed together, as on the Word
 * timetable ("ACCOUNTING / BANKING AND FINANCE / HUMAN RESOURCE MANAGEMENT /
 * MARKETING") — with the days down the side, the time slots across the top,
 * and the school's breaks laid in (the midday break as a column down every
 * day, Friday's club activities in its own cell).
 *
 * Programmes share a sheet when their weeks are the same: the same days,
 * times, lecturers and courses, where a course and the other courses of its
 * course group count as the same (AGE118H and MGT1101H).
 */
class TimetableDocument
{
    /** Monday first, as the school's timetables read. class_routines.day: 1 = Saturday … 7 = Friday. */
    public const DAY_ORDER = [3 => 'Monday', 4 => 'Tuesday', 5 => 'Wednesday', 6 => 'Thursday', 7 => 'Friday', 1 => 'Saturday', 2 => 'Sunday'];

    /**
     * @return array<int, array> one sheet per specialty
     */
    public function sheets(Session $session, Semester $semester, ?int $programId = null, ?int $sectionId = null): array
    {
        $rows = ClassRoutine::with(['subject', 'teacher', 'room', 'program'])
            ->where('status', 1)->where('session_id', $session->id)->where('semester_id', $semester->id)
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->get();
        CourseGroups::load($rows->pluck('subject_id')->all());

        // Programmes with the same week share a sheet.
        $groups = $rows->groupBy('program_id')->map(function (Collection $own, $pid) {
            $signature = $own->map(fn ($r) => implode('|', [$r->day, substr($r->start_time, 0, 5), substr($r->end_time, 0, 5),
                CourseGroups::key((int) $r->subject_id), $r->teacher_id]))->sort()->implode(';');

            return ['program_id' => (int) $pid, 'signature' => md5($signature)];
        })->groupBy('signature');

        $sheets = [];
        foreach ($groups as $members) {
            $ids = $members->pluck('program_id')->all();
            if ($programId && !in_array($programId, $ids, true)) {
                continue;
            }
            $programmes = Program::whereIn('id', $ids)->orderBy('title')->get();
            $sheets[] = $this->sheet($session, $semester, $programmes, $rows->whereIn('program_id', $ids));
        }
        usort($sheets, fn ($a, $b) => strcmp($a['order'], $b['order']));

        return $sheets;
    }

    private function sheet(Session $session, Semester $semester, Collection $programmes, Collection $rows): array
    {
        // One class per (day, start): the programmes' rows of one lecture.
        $lectures = $rows->groupBy(fn ($r) => $r->day . '|' . substr($r->start_time, 0, 5) . '|' . CourseGroups::key((int) $r->subject_id))
            ->map(fn (Collection $g) => (object) [
                'day' => (int) $g->first()->day,
                'start' => substr($g->first()->start_time, 0, 5),
                'end' => substr($g->first()->end_time, 0, 5),
                'codes' => $g->map(fn ($r) => $r->subject->code ?? '')->filter()->unique()->sort()->values()->all(),
                'title' => $g->first()->subject->title ?? '',
                'lecturer' => trim(($g->first()->teacher->first_name ?? '') . ' ' . ($g->first()->teacher->last_name ?? '')),
                'room' => $g->map(fn ($r) => $r->room->title ?? null)->filter()->unique()->implode(', '),
            ])->values();

        $days = array_values(array_filter(array_keys(self::DAY_ORDER), fn ($d) => $lectures->contains('day', $d)));
        $breaks = TimetableBreak::where('status', 1)->orderBy('start_time')->get()
            ->filter(fn ($b) => collect($days)->contains(fn ($d) => $b->appliesTo($d)));

        // The columns: every time a class or a break starts or ends.
        $cuts = $lectures->flatMap(fn ($l) => [$l->start, $l->end])
            ->concat($breaks->flatMap(fn ($b) => [substr($b->start_time, 0, 5), substr($b->end_time, 0, 5)]))
            ->unique()->sort()->values()->all();
        $first = $lectures->min('start');
        $last = $lectures->max('end');
        $cuts = array_values(array_filter($cuts, fn ($c) => $c >= $first && $c <= $last));
        $columns = [];
        for ($i = 0; $i < count($cuts) - 1; $i++) {
            $columns[] = ['start' => $cuts[$i], 'end' => $cuts[$i + 1]];
        }
        $col = fn ($time) => array_search($time, array_column($columns, 'start'), true);
        $colEnd = function ($time) use ($columns) {
            foreach ($columns as $i => $c) {
                if ($c['end'] === $time) {
                    return $i;
                }
            }

            return count($columns) - 1;
        };

        // A break that falls on every day is one cell down the whole week.
        $tall = $breaks->filter(fn ($b) => collect($days)->every(fn ($d) => $b->appliesTo($d)));

        $grid = [];
        foreach ($days as $rowIndex => $day) {
            $cells = [];
            $i = 0;
            while ($i < count($columns)) {
                $c = $columns[$i];
                $tallBreak = $tall->first(fn ($b) => substr($b->start_time, 0, 5) === $c['start']);
                if ($tallBreak) {
                    $span = $colEnd(substr($tallBreak->end_time, 0, 5)) - $i + 1;
                    if ($rowIndex === 0) {
                        $cells[] = ['type' => 'tall-break', 'title' => $tallBreak->title, 'colspan' => $span, 'rowspan' => count($days)];
                    }
                    $i += $span;
                    continue;
                }
                $here = $lectures->filter(fn ($l) => $l->day === $day && $l->start === $c['start']);
                if ($here->isNotEmpty()) {
                    $span = max(1, $colEnd($here->max('end')) - $i + 1);
                    $cells[] = ['type' => 'class', 'items' => $here->values()->all(), 'colspan' => $span];
                    $i += $span;
                    continue;
                }
                $dayBreak = $breaks->first(fn ($b) => $b->appliesTo($day) && substr($b->start_time, 0, 5) === $c['start'] && !$tall->contains('id', $b->id));
                if ($dayBreak) {
                    $span = max(1, $colEnd(substr($dayBreak->end_time, 0, 5)) - $i + 1);
                    $cells[] = ['type' => 'break', 'title' => $dayBreak->title, 'colspan' => $span];
                    $i += $span;
                    continue;
                }
                $cells[] = ['type' => 'empty', 'colspan' => 1];
                $i++;
            }
            $grid[] = ['day' => self::DAY_ORDER[$day], 'cells' => $cells];
        }

        $names = $programmes->map(fn ($p) => trim(preg_replace('/^HND\s*-\s*/i', '', $p->title)));
        $isHnd = $programmes->every(fn ($p) => stripos($p->title, 'HND') === 0);
        $year = (int) ($semester->year ?? 0);
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'][$year] ?? (string) $year;

        return [
            'order' => sprintf('%d-%s', $year, $names->first()),
            'programme_ids' => $programmes->pluck('id')->all(),
            'heading' => 'TIME TABLE YEAR ' . $roman,
            'programme_line' => $isHnd ? 'HIGHER NATIONAL DIPLOMA (HND) PROGRAMME' : null,
            'semester_line' => strtoupper(trim(explode(' - ', (string) $semester->title)[0])) . ' ' . $session->title . ' ACADEMIC YEAR',
            'specialty' => strtoupper($names->implode(' / ')),
            'columns' => $columns,
            'rows' => $grid,
            'rooms' => $lectures->pluck('room')->filter()->unique()->values()->all(),
        ];
    }
}
