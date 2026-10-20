<?php

namespace App\Services\Academic;

use Illuminate\Support\Collection;

/**
 * The class timetable, read rather than written.
 *
 * Three jobs, each of which was being done badly or not at all before:
 *
 * 1. LAY IT OUT. The old screen put a whole day in one table cell, so nothing
 *    lined up across the week and nobody could answer "what is happening at
 *    ten o'clock". This builds a proper time-by-day grid.
 *
 * 2. BAND THE TIMES. Hours in this data are entered by hand and are
 *    near-but-not-quite aligned: 08:00, 08:10 and 08:16 are all the eight
 *    o'clock class, and 10:00, 10:02 and 10:29 are all the ten o'clock one.
 *    Keying rows on the exact string gives 27 near-identical bands for what a
 *    human reads as five. Starts within TOLERANCE of the band's first start
 *    join that band, so the grid reads the way the week is actually taught.
 *
 * 3. SAY WHAT IS WRONG. A timetable with a mistake in it sends students and
 *    lecturers to the wrong place, and the mistakes are mechanically findable.
 *    Everything this reports is a statement about two rows that cannot both be
 *    true, never a matter of taste.
 *
 * It writes nothing and schedules nothing. Fixing what it finds is still done
 * on the Add/Edit screen by someone who knows which of the two classes should
 * move.
 */
class ClassTimetable
{
    /**
     * The week as this system numbers it: 1 is Saturday, 7 is Friday.
     *
     * The same order the student timetable uses, so the two screens can never
     * disagree about what "day 3" means.
     */
    public const DAYS = [
        1 => 'day_saturday',
        2 => 'day_sunday',
        3 => 'day_monday',
        4 => 'day_tuesday',
        5 => 'day_wednesday',
        6 => 'day_thursday',
        7 => 'day_friday',
    ];

    /** How far apart two starts can be and still be the same class hour. */
    public const TOLERANCE = 30 * 60;

    /** Longer than this and the row is a data-entry error, not a lesson. */
    public const MAX_MINUTES = 300;

    /**
     * The grid: one row per time band, one cell per day.
     *
     * @return array{bands: array<int, array>, days: array<int, string>}
     */
    public function grid(Collection $rows): array
    {
        $rows = $rows->filter(fn ($row) => isset(self::DAYS[(int) $row->day]));

        $bands = [];
        $starts = $this->bandStarts($rows);

        foreach ($starts as $index => $start) {
            $next = $starts[$index + 1] ?? null;

            $inBand = $rows->filter(function ($row) use ($start, $next) {
                $at = $this->seconds($row->start_time);

                return $at >= $start && ($next === null || $at < $next);
            });

            if ($inBand->isEmpty()) {
                continue;
            }

            $cells = [];

            foreach (array_keys(self::DAYS) as $day) {
                $cells[$day] = $inBand->where('day', (string) ($day % 7 + 1))
                    ->sortBy('start_time')
                    ->values();
            }

            $bands[] = [
                'start' => $inBand->min('start_time'),
                'end' => $inBand->max('end_time'),
                'cells' => $cells,
            ];
        }

        return ['bands' => $bands, 'days' => self::DAYS];
    }

    /**
     * The first start of each band, in order.
     *
     * A new band opens as soon as a start is more than TOLERANCE past the one
     * that opened the current band — measured from the band's own first start,
     * not from the previous row, so a run of closely spaced entries cannot
     * chain into one band that swallows the whole day.
     *
     * @return array<int, int>
     */
    protected function bandStarts(Collection $rows): array
    {
        $starts = $rows->map(fn ($row) => $this->seconds($row->start_time))
            ->unique()->sort()->values();

        $bands = [];

        foreach ($starts as $start) {
            if ($bands === [] || $start - end($bands) > self::TOLERANCE) {
                $bands[] = $start;
            }
        }

        return $bands;
    }

