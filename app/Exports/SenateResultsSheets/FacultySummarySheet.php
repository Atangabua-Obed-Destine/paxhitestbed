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

                // Now style each faculty block
                $faculties = $this->data['faculty_summaries'] ?? [];
                $row = 5;
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
