<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * Every course configured, listed under its programme and semester.
 *
 * The rest of the workbook reports counts, and a count cannot be checked: "15
 * courses" does not tell the head of department whether the fifteen are the
 * right fifteen. This sheet is the one the school reads down with their own
 * course list beside it, ticking off, which is the only way a missing course is
 * ever found before the semester starts.
 *
 * Laid out faculty, department, programme, semester, course — the same nesting
 * the senate results preview uses, so the two read alike.
 */
class CourseCatalogueSheet extends HealthSheet
{
    public function title(): string
    {
        return 'Course Catalogue';
    }

    public function array(): array
    {
        $rows = $this->masthead('Every course configured, by programme and semester');

        $totalCourses = 0;
        $totalCredits = 0.0;
        $emptySemesters = [];

        foreach ($this->data['faculty_report'] as $faculty) {
            $facultyName = $faculty['faculty'] ?? $faculty['title'] ?? '';

            foreach ($faculty['departments'] as $department) {
                foreach ($department['programs'] as $programme) {
                    $rows[] = [
                        strtoupper($programme['title'] ?? '')
                            . '  —  ' . $facultyName
                            . '  /  ' . ($department['title'] ?? ''),
                    ];
                    $this->sectionRows[] = count($rows);

                    // Indexed by semester name so the loop below can walk the
                    // semesters the school actually runs, rather than only the
                    // ones that happen to have an offering. A semester with no
                    // offering record would otherwise not appear at all — and a
                    // missing semester is exactly what this sheet exists to
                    // show.
                    $offerings = collect($programme['semester_offerings'] ?? [])
                        ->keyBy(fn ($o) => $o['semester'] ?? '');

                    foreach ($this->data['active_semesters'] as $activeSemester) {
                        $semester = $activeSemester->title;
                        $offering = $offerings->get($semester);
                        $courses = $offering['courses'] ?? [];

                        $rows[] = [
                            $semester,
                            count($courses) . ' course(s)',
                            'Total credits: ' . array_sum(array_column($courses, 'credits')),
                        ];
                        $this->verdictRows[count($rows)] = $courses === [] ? 'bad' : 'ok';

                        if ($courses === []) {
                            $rows[] = ['', 'No courses configured for this semester.'];
                            $this->verdictRows[count($rows)] = 'bad';
                            $emptySemesters[] = ($programme['title'] ?? '') . ' — ' . $semester;
                            continue;
                        }

                        $rows[] = ['#', 'Course Code', 'Course Title', 'Credits'];
                        $this->headerRows[] = count($rows);

                        foreach ($courses as $i => $course) {
                            $rows[] = [
                                $i + 1,
                                $course['code'] ?? '—',
                                $course['title'] ?? '—',
                                $course['credits'] ?? 0,
                            ];

                            // A course with no credit value cannot contribute to
                            // a GPA, so it is worth seeing here rather than
                            // discovering it at deliberation.
                            if (($course['credits'] ?? 0) <= 0) {
                                $this->verdictRows[count($rows)] = 'warn';
                            }

                            $totalCourses++;
                            $totalCredits += (float) ($course['credits'] ?? 0);
                        }
                    }

                    // Anything filed against a semester the school no longer
                    // runs. Rare, but it is real configuration and hiding it
                    // would make the totals disagree with the listing.
                    $expected = collect($this->data['active_semesters'])->pluck('title');

                    foreach ($offerings as $semesterName => $offering) {
                        if ($expected->contains($semesterName)) {
                            continue;
                        }

                        $courses = $offering['courses'] ?? [];

                        $rows[] = [$semesterName, count($courses) . ' course(s)', 'Not an active semester'];
                        $this->verdictRows[count($rows)] = 'warn';

                        foreach ($courses as $i => $course) {
                            $rows[] = [$i + 1, $course['code'] ?? '—', $course['title'] ?? '—', $course['credits'] ?? 0];
                            $totalCourses++;
                            $totalCredits += (float) ($course['credits'] ?? 0);
                        }
                    }

                    $rows[] = [''];
                }
            }
        }

        if ($totalCourses === 0) {
            $rows[] = ['No courses are configured anywhere.'];
            $this->verdictRows[count($rows)] = 'bad';

            return $rows;
        }

        $rows[] = ['TOTALS'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Measure', 'Value', ''];
        $this->headerRows[] = count($rows);
        $rows[] = ['Course offerings listed', $totalCourses, 'Counting a course once per semester it is offered in.'];
        $rows[] = ['Total credits offered', $totalCredits, ''];

        if ($emptySemesters !== []) {
            $rows[] = [''];
            $rows[] = ['SEMESTERS WITH NO COURSES (' . count($emptySemesters) . ')'];
            $this->sectionRows[] = count($rows);
            $rows[] = ['Nobody can be taught in these until courses are assigned.'];

            foreach ($emptySemesters as $gap) {
                $rows[] = [$gap];
                $this->verdictRows[count($rows)] = 'bad';
            }
        }

        return $rows;
    }
}