    /**
     * Everything that cannot both be true, keyed by routine id.
     *
     * $scope is every class the conflict should be judged against — pass the
     * whole session, not just the timetable on screen, or a room booked twice
     * by two different programmes is invisible to both of them.
     *
     * @return array<int, array<int, array{type: string, note: string, with: int}>>
     */
    public function conflicts(Collection $scope): array
    {
        $found = [];
        $rows = $scope->values();

        $add = function (&$found, $id, $type, $note, $with) {
            $found[$id][] = ['type' => $type, 'note' => $note, 'with' => $with];
        };

        // Every pair below wants the same few facts about each class, and
        // reading them off the model each time is what made this slow: an
        // Eloquent property goes through __get and the mutator machinery, and
        // a busy session asks for tens of thousands of them. Each class is
        // therefore flattened once, here, into a plain array — the comparison
        // keys and the strings the messages are built from — and the pair
        // walk below touches nothing but arrays.
        $flat = [];

        foreach ($rows as $row) {
            $flat[] = [
                'id' => $row->id,
                'day' => (string) $row->day,
                'start' => (string) $row->start_time,
                'end' => (string) $row->end_time,
                'group' => $row->program_id . ':' . $row->session_id
                    . ':' . $row->semester_id . ':' . $row->section_id,
                'room_id' => (int) $row->room_id,
                'teacher_id' => (int) $row->teacher_id,
                'subject_id' => (int) $row->subject_id,
                'code' => $row->subject->code ?? __('another class'),
                'room' => $row->room->title ?? __('The room'),
                'programme' => $row->program->shortcode ?? $row->program->title ?? '—',
                'lecturer' => $this->lecturer($row),
                'from' => $this->clock($row->start_time),
                'to' => $this->clock($row->end_time),
            ];
        }

        foreach ($flat as $one) {
            $minutes = (strtotime($one['end']) - strtotime($one['start'])) / 60;

            if ($minutes <= 0) {
                $add($found, $one['id'], 'duration',
                    __('Ends at or before it starts (:start to :end).', [
                        'start' => $one['from'],
                        'end' => $one['to'],
                    ]), $one['id']);
            } elseif ($minutes > self::MAX_MINUTES) {
                $add($found, $one['id'], 'duration',
                    __(':hours hours long (:start to :end) — longer than any lesson.', [
                        'hours' => round($minutes / 60, 1),
                        'start' => $one['from'],
                        'end' => $one['to'],
                    ]), $one['id']);
            }
        }

        // Two classes can only conflict on the same day, so compare within a
        // day rather than across the whole session: it is the difference
        // between a quarter of a million comparisons and thirty thousand, and
        // this runs on every page load.
        $byDay = [];

        foreach ($flat as $one) {
            $byDay[$one['day']][] = $one;
        }

        foreach ($byDay as $day) {
            usort($day, fn ($x, $y) => strcmp($x['start'], $y['start']));
            $count = count($day);

            for ($i = 0; $i < $count; $i++) {
                $a = $day[$i];

                for ($j = $i + 1; $j < $count; $j++) {
                    $b = $day[$j];

                    // Sorted by start, so once one starts at or after this
                    // one ends, nothing later in the day can overlap either.
                    if ($b['start'] >= $a['end']) {
                        break;
                    }

                    if ($b['end'] <= $a['start']) {
                        continue;
                    }

                    $window = $a['from'] . '–' . $a['to'];

                    // Two classes in one group's own timetable: the students
                    // themselves are double-booked, which is the worst of the
                    // three because no room change can resolve it.
                    if ($a['group'] === $b['group']) {
                        foreach ([[$a, $b], [$b, $a]] as [$x, $y]) {
                            $add($found, $x['id'], 'clash',
                                __('This class overlaps :code in the same timetable, so the group is in two places at once.', [
                                    'code' => $y['code'],
                                ]), $y['id']);
                        }
                    }

                    $differentCourse = $a['subject_id'] !== $b['subject_id'];

                    // The same subject in the same room at the same time is a
                    // joint lecture, which is how this school teaches shared
                    // courses. Only a different subject is a double-booking.
                    if ($a['room_id'] && $a['room_id'] === $b['room_id'] && $differentCourse) {
                        foreach ([[$a, $b], [$b, $a]] as [$x, $y]) {
                            $add($found, $x['id'], 'room',
                                __(':room is also taking :code (:programme) at :window.', [
                                    'room' => $x['room'],
                                    'code' => $y['code'],
                                    'programme' => $y['programme'],
                                    'window' => $window,
                                ]), $y['id']);
                        }
                    }

                    // Likewise a lecturer: teaching one subject to several
                    // groups at once is the joint lecture; two subjects is
                    // not possible.
                    if ($a['teacher_id'] && $a['teacher_id'] === $b['teacher_id'] && $differentCourse) {
                        foreach ([[$a, $b], [$b, $a]] as [$x, $y]) {
                            $add($found, $x['id'], 'lecturer',
                                __(':name is also teaching :code (:programme) at :window.', [
                                    'name' => $x['lecturer'],
                                    'code' => $y['code'],
                                    'programme' => $y['programme'],
                                    'window' => $window,
                                ]), $y['id']);
                        }
                    }
                }
            }
        }

        return $found;
    }

