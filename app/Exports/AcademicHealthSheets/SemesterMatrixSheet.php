<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * Which programme-and-semester combinations can actually run.
 *
 * The grid the academic office works from: a semester needs courses, a fee and
 * a timetable before students can be taught in it. A blank cell here is a
 * semester nobody can be enrolled into.
 */
class SemesterMatrixSheet extends HealthSheet
{
    public function title(): string
    {
        return 'Semester Matrix';
    }

    public function array(): array
    {
        $semesters = $this->data['active_semesters'];

        $rows = $this->masthead('Course, fee and timetable coverage per semester');

        $header = ['Programme', 'Code', 'Faculty', 'Award'];
        foreach ($semesters as $semester) {
            $header[] = $semester->title . ' — Courses';
            $header[] = $semester->title . ' — Fee';
            $header[] = $semester->title . ' — Timetable';
        }
        $header[] = 'Semesters Ready';

        $rows[] = $header;
        $this->headerRows[] = count($rows);

        foreach ($this->data['semester_matrix'] as $row) {
            $line = [
                $row['program'],
                $row['shortcode'],
                $row['faculty'],
                $row['degree'],
            ];

            $ready = 0;

            foreach ($semesters as $semester) {
                $cell = $row['semesters'][$semester->id] ?? ['courses' => 0, 'has_fee' => false, 'has_routine' => false];

                $line[] = $cell['courses'];
                $line[] = $this->yesNo($cell['has_fee']);
                $line[] = $this->yesNo($cell['has_routine']);

                if ($cell['courses'] > 0 && $cell['has_fee'] && $cell['has_routine']) {
                    $ready++;
                }
            }

            $line[] = $ready . ' of ' . $semesters->count();
            $rows[] = $line;

            $this->verdictRows[count($rows)] = $ready === $semesters->count()
                ? 'ok'
                : ($ready === 0 ? 'bad' : 'warn');
        }

        $rows[] = [''];
        $rows[] = ['A semester can only run where all three are present: courses offered, a fee configured, and a timetable for the current session.'];

        return $rows;
    }
}
