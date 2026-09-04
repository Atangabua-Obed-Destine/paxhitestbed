<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * Who teaches what.
 *
 * Includes staff carrying no teaching load, because an academic staff member
 * with no timetabled class is either genuinely non-teaching or has been left
 * off the timetable, and only the school can say which.
 */
class StaffSheet extends HealthSheet
{
    public function title(): string
    {
        return 'Teaching Staff';
    }

    public function array(): array
    {
        $rows = $this->masthead('Teaching load by member of staff');

        $rows[] = ['TEACHING STAFF (' . count($this->data['teacher_report']) . ')'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['#', 'Name', 'Department', 'Designation', 'Courses Taught', 'Programmes', 'Timetable Slots'];
        $this->headerRows[] = count($rows);

        foreach ($this->data['teacher_report'] as $i => $teacher) {
            $rows[] = [
                $i + 1,
                $teacher['name'],
                $teacher['department'],
                $teacher['designation'],
                $teacher['courses_taught'],
                $teacher['programs'] ?: '—',
                $teacher['total_slots'],
            ];

            // Nobody should be counted as teaching staff with nothing to teach.
            if ($teacher['courses_taught'] == 0) {
                $this->verdictRows[count($rows)] = 'warn';
            }
        }

        $withoutTeaching = $this->data['staff_without_teaching'] ?? [];

        $rows[] = [''];
        $rows[] = ['STAFF WITH NO TEACHING LOAD (' . count($withoutTeaching) . ')'];
        $this->sectionRows[] = count($rows);

        if (count($withoutTeaching) === 0) {
            $rows[] = ['None — every member of academic staff has at least one class.'];
            $this->verdictRows[count($rows)] = 'ok';
        } else {
            $rows[] = ['#', 'Name', 'Department', 'Designation'];
            $this->headerRows[] = count($rows);

            foreach ($withoutTeaching as $i => $member) {
                $rows[] = [
                    $i + 1,
                    is_array($member) ? ($member['name'] ?? '') : trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
                    is_array($member) ? ($member['department'] ?? '') : ($member->department->title ?? '—'),
                    is_array($member) ? ($member['designation'] ?? '') : ($member->designation->title ?? '—'),
                ];
                $this->verdictRows[count($rows)] = 'warn';
            }

            $rows[] = [''];
            $rows[] = ['Each of these is either genuinely non-teaching, or missing from the timetable. Confirm which.'];
        }

        $rows[] = [''];
        $rows[] = ['DEPARTMENTS'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Department', 'Staff'];
        $this->headerRows[] = count($rows);

        foreach ($this->data['staff_departments'] as $department) {
            $rows[] = [$department->title, $department->users->count()];

            if ($department->users->count() === 0) {
                $this->verdictRows[count($rows)] = 'warn';
            }
        }

        return $rows;
    }
}