    /**
     * What the timetable adds up to.
     *
     * @return array<string, mixed>
     */
    public function summary(Collection $rows, array $conflicts = []): array
    {
        $minutes = $rows->sum(fn ($row) => max(0, min($this->minutes($row), self::MAX_MINUTES)));

        $ids = $rows->pluck('id')->all();
        $flagged = array_intersect_key($conflicts, array_flip($ids));

        $byType = ['clash' => 0, 'room' => 0, 'lecturer' => 0, 'duration' => 0];

        foreach ($flagged as $list) {
            foreach ($list as $item) {
                $byType[$item['type']] = ($byType[$item['type']] ?? 0) + 1;
            }
        }

        return [
            'classes' => $rows->count(),
            'courses' => $rows->pluck('subject_id')->filter()->unique()->count(),
            'lecturers' => $rows->pluck('teacher_id')->filter()->unique()->count(),
            'rooms' => $rows->pluck('room_id')->filter()->unique()->count(),
            'days' => $rows->pluck('day')->filter()->unique()->count(),
            // Capped at MAX_MINUTES a class, so one 14-hour typo cannot make
            // the week's contact hours meaningless.
            'contact_hours' => round($minutes / 60, 1),
            'flagged' => count($flagged),
            'conflicts' => $byType,
            'total_conflicts' => array_sum($byType),
        ];
    }

    /** The days actually taught, in week order. */
    public function daysUsed(Collection $rows): array
    {
        $used = $rows->pluck('day')->map(fn ($day) => (int) $day)->unique()->all();

        return array_values(array_filter(
            array_keys(self::DAYS),
            fn ($day) => in_array($day, $used, true)
        ));
    }

    /** One timetable's identity, as a person would say it. */
    public function label($row): string
    {
        return trim(implode(' · ', array_filter([
            $row->program->title ?? null,
            $row->semester->title ?? null,
            ($row->section->title ?? null) ? __('field_section') . ' ' . $row->section->title : null,
        ])));
    }

    /** "PAX00025 — Ful Honest", or a plain dash when the post is vacant. */
    public function lecturer($row): string
    {
        $teacher = $row->teacher ?? null;

        if (!$teacher) {
            return '—';
        }

        $name = trim(($teacher->first_name ?? '') . ' ' . ($teacher->last_name ?? ''));

        return trim(($teacher->staff_id ? $teacher->staff_id . ' — ' : '') . $name) ?: '—';
    }

    /** A time as the timetable prints it. */
    public function clock($time): string
    {
        return date('h:i A', strtotime((string) $time));
    }

    /** Minutes between start and end; may be zero or negative on bad data. */
    public function minutes($row): float
    {
        return (strtotime((string) $row->end_time) - strtotime((string) $row->start_time)) / 60;
    }

    protected function overlaps($a, $b): bool
    {
        return $a->start_time < $b->end_time && $b->start_time < $a->end_time;
    }

    protected function sameTimetable($a, $b): bool
    {
        foreach (['program_id', 'session_id', 'semester_id', 'section_id'] as $key) {
            if ((int) $a->$key !== (int) $b->$key) {
                return false;
            }
        }

        return true;
    }

    protected function seconds($time): int
    {
        [$h, $m, $s] = array_pad(explode(':', (string) $time), 3, 0);

        return ((int) $h) * 3600 + ((int) $m) * 60 + (int) $s;
    }
}
