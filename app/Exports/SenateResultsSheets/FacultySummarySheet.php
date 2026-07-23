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

class FacultySummarySheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    use SenateSheetHelpers;

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Faculty Summary';
    }

    public function array(): array
    {
        $faculties = $this->data['faculty_summaries'] ?? [];
        $rows = [];

        // Header rows 1-4
        $rows[] = [strtoupper($this->data['institution_name'] ?? config('app.name'))];
        $rows[] = ['SENATE RESULTS PREVIEW — FACULTY & DEPARTMENT BREAKDOWN'];
        $rows[] = [
            'Session: ' . ($this->data['session_label'] ?? '') .
            '  |  Semester: ' . ($this->data['semester_label'] ?? '') .
            '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A')
        ];
        $rows[] = [''];

        // ── NEW: Student Performance Statistics Table (matching screenshot) ──
        $studentSummaries = $this->data['student_performance_summaries'] ?? [];
        if (!empty($studentSummaries)) {
            $totCourses = 0; $totReg = 0; $totExam = 0; $totPass = 0; $totFail = 0; $totScripts = 0;
            foreach ($studentSummaries as $ss) {
                $totCourses += $ss['courses_examined'];
                $totReg += $ss['registered'];
                $totExam += $ss['examined'];
                $totPass += $ss['passed'];
                $totFail += $ss['failed'];
                $totScripts += $ss['scripts_marked'];
            }
            $totPassRate = $totExam > 0 ? round(($totPass / $totExam) * 100, 1) : 0;
            $totFailRate = $totExam > 0 ? round(($totFail / $totExam) * 100, 1) : 0;
            
            $facMatrices = $this->data['faculty_student_matrices'] ?? [];
            $uniqueCourses = collect($facMatrices)->flatMap(fn($f) => collect($f['programs'] ?? [])->flatMap(fn($p) => $p['subjects'] ?? []))->unique('id')->count();

            $rows[] = ['SUMMARY OF RESULTS FOR THE ' . strtoupper($this->data['semester_label'] ?? 'First Semester') . ' EXAMINATION ' . ($this->data['session_label'] ?? '')];
            $rows[] = [
                "For the four schools, a total of {$uniqueCourses} courses were examined, with {$totScripts} scripts marked, {$totPass} passed and {$totFail} failed giving a percentage of passed of {$totPassRate} and percentage failed of {$totFailRate}."
            ];
            $rows[] = ['Table 1: ' . ($this->data['semester_label'] ?? 'First Semester') . ' Statistics ' . ($this->data['session_label'] ?? '')];
            
            $rows[] = [
                'Faculties/Schools', 'No of courses examined', 'No Registered',
                'No Examined', 'No Passed', 'No Failed', '% Passed'
            ];
            
            foreach ($studentSummaries as $ss) {
                $rows[] = [
                    $ss['shortcode'] ?: $ss['name'],
                    $ss['courses_examined'],
                    $ss['registered'],
                    $ss['examined'],
                    $ss['passed'],
                    $ss['failed'],
                    $ss['pass_rate']
                ];
            }
            
            $rows[] = [
                'Total', $totCourses, $totReg, $totExam, $totPass, $totFail, $totPassRate
            ];
            $rows[] = [''];
        }

        foreach ($faculties as $fac) {
            // Faculty title row
            $rows[] = [
                'FACULTY: ' . strtoupper($fac['name']) . ' (' . $fac['shortcode'] . ')',
                '', '', '', '', '', '', '', '',
                'Programmes: ' . $fac['program_count'],
                'Students: ' . $fac['student_count'],
            ];

            // Faculty aggregate
            $rows[] = [
                '', 'Faculty Aggregate',
                'Scripts: ' . $fac['scripts_written'],
                'Passed: ' . $fac['passed'],
                'Failed: ' . $fac['failed'],
                'Pass Rate: ' . number_format($fac['pass_rate'], 1) . '%',
                'Fail Rate: ' . number_format($fac['fail_rate'], 1) . '%',
                'Courses: ' . $fac['courses_offered'],
                '', '', '',
            ];

            // Department headers
            $rows[] = [
                '#', 'Department', 'Code', 'HOD',
                'Courses Offered', 'Scripts Written',
                'Passed', 'Failed', 'Pass Rate (%)', 'Fail Rate (%)', 'Remarks',
            ];

            // Department rows
            $depts = $fac['departments'] ?? [];
            $sn = 1;
            foreach ($depts as $dept) {
                $remark = '';
                if ($dept['pass_rate'] >= 80) $remark = 'Excellent';
                elseif ($dept['pass_rate'] >= 70) $remark = 'Very Good';
                elseif ($dept['pass_rate'] >= 60) $remark = 'Good';
                elseif ($dept['pass_rate'] >= 50) $remark = 'Fair';
                else $remark = 'Needs Attention';

                $rows[] = [
                    $sn++,
                    $dept['name'],
                    $dept['shortcode'],
                    $dept['head_name'] ?? '-',
                    $dept['courses_offered'],
                    $dept['scripts_written'],
                    $dept['passed'],
                    $dept['failed'],
                    number_format($dept['pass_rate'], 1) . '%',
                    number_format($dept['fail_rate'], 1) . '%',
                    $remark,
                ];
            }

            // Faculty totals
            $rows[] = [
                '', 'TOTAL (' . count($depts) . ' Departments)',
                '', '',
                $fac['courses_offered'],
                $fac['scripts_written'],
                $fac['passed'],
                $fac['failed'],
                number_format($fac['pass_rate'], 1) . '%',
                number_format($fac['fail_rate'], 1) . '%',
                '',
            ];

            $rows[] = ['']; // separator row
        }

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
                $lastCol = 'K';

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

                // Style the new Student Performance Summary block if present
                $studentSummaries = $this->data['student_performance_summaries'] ?? [];
                $row = 5;
                if (!empty($studentSummaries)) {
                    // Summary Title
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $row++;

                    // Summary Text
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'alignment' => ['wrapText' => true],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(30);
                    $row++;

                    // Table Label
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'italic' => true],
                    ]);
                    $row++;

                    // Table Header
                    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f8f9fa']],
                        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                    ]);
                    $row++;

                    // Table Data
                    $dataStartRow = $row;
                    $dataEndRow = $row + count($studentSummaries) - 1;
                    $sheet->getStyle("A{$dataStartRow}:G{$dataEndRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                    ]);
                    $row = $dataEndRow + 1;

                    // Table Footer (Totals)
                    $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e9ecef']],
                        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                    ]);
                    $row += 2; // skip blank row
                }

                // Now style each faculty block
                $faculties = $this->data['faculty_summaries'] ?? [];
                foreach ($faculties as $fac) {
                    $deptCount = count($fac['departments'] ?? []);

                    // Faculty title row
                    $this->writeSectionTitle($sheet, $row, $lastCol, $sheet->getCell("A{$row}")->getValue(), '2c3e50');
                    $row++;

                    // Faculty aggregate row
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'font' => ['italic' => true, 'color' => ['rgb' => '17a2b8']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e8f4f8']],
                    ]);
                    $row++;

                    // Department header
                    $this->styleHeaderRow($sheet, $row, 'A', $lastCol, '495057');
                    $row++;

                    // Department data rows
                    $dataStartRow = $row;
                    for ($i = 0; $i < $deptCount; $i++) {
                        // Color-code pass rate
                        $passCell = "I{$row}";
                        $val = floatval(str_replace('%', '', $sheet->getCell($passCell)->getValue()));
                        if ($val >= 70) {
                            $this->conditionalFill($sheet, $passCell, 'd4edda');
                        } elseif ($val >= 50) {
                            $this->conditionalFill($sheet, $passCell, 'fff3cd');
                        } else {
                            $this->conditionalFill($sheet, $passCell, 'f8d7da');
                        }
                        $row++;
                    }
                    if ($deptCount > 0) {
                        $this->styleDataRange($sheet, "A{$dataStartRow}:{$lastCol}" . ($row - 1));
                    }

                    // Totals row
                    $this->styleTotalsRow($sheet, $row, 'A', $lastCol, 'e8f4f8');
                    $row++;

                    // Separator
                    $row++;
                }

                $this->autoSizeColumns($sheet, $lastCol);
                $this->configurePrint($sheet, 'landscape');
            },
        ];
    }
}
