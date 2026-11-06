<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * One teacher's week, shaped for reading rather than storing.
 *
 * A lecture given jointly to several programs is stored once per program,
 * so a teacher with seven lectures can have fifty-odd rows. Read naively
 * that is fifty cards. Here rows that are the same period — same day,
 * start, end, course and room — become one slot that lists the programs
 * it serves.
 *
 * Two periods of the same course that follow each other (08:00–10:00 then
 * 10:00–12:00) are not the same period and are never merged: they stay two
 * slots, marked as a run (1/2, 2/2) so the timetable still shows them as
 * the separate periods they are on paper.
 *
 * Days are numbered as everywhere else in the application: 1 = Saturday
 * through 7 = Friday.
 */
class TeacherTimetable
{
    /** Slots longer than this are almost certainly a mistyped end time. */
    public const LONG_SLOT_MINUTES = 6 * 60;

    /** Muted course colours: [tint background, solid accent]. */
    public const PALETTE = [
        ['#e8f4fd', '#2b8fd6'],
        ['#e9f7ef', '#2e9d5b'],
        ['#fdf2e6', '#d9822b'],
        ['#f1ecfb', '#7b5cc4'],
        ['#e6f6f7', '#1f9aa5'],
        ['#fcecef', '#cc4b65'],
        ['#f3f5e6', '#8a9a2b'],
        ['#eef0f3', '#5b6675'],
    ];

    public Collection $slots;
    public array $days = [];
    public array $courses = [];
    public array $stats = [];
    public int $clashCount = 0;
    public int $longCount = 0;
    public ?int $gridStart = null;   // minutes from midnight, on the hour
    public ?int $gridEnd = null;

    public function __construct(Collection $rows)
    {
        $this->slots = $this->mergeRows($rows);
        $this->assignCourseColours();
        $this->markRuns();
        $this->layOutDays();
        $this->summarise();
    }

