<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * The verdict, on one page.
 *
 * Written so that a reader who opens the workbook and reads nothing else knows
 * whether the school is ready and, if not, how far off. The score is shown with
 * its working rather than as a bare number, because a number nobody can account
 * for is either trusted blindly or dismissed.
 */
class SummarySheet extends HealthSheet
{
    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $kpis = $this->data['kpis'];
        $breakdown = $this->data['score_breakdown'];
        $errors = count($this->data['diagnostic_errors']);
        $warnings = count($this->data['warnings']);

        $rows = $this->masthead('Readiness summary');

        // The verdict, in words. The score alone does not say what to do.
        if ($errors > 0) {
            $verdict = 'NOT READY — ' . $errors . ' item(s) must be corrected before the year runs';
            $verdictKey = 'bad';
        } elseif ($warnings > 0) {
            $verdict = 'READY WITH RESERVATIONS — ' . $warnings . ' item(s) need attention';
            $verdictKey = 'warn';
        } else {
            $verdict = 'READY — no outstanding findings';
            $verdictKey = 'ok';
        }

        $rows[] = ['VERDICT', $verdict];
        $this->verdictRows[count($rows)] = $verdictKey;
        $rows[] = [''];

        $rows[] = ['READINESS SCORE'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Measure', 'Value', 'What it means'];
        $this->headerRows[] = count($rows);

        $rows[] = [
            'Configuration readiness',
            $breakdown['configuration'] . '%',
            'How completely the academic structure is set up, weighted by consequence.',
        ];
        $rows[] = [
            'Less: errors',
            '-' . $breakdown['error_penalty'],
            $breakdown['errors'] . ' item(s) that stop something working.',
        ];
        $rows[] = [
            'Less: warnings',
            '-' . $breakdown['warning_penalty'],
            $breakdown['warnings'] . ' item(s) that need attention but do not stop the year.',
        ];
        $rows[] = ['Overall score', $kpis['overall_score'] . '%', 'The figure to report.'];
        $this->verdictRows[count($rows)] = $verdictKey;

        $rows[] = [''];
        $rows[] = ['THE SCHOOL AT A GLANCE'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Measure', 'Count', 'Notes'];
        $this->headerRows[] = count($rows);

        $rows[] = ['Faculties', $kpis['total_faculties'], ''];
        $rows[] = ['Departments', $kpis['total_departments'], ''];
        $rows[] = ['Programmes', $kpis['total_programs'], ''];
        $rows[] = ['Courses', $kpis['total_subjects'], 'Active courses on the books.'];
        $rows[] = ['Students', $kpis['total_students'], 'Active students.'];
        $rows[] = ['Teaching staff', $kpis['total_teachers'], 'Staff with at least one timetabled class.'];
        $rows[] = ['All staff', $kpis['total_staff'], ''];
        $rows[] = [
            'Fee coverage',
            $kpis['fee_coverage'] . '%',
            'Share of active programmes with at least one fee configured.',
        ];

        $rows[] = [''];
        $rows[] = ['HOW TO READ THIS WORKBOOK'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Sheet', 'What it answers'];
        $this->headerRows[] = count($rows);

        foreach ([
            ['Action Required', 'Everything currently wrong, worst first, and where to fix it.'],
            ['Configuration', 'Whether each stage of setup is complete.'],
            ['Programmes', 'Per programme: courses, fees, timetable and teaching staff.'],
            ['Semester Matrix', 'Which programme-and-semester combinations are ready to run.'],
            ['Course Catalogue', 'Every course configured, listed under its programme and semester, to check against your own course list.'],
            ['Teaching Staff', 'Who teaches what, and who has no teaching load.'],
            ['Results Readiness', 'Whether marks are complete enough to close the semester.'],
            ['Finance', 'Fees billed against fees collected, and what is outstanding.'],
        ] as $row) {
            $rows[] = $row;
        }

        return $rows;
    }
}
