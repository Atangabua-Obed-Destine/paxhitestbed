<?php

namespace App\Exports\SenateResultsSheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReferenceSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    use SenateSheetHelpers;

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Reference & Legend';
    }

    public function array(): array
    {
        $grades = $this->data['grades'] ?? collect([]);
        $standingLabels = $this->data['standingLabels'] ?? [];
        $rows = [];

        // Header
        $rows[] = [strtoupper($this->data['institution_name'] ?? config('app.name'))];
        $rows[] = ['SENATE RESULTS PREVIEW — REFERENCE & LEGEND'];
        $rows[] = [
            'Session: ' . ($this->data['session_label'] ?? '') .
            '  |  Semester: ' . ($this->data['semester_label'] ?? '') .
            '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A')
        ];
        $rows[] = [''];

        // ── Section 1: Grade Scale ──
        $rows[] = ['GRADE SCALE'];
        $rows[] = ['Grade', 'Grade Point', 'Min Mark', 'Max Mark', 'Description'];
        foreach ($grades as $g) {
            $desc = '';
            if ($g->point >= 4.0) $desc = 'Excellent';
            elseif ($g->point >= 3.5) $desc = 'Very Good';
            elseif ($g->point >= 3.0) $desc = 'Good';
            elseif ($g->point >= 2.5) $desc = 'Credit';
            elseif ($g->point >= 2.0) $desc = 'Pass';
            elseif ($g->point >= 1.0) $desc = 'Marginal Pass';
            else $desc = 'Fail';

            $rows[] = [
                $g->title,
                number_format($g->point, 1),
                $g->min_mark ?? '-',
                $g->max_mark ?? '-',
                $desc,
            ];
        }
        $rows[] = [''];

        // ── Section 2: Column Abbreviations ──
        $rows[] = ['COLUMN ABBREVIATIONS'];
        $rows[] = ['Abbreviation', 'Full Name', 'Description'];
        $abbrevs = [
            ['Att', 'Attendance', 'Student attendance marks component'],
            ['CA', 'Continuous Assessment', 'CA marks = Assignment + Activity + CA Exam marks'],
            ['EX', 'Final Exam', 'Final examination marks component'],
            ['TOT', 'Total', 'Sum of all mark components (Att + CA + EX)'],
            ['Grd', 'Grade', 'Letter grade assigned based on total marks'],
            ['TCR', 'Total Credits Registered', 'Sum of credit values for all registered courses'],
            ['TCE', 'Total Credits Earned', 'Sum of credit values for passed courses only'],
            ['R#', 'Scheduled Resit Courses', 'Number of courses in scheduled resit state for the student'],
            ['RCR', 'Scheduled Resit Credits', 'Total credit value of courses in scheduled resit state'],
            ['CO#', 'Carry-Over Courses', 'Number of courses currently classified as carry-over'],
            ['COCR', 'Carry-Over Credits', 'Total credit value of courses currently classified as carry-over'],
            ['QP', 'Quality Points', 'Sum of (Grade Point × Credit Value) for all courses'],
            ['GPA', 'Grade Point Average', 'QP ÷ TCR — weighted average of grade points'],
            ['CV', 'Credit Value', 'Credit hours/value assigned to a course'],
            ['VAL', 'Validated', 'Course has been passed in this or a later attempt'],
            ['RES', 'Scheduled Resit', 'Failed course has been scheduled into a resit semester'],
            ['CO', 'Carry Over', 'Failed course remains to be retaken outside the current semester offering'],
            ['PND', 'Pending Resit Decision', 'Failed course is still in the unresolved resit workflow'],
        ];
        foreach ($abbrevs as $a) {
            $rows[] = $a;
        }
        $rows[] = [''];

        // ── Section 3: Status Codes ──
        $rows[] = ['STATUS CODES'];
        $rows[] = ['Code', 'Meaning', 'Description'];
        $statuses = [
            ['P', 'Passed', 'Student scored ≥ 50 marks (pass mark threshold)'],
            ['F', 'Failed', 'Student scored < 50 marks'],
            ['NR', 'Not Registered', 'Student is not enrolled in this course'],
            ['ABS', 'Absent', 'Student was absent for the examination'],
            ['N/S', 'Not Submitted', 'Marks have not been submitted by the lecturer'],
            ['⚠', 'Distribution Warning', 'Mark contribution percentages not configured (zero distribution)'],
        ];
        foreach ($statuses as $s) {
            $rows[] = $s;
        }
        $rows[] = [''];

        // ── Section 4: Color Coding ──
        $rows[] = ['COLOR CODING GUIDE'];
        $rows[] = ['Color', 'Hex Code', 'Meaning'];
        $colors = [
            ['Green Background', '#d4edda', 'Passed / Good GPA (≥ 2.0) / High Pass Rate (≥ 70%)'],
            ['Yellow Background', '#fff3cd', 'Warning / Low GPA (< 2.0) / Medium Pass Rate (50-69%) / N/S'],
            ['Red Background', '#f8d7da', 'Failed / Very Low GPA (< 1.0) / Low Pass Rate (< 50%)'],
            ['Grey Background', '#e2e3e5', 'Absent / Not Available'],
            ['Blue Background', '#e8f4f8', 'Summary / Totals rows'],
            ['Green Text', '#28a745', 'Total marks for passed courses'],
            ['Red Text', '#dc3545', 'Total marks for failed courses'],
        ];
        foreach ($colors as $c) {
            $rows[] = $c;
        }
        $rows[] = [''];

        // ── Section 5: GPA Computation ──
        $rows[] = ['GPA COMPUTATION FORMULA'];
        $rows[] = [''];
        $rows[] = ['GPA = Σ(Grade Point × Credit Value) ÷ Σ(Credit Value of Registered Courses)'];
        $rows[] = [''];
        $rows[] = ['Example:', '', ''];
        $rows[] = ['Course', 'Grade', 'Grade Point', 'Credit Value', 'Quality Points'];
        $rows[] = ['Course A', 'A', '4.0', '3', '12.0'];
        $rows[] = ['Course B', 'B+', '3.5', '3', '10.5'];
        $rows[] = ['Course C', 'C', '2.0', '2', '4.0'];
        $rows[] = ['', '', '', 'Total CV: 8', 'Total QP: 26.5'];
        $rows[] = ['', '', '', '', 'GPA = 26.5 ÷ 8 = 3.31'];
        $rows[] = [''];

        // ── Section 6: Lecturer Ratings ──
        $rows[] = ['LECTURER PERFORMANCE RATINGS'];
        $rows[] = ['Rating', 'Pass Rate Range', 'Description'];
        $lRatings = [
            ['Outstanding', '≥ 80%', 'Exceptional teaching performance with very high pass rates'],
            ['Good', '70% – 79%', 'Good teaching performance above institution average'],
            ['Satisfactory', '60% – 69%', 'Acceptable performance meeting minimum standards'],
            ['Needs Improvement', '50% – 59%', 'Below average, department attention recommended'],
            ['Critical', '< 50%', 'Significantly below standard, immediate intervention required'],
        ];
        foreach ($lRatings as $lr) {
            $rows[] = $lr;
        }
        $rows[] = [''];

        // ── Section 7: Academic Standings ──
        $rows[] = ['ACADEMIC STANDING CLASSIFICATIONS'];
        $rows[] = ['Standing', 'Description'];
        foreach ($standingLabels as $key => $label) {
            $desc = match ($key) {
                'deans_list'               => 'GPA ≥ 3.50 with no failures — highest academic honor',
                'good_standing'            => 'GPA ≥ 2.00 with no failures — satisfactory progress',
                'academic_warning'         => 'GPA 1.50 – 1.99 — student is warned about performance',
                'academic_probation'       => 'GPA 1.00 – 1.49 — student on probation, restricted load',
                'recommended_dismissal'    => 'GPA < 1.00 — recommended for academic dismissal',
                default                    => ucfirst(str_replace('_', ' ', $key)),
            };
            $rows[] = [$label, $desc];
        }
        $rows[] = [''];

        // ── Section 8: Report Metadata ──
        $rows[] = ['REPORT METADATA'];
        $rows[] = ['Property', 'Value'];
        $rows[] = ['Report Title', 'Senate Results Preview — Comprehensive Export'];
        $rows[] = ['Academic Session', $this->data['session_label'] ?? '-'];
        $rows[] = ['Semester', $this->data['semester_label'] ?? '-'];
        $rows[] = ['Generated Date', now()->format('l, F d, Y')];
        $rows[] = ['Generated Time', now()->format('h:i:s A')];
        $rows[] = ['Generated By', auth()->user()->name ?? 'System'];
        $rows[] = ['Pass Mark Threshold', '50 marks'];
        $rows[] = ['Number of Worksheets', '7+ (Including per-faculty student matrices)'];
        $rows[] = ['Software', 'PAX Higher Institute — Academic Management System'];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'E';

                // Header rows
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1a1a2e']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '2c3e50']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Find and style all section titles
                $highestRow = $sheet->getHighestRow();
                $sectionTitles = [
                    'GRADE SCALE', 'COLUMN ABBREVIATIONS', 'STATUS CODES',
                    'COLOR CODING GUIDE', 'GPA COMPUTATION FORMULA',
                    'LECTURER PERFORMANCE RATINGS', 'ACADEMIC STANDING CLASSIFICATIONS',
                    'REPORT METADATA',
                ];
                $headerRowNums = [];

                for ($r = 5; $r <= $highestRow; $r++) {
                    $val = trim($sheet->getCell("A{$r}")->getValue());
                    if (in_array($val, $sectionTitles)) {
                        $this->writeSectionTitle($sheet, $r, $lastCol, "  {$val}", '2c3e50');
                    }
                    // Style sub-header rows (based on known header patterns)
                    if (in_array($val, ['Grade', 'Abbreviation', 'Code', 'Color', 'Rating', 'Standing', 'Property', 'Course', 'Example:'])) {
                        $nextVal = $sheet->getCell("B{$r}")->getValue();
                        // Only style actual column header rows
                        if ($nextVal && !in_array($val, ['Example:', 'Course A', 'Course B', 'Course C'])) {
                            $this->styleHeaderRow($sheet, $r, 'A', $lastCol, '495057');
                            $headerRowNums[] = $r;
                        }
                    }
                }

                // Color-code the color guide rows
                for ($r = 5; $r <= $highestRow; $r++) {
                    $val = $sheet->getCell("B{$r}")->getValue();
                    if ($val && strpos($val, '#') === 0) {
                        $hex = str_replace('#', '', $val);
                        $this->conditionalFill($sheet, "A{$r}", $hex);
                    }
                }

                $this->autoSizeColumns($sheet, $lastCol);
                $this->configurePrint($sheet, 'portrait');
            },
        ];
    }
}