    /**
     * Collapse joint-program duplicates into one slot each. The room is not
     * part of the key: one course at one time is one lecture, even where each
     * program's row names its own hall, so the rooms are listed on the slot
     * rather than drawn as several lectures clashing with one another.
     */
    private function mergeRows(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn ($r) => implode('|', [
                $r->day,
                substr((string) $r->start_time, 0, 5),
                substr((string) $r->end_time, 0, 5),
                $r->subject_id,
            ]))
            ->map(function (Collection $group) {
                $first = $group->first();
                $start = self::minutes($first->start_time);
                $end = self::minutes($first->end_time);

                $groups = $group->map(fn ($r) => [
                    'program' => $r->program->shortcode ?? $r->program->title ?? '—',
                    'semester' => $r->semester->title ?? '',
                    'section' => $r->section->title ?? '',
                    'room' => $r->room->title ?? '',
                ])->unique(fn ($g) => implode('|', $g))->sortBy('program')->values()->all();

                $rooms = $group->map(fn ($r) => $r->room->title ?? null)->filter()->unique()->values()->all();

                return (object) [
                    'day' => (int) $first->day,
                    'start' => $start,
                    'end' => $end,
                    'duration' => max(0, $end - $start),
                    'subject_id' => $first->subject_id,
                    'code' => $first->subject->code ?? '—',
                    'title' => $first->subject->title ?? '',
                    'rooms' => $rooms,
                    // "Room 5", or "SCEDT HALL +3" when the programs meet in several halls
                    'room' => $rooms ? $rooms[0].(count($rooms) > 1 ? ' +'.(count($rooms) - 1) : '') : null,
                    'groups' => $groups,
                    'program_ids' => $group->pluck('program_id')->unique()->values()->all(),
                    'colour' => 0,
                    'run_index' => 0,     // 1-based position in a run of consecutive periods
                    'run_length' => 1,
                    'joins_next' => false,
                    'clash' => false,
                    'long' => ($end - $start) > self::LONG_SLOT_MINUTES,
                    'lane' => 0,
                    'lanes' => 1,
                ];
            })
            ->sortBy(fn ($s) => sprintf('%d-%04d-%04d', $s->day, $s->start, $s->end))
            ->values();
    }

    /** One colour per course, stable across the week. */
    private function assignCourseColours(): void
    {
        $order = $this->slots->pluck('subject_id')->unique()->values()->flip();
        foreach ($this->slots as $slot) {
            $slot->colour = $order[$slot->subject_id] % count(self::PALETTE);
        }
    }

    /**
     * Mark consecutive periods of the same course on the same day: the next
     * one starts exactly when this one ends.
     */
    private function markRuns(): void
    {
        foreach ($this->slots->groupBy('day') as $daySlots) {
            // Link each period to the next one of the same course that starts
            // exactly when it ends — whatever else is on the timetable between.
            $next = [];
            $hasPrev = [];
            foreach ($daySlots as $a) {
                foreach ($daySlots as $b) {
                    if ($a !== $b && $a->subject_id === $b->subject_id && $a->end === $b->start && !isset($hasPrev[spl_object_id($b)])) {
                        $next[spl_object_id($a)] = $b;
                        $hasPrev[spl_object_id($b)] = true;
                        break;
                    }
                }
            }

            foreach ($daySlots as $slot) {
                if (isset($hasPrev[spl_object_id($slot)])) {
                    continue; // not the start of a run
                }
                $run = [$slot];
                while (isset($next[spl_object_id(end($run))])) {
                    $run[] = $next[spl_object_id(end($run))];
                }
                $count = count($run);
                foreach ($run as $i => $s) {
                    $s->run_index = $count > 1 ? $i + 1 : 0;
                    $s->run_length = $count;
                    $s->joins_next = $i < $count - 1;
                }
            }
        }
    }

    /**
     * Per day: flag clashes (the teacher in two places at once) and give
     * overlapping slots side-by-side lanes so neither hides the other.
     */
    private function layOutDays(): void
    {
        for ($day = 1; $day <= 7; $day++) {
            $daySlots = $this->slots->where('day', $day)->values();

            // Clusters of slots that overlap one another.
            $cluster = [];
            $clusterEnd = -1;
            $flush = function () use (&$cluster) {
                if (count($cluster) > 1) {
                    $laneEnds = [];
                    foreach ($cluster as $slot) {
                        $lane = 0;
                        while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $slot->start) {
                            $lane++;
                        }
                        $laneEnds[$lane] = $slot->end;
                        $slot->lane = $lane;
                    }
                    foreach ($cluster as $slot) {
                        $slot->lanes = count($laneEnds);
                        $slot->clash = true;
                    }
                }
                $cluster = [];
            };

            foreach ($daySlots as $slot) {
                if ($cluster && $slot->start >= $clusterEnd) {
                    $flush();
                    $clusterEnd = -1;
                }
                $cluster[] = $slot;
                $clusterEnd = max($clusterEnd, $slot->end);
            }
            $flush();

            $this->days[$day] = $daySlots;
        }

        $this->clashCount = $this->slots->where('clash', true)->count();
        $this->longCount = $this->slots->where('long', true)->count();

        if ($this->slots->isNotEmpty()) {
            $this->gridStart = intdiv($this->slots->min('start'), 60) * 60;
            $this->gridEnd = (int) ceil($this->slots->max('end') / 60) * 60;
            if ($this->gridEnd <= $this->gridStart) {
                $this->gridEnd = $this->gridStart + 60;
            }
        }
    }

    private function summarise(): void
    {
        $this->courses = $this->slots
            ->groupBy('subject_id')
            ->map(fn (Collection $s) => (object) [
                'code' => $s->first()->code,
                'title' => $s->first()->title,
                'colour' => $s->first()->colour,
                'periods' => $s->count(),
                'minutes' => $s->sum('duration'),
            ])
            ->sortBy('code')
            ->values()
            ->all();

        $this->stats = [
            'periods' => $this->slots->count(),
            'minutes' => $this->slots->sum('duration'),
            'courses' => count($this->courses),
            'programs' => $this->slots->pluck('program_ids')->flatten()->unique()->count(),
        ];
    }

    public static function minutes($time): int
    {
        [$h, $m] = array_pad(explode(':', (string) $time), 2, 0);

        return ((int) $h) * 60 + (int) $m;
    }

    /** "2h", "1h 30m", "45m". */
    public static function duration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h ? ($m ? "{$h}h {$m}m" : "{$h}h") : "{$m}m";
    }

    /**
     * The application's time format without seconds, and never ambiguous:
     * a 12-hour format that forgot AM/PM gets it.
     */
    public static function timeFormat(?string $format): string
    {
        $format = trim(str_replace([':s', '.s'], '', $format ?: 'h:i A'));
        if (preg_match('/[hg]/', $format) && !preg_match('/[aA]/', $format)) {
            $format .= ' A';
        }

        return $format;
    }

    /** Today in the application's day numbering (1 = Saturday … 7 = Friday). */
    public static function todayNumber(): int
    {
        return ((now()->dayOfWeek + 1) % 7) + 1;
    }
}
