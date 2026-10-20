<?php

namespace App\Exports\ClassTimetableSheets;

use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * The state of the whole session, one line per class group.
 *
 * The sheet that answers "can we teach this session". Before this existed the
 * only way to find the programme nobody had scheduled was to open each one in
 * turn, choosing a faculty, programme, semester and section every time.
 */
class OverviewSheet extends TimetableSheetBase
{
    public function title(): string
    {
        return __('Overview');
    }

    public function array(): array
    {
        $rows = $this->masthead(__('Every timetable in this session'));

        $totals = $this->data['totals'];
        $timetables = $this->data['timetables'];

        $rows[] = [__('Session at a glance')];
        $this->sectionRows[] = count($rows);

        $rows[] = [__('Timetables'), $timetables->count()];
        $rows[] = [__('Classes scheduled'), $totals['classes']];
        $rows[] = [__('Distinct courses'), $totals['courses']];
        $rows[] = [__('Lecturers involved'), $totals['lecturers']];
        $rows[] = [__('Rooms in use'), $totals['rooms']];
        $rows[] = [__('Contact hours a week'), $totals['contact_hours']];
        $rows[] = [__('Programmes with no timetable'),
            count($this->data['unscheduled']) . ' ' . __('of') . ' ' . $this->data['programme_count']];

        $rows[] = [__('Classes carrying a problem'), $totals['flagged']];
        $this->verdictRows[count($rows)] = $totals['flagged'] > 0 ? 'bad' : 'ok';

        $rows[] = [''];

        $rows[] = [__('Timetables')];
        $this->sectionRows[] = count($rows);

        $rows[] = [
            __('field_faculty'),
            __('field_program'),
            __('Code'),
            __('field_year'),
            __('field_semester'),
            __('field_section'),
            __('Classes'),
            __('Courses'),
            __('Lecturers'),
            __('Rooms'),
            __('Days'),
            __('Contact Hours'),
            __('Problems'),
            __('Status'),
        ];
        $this->headerRows[] = count($rows);

        foreach ($timetables as $entry) {
            $summary = $entry['summary'];

            $rows[] = [
                $entry['faculty'],
                $entry['program']->title ?? '—',
                $entry['program']->shortcode ?? '—',
                $entry['semester']->year ?? '—',
                $entry['semester']->title ?? '—',
                $entry['section']->title ?? '—',
                $summary['classes'],
                $summary['courses'],
                $summary['lecturers'],
                $summary['rooms'],
                $summary['days'],
                $summary['contact_hours'],
                $summary['total_conflicts'] ?: '',
                $this->verdict($summary),
            ];

            $this->verdictRows[count($rows)] = $summary['total_conflicts'] > 0
                ? ($summary['conflicts']['clash'] > 0 || $summary['conflicts']['duration'] > 0 ? 'bad' : 'warn')
                : 'ok';
        }

        // A programme with no timetable at all cannot appear in the list
        // above, because the list is built from the classes that exist. It
        // has to be named separately or it is invisible.
        if (count($this->data['unscheduled'])) {
            $rows[] = [''];
            $rows[] = [__('Programmes with no timetable in this session')];
            $this->sectionRows[] = count($rows);

            $rows[] = [__('field_faculty'), __('field_program'), __('Code')];
            $this->headerRows[] = count($rows);

            foreach ($this->data['unscheduled'] as $programme) {
                $rows[] = [
                    $programme->faculty->title ?? '—',
                    $programme->title,
                    $programme->shortcode ?? '—',
                ];
                $this->verdictRows[count($rows)] = 'warn';
            }
        }

        $rows[] = [''];
        $rows[] = [__('Contact hours count each class at its scheduled length, capped at five hours, so a single mistyped end time cannot distort the week.')];
        $rows[] = [__('"Problems" counts the conflicts listed on the Conflicts sheet. A timetable with none is marked Clear.')];

        return $rows;
    }

    /** The word, so the colour is never carrying the meaning alone. */
    protected function verdict(array $summary): string
    {
        if ($summary['conflicts']['clash'] > 0) {
            return __('Group double-booked');
        }

        if ($summary['conflicts']['duration'] > 0) {
            return __('Check times');
        }

        if ($summary['total_conflicts'] > 0) {
            return __('Room or lecturer clash');
        }

        return __('Clear');
    }

    public function registerEvents(): array
    {
        // The glance block reads as a list, not a grid.
        $this->fixedWidths = ['A' => 38, 'B' => 44];
        $this->orientation = PageSetup::ORIENTATION_LANDSCAPE;

        return parent::registerEvents();
    }
}
