<?php

namespace App\Exports\ClassTimetableSheets;

/**
 * One class group's week, as a grid that can be printed and put on a wall.
 *
 * Time down the side, days across the top, so a student or a lecturer can
 * read along a row to find out what is happening at ten o'clock. The old
 * screen stacked a whole day into a single cell, which meant nothing lined up
 * across the week and that question had no answer.
 *
 * Each cell names the course, the room and the lecturer, because a timetable
 * that does not say who is teaching sends people to the right room to find
 * nobody there. A cell carrying a conflict is marked, and the detail is on the
 * Conflicts sheet rather than crammed in here.
 */
class TimetableSheet extends TimetableSheetBase
{
    protected array $entry;
    protected string $tab;

    public function __construct(array $data, array $entry, string $tab)
    {
        parent::__construct($data);

        $this->entry = $entry;
        $this->tab = $tab;
    }

    public function title(): string
    {
        return $this->tab;
    }

    public function array(): array
    {
        $timetable = $this->data['timetable'];
        $conflicts = $this->data['conflicts'];

        $rows = $this->masthead($timetable->label($this->entry['rows']->first()));

        $summary = $this->entry['summary'];

        $rows[] = [
            __('Classes') . ': ' . $summary['classes'],
            __('Courses') . ': ' . $summary['courses'],
            __('Lecturers') . ': ' . $summary['lecturers'],
            __('Rooms') . ': ' . $summary['rooms'],
            __('Contact Hours') . ': ' . $summary['contact_hours'],
            $summary['total_conflicts'] > 0
                ? __(':count problem(s) — see the Conflicts sheet', ['count' => $summary['total_conflicts']])
                : __('No conflicts'),
        ];
        $this->verdictRows[count($rows)] = $summary['total_conflicts'] > 0 ? 'bad' : 'ok';

        $rows[] = [''];

        // The grid.
        $grid = $timetable->grid($this->entry['rows']);

        $header = [__('Time')];

        foreach ($grid['days'] as $key) {
            $header[] = __($key);
        }

        $rows[] = $header;
        $this->headerRows[] = count($rows);

        foreach ($grid['bands'] as $band) {
            $line = [$timetable->clock($band['start']) . "\n" . $timetable->clock($band['end'])];

            foreach (array_keys($grid['days']) as $day) {
                $line[] = $this->cell($band['cells'][$day], $timetable, $conflicts);
            }

            $rows[] = $line;
        }

        if ($grid['bands'] === []) {
            $rows[] = [__('No classes scheduled.')];
        }

        // The same week again as a plain list, because a grid cell cannot hold
        // a full course title and somebody always needs the full title.
        $rows[] = [''];
        $rows[] = [__('The same classes as a list')];
        $this->sectionRows[] = count($rows);

        $rows[] = [
            __('field_day'),
            __('Time'),
            __('Code'),
            __('field_subject'),
            __('field_room'),
            __('Lecturer'),
            __('Problem'),
        ];
        $this->headerRows[] = count($rows);

        $listed = $this->entry['rows']
            ->sortBy(fn ($row) => sprintf('%02d-%s', (int) $row->day, $row->start_time));

        foreach ($listed as $row) {
            $flags = $conflicts[$row->id] ?? [];

            $rows[] = [
                __($timetable::DAYS[(int) $row->day] ?? '—'),
                $timetable->clock($row->start_time) . ' – ' . $timetable->clock($row->end_time),
                $row->subject->code ?? '—',
                $row->subject->title ?? '—',
                $row->room->title ?? '—',
                $timetable->lecturer($row),
                $flags ? $this->flagWords($flags) : '',
            ];

            if ($flags) {
                $types = array_column($flags, 'type');
                $this->verdictRows[count($rows)] =
                    (in_array('clash', $types, true) || in_array('duration', $types, true))
                        ? 'bad' : 'warn';
            }
        }

        return $rows;
    }

    /** One grid cell: everything a reader needs, nothing they do not. */
    protected function cell($classes, $timetable, array $conflicts): string
    {
        if ($classes->isEmpty()) {
            return '';
        }

        $parts = [];

        foreach ($classes as $row) {
            $text = ($row->subject->code ?? '—')
                . "\n" . ($row->room->title ?? '—')
                . "\n" . $timetable->lecturer($row);

            // The exact hours go in every cell, not just the odd ones. The
            // time column beside it carries the band, and in this data the
            // band is an approximation: 08:00, 08:10 and 08:16 all read as
            // the eight o'clock class. On a sheet somebody turns up to, the
            // class's own time has to be on the class.
            $text .= "\n" . $timetable->clock($row->start_time)
                . ' – ' . $timetable->clock($row->end_time);

            if (isset($conflicts[$row->id])) {
                $text .= "\n(!) " . $this->flagWords($conflicts[$row->id]);
            }

            $parts[] = $text;
        }

        return implode("\n— — —\n", $parts);
    }

    /** The problems on one class, as words rather than a colour. */
    protected function flagWords(array $flags): string
    {
        $labels = [
            'clash' => __('Group double-booked'),
            'room' => __('Room clash'),
            'lecturer' => __('Lecturer clash'),
            'duration' => __('Check times'),
        ];

        $types = array_unique(array_column($flags, 'type'));

        return implode(', ', array_map(fn ($type) => $labels[$type] ?? $type, $types));
    }

    public function registerEvents(): array
    {
        // Day columns are fixed and equal, so the week reads as a grid rather
        // than as seven columns of different widths.
        $this->fixedWidths = ['A' => 12];

        foreach (range('B', 'H') as $column) {
            $this->fixedWidths[$column] = 26;
        }

        return parent::registerEvents();
    }
}
