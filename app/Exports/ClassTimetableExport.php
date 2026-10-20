<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * A session's timetables as one workbook.
 *
 * Ordered the way the registry reads it: the state of the whole session first,
 * then what is wrong with it, then a printable grid for every class group. A
 * head of department who only wants their own programme goes straight to its
 * tab; whoever signs the session off reads the first two and stops.
 *
 * One file, so the thirty-nine timetables that used to be printed one screen
 * at a time arrive together and formatted.
 */
class ClassTimetableExport implements WithMultipleSheets
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        $sheets = [
            new ClassTimetableSheets\OverviewSheet($this->data),
            new ClassTimetableSheets\ConflictSheet($this->data),
        ];

        // Excel allows 31 characters of sheet name and no duplicates, so the
        // tab is numbered and built from codes rather than from the full
        // programme title, which would collide after truncation.
        $used = [];

        foreach ($this->data['timetables'] as $index => $entry) {
            $sheets[] = new ClassTimetableSheets\TimetableSheet(
                $this->data,
                $entry,
                $this->tabName($entry, $index + 1, $used)
            );
        }

        return $sheets;
    }

    /** A unique, legal, still-recognisable tab name. */
    protected function tabName(array $entry, int $number, array &$used): string
    {
        $code = $entry['program']->shortcode
            ?: ($entry['program']->title ?? 'Programme');

        // Year and semester both go in the name: one programme routinely has
        // a first-semester and a second-semester timetable in the same year,
        // and two tabs reading "HND - AGE Y1" tell the reader nothing about
        // which is which.
        $semester = $entry['semester'];
        $where = trim(($semester->year ? 'Y' . $semester->year : '')
            . ($semester->semester_type ? ' S' . $semester->semester_type : ''));

        $stem = $number . '. ' . $code . ($where ? ' ' . $where : '');

        // Characters Excel refuses in a sheet name.
        $stem = str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $stem);
        $stem = trim(mb_substr($stem, 0, 31));

        $candidate = $stem;
        $suffix = 2;

        while (isset($used[mb_strtolower($candidate)])) {
            $tail = ' (' . $suffix++ . ')';
            $candidate = trim(mb_substr($stem, 0, 31 - mb_strlen($tail))) . $tail;
        }

        $used[mb_strtolower($candidate)] = true;

        return $candidate;
    }
}
