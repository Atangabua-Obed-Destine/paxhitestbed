<?php

namespace App\Exports\ClassTimetableSheets;

/**
 * Every conflict in the session, named, with the two classes that cause it.
 *
 * Each line is a statement about two rows that cannot both be true — a group
 * in two rooms at once, a room taking two different courses, a lecturer in two
 * places, or a lesson whose end time is impossible. Nothing here is a matter
 * of opinion, and nothing here is fixed automatically: the sheet says what is
 * wrong and where, and someone who knows which of the two classes should move
 * does the moving.
 *
 * A class shared by several programmes at the same hour in the same room is a
 * joint lecture, which is how this school teaches shared courses, so it is not
 * reported.
 */
class ConflictSheet extends TimetableSheetBase
{
    public function title(): string
    {
        return __('Conflicts');
    }

    public function array(): array
    {
        $rows = $this->masthead(__('Classes that cannot all be true as scheduled'));

        $timetable = $this->data['timetable'];
        $conflicts = $this->data['conflicts'];
        $byId = $this->data['all_rows']->keyBy('id');

        $labels = [
            'clash' => __('Group double-booked'),
            'room' => __('Room double-booked'),
            'lecturer' => __('Lecturer double-booked'),
            'duration' => __('Impossible time'),
        ];

        // Worst first: a group in two places cannot be resolved by moving a
        // room, so it is the one that has to be dealt with.
        $order = ['clash' => 0, 'duration' => 1, 'lecturer' => 2, 'room' => 3];

        $flat = [];

        foreach ($conflicts as $id => $list) {
            foreach ($list as $item) {
                $row = $byId->get($id);

                if (!$row) {
                    continue;
                }

                $flat[] = [
                    'sort' => ($order[$item['type']] ?? 9) . '-' . ($row->day ?? 0)
                        . '-' . ($row->start_time ?? ''),
                    'type' => $item['type'],
                    'row' => $row,
                    'note' => $item['note'],
                ];
            }
        }

        usort($flat, fn ($a, $b) => strcmp($a['sort'], $b['sort']));

        $counts = [];

        foreach ($flat as $item) {
            $counts[$item['type']] = ($counts[$item['type']] ?? 0) + 1;
        }

        $rows[] = [__('What was found')];
        $this->sectionRows[] = count($rows);

        foreach ($labels as $type => $label) {
            $rows[] = [$label, $counts[$type] ?? 0];
            $this->verdictRows[count($rows)] = ($counts[$type] ?? 0) > 0
                ? ($type === 'clash' || $type === 'duration' ? 'bad' : 'warn')
                : 'ok';
        }

        $rows[] = [''];

        if ($flat === []) {
            $rows[] = [__('No conflicts found in this session.')];
            $this->verdictRows[count($rows)] = 'ok';

            return $rows;
        }

        $rows[] = [__('Every conflict, worst first')];
        $this->sectionRows[] = count($rows);

        $rows[] = [
            __('Problem'),
            __('field_day'),
            __('Time'),
            __('field_program'),
            __('field_semester'),
            __('field_section'),
            __('field_subject'),
            __('field_room'),
            __('Lecturer'),
            __('What is wrong'),
        ];
        $this->headerRows[] = count($rows);

        foreach ($flat as $item) {
            $row = $item['row'];

            $rows[] = [
                $labels[$item['type']] ?? $item['type'],
                __($timetable::DAYS[(int) $row->day] ?? '—'),
                $timetable->clock($row->start_time) . ' – ' . $timetable->clock($row->end_time),
                $row->program->shortcode ?? $row->program->title ?? '—',
                $row->semester->title ?? '—',
                $row->section->title ?? '—',
                trim(($row->subject->code ?? '') . ' ' . ($row->subject->title ?? '')) ?: '—',
                $row->room->title ?? '—',
                $timetable->lecturer($row),
                $item['note'],
            ];

            $this->verdictRows[count($rows)] =
                $item['type'] === 'clash' || $item['type'] === 'duration' ? 'bad' : 'warn';
        }

        $rows[] = [''];
        $rows[] = [__('A course taught to several programmes at the same hour in the same room is a joint lecture and is not reported here.')];

        return $rows;
    }

    public function registerEvents(): array
    {
        $this->fixedWidths = ['A' => 24, 'J' => 60];

        return parent::registerEvents();
    }
}
