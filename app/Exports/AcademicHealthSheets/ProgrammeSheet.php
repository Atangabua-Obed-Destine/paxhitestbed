<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * Every programme, and whether it is ready to teach.
 *
 * One row per programme with the four things that have to be true before it can
 * run — courses, fees, a timetable, and somebody to teach it — and a verdict
 * combining them, so an incomplete programme is visible without reading across.
 */
class ProgrammeSheet extends HealthSheet
{
    public function title(): string
    {
        return 'Programmes';
    }

    public function array(): array
    {
        $rows = $this->masthead('Readiness of each programme');

        $rows[] = ['#', 'Faculty', 'Department', 'Programme', 'Award', 'Courses',
                   'Offerings', 'Students', 'Fees Set', 'Timetabled', 'Teachers', 'Ready'];
        $this->headerRows[] = count($rows);

        $index = 0;

        foreach ($this->data['faculty_report'] as $faculty) {
            foreach ($faculty['departments'] as $department) {
                foreach ($department['programs'] as $programme) {
                    $index++;

                    $hasCourses = ($programme['offerings'] ?? 0) > 0;
                    $hasFees = (bool) ($programme['has_fees'] ?? false);
                    $hasRoutines = (bool) ($programme['has_routines'] ?? false);
                    // 'teachers' is a collection of the staff teaching the
                    // programme, not a count.
                    $teachers = $programme['teachers'] ?? collect();
                    $teacherCount = is_countable($teachers) ? count($teachers) : (int) $teachers;

                    // A programme is only ready when all four hold. Reported as
                    // one word so nobody has to reconcile four columns by eye.
                    $missing = [];
                    if (!$hasCourses) { $missing[] = 'courses'; }
                    if (!$hasFees) { $missing[] = 'fees'; }
                    if (!$hasRoutines) { $missing[] = 'timetable'; }
                    if ($teacherCount < 1) { $missing[] = 'teachers'; }

                    $rows[] = [
                        $index,
                        $faculty['faculty'] ?? $faculty['title'] ?? '',
                        $department['title'] ?? '',
                        $programme['title'] ?? '',
                        $programme['degree'] ?? '',
                        $programme['subjects'] ?? 0,
                        $programme['offerings'] ?? 0,
                        $programme['students'] ?? 0,
                        $this->yesNo($hasFees),
                        $this->yesNo($hasRoutines),
                        $teacherCount,
                        $missing === [] ? 'Ready' : 'Missing: ' . implode(', ', $missing),
                    ];

                    $this->verdictRows[count($rows)] = $missing === []
                        ? 'ok'
                        : (count($missing) > 2 ? 'bad' : 'warn');
                }
            }
        }

        if ($index === 0) {
            $rows[] = ['No programmes configured.'];
            $this->verdictRows[count($rows)] = 'bad';
        }

        return $rows;
    }
}
