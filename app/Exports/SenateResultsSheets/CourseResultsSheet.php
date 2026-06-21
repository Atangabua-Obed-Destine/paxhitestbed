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

class CourseResultsSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    use SenateSheetHelpers;

    protected $data;
    protected $sectionRows   = [];
    protected $headerRows    = [];
    protected $totalRows     = [];
    protected $gradeColStart = 'N'; // depends on grade count

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Course Results';
    }

    public function array(): array
    {
        $facultyData = $this->data['faculty_course_data'] ?? [];
        $grades      = $this->data['grades'] ?? collect([]);
        $gradeNames  = $grades->pluck('title')->toArray();
        $rows = [];

        // Header rows
        $rows[] = [strtoupper($this->data['institution_name'] ?? config('app.name'))];
        $rows[] = ['SENATE RESULTS PREVIEW — DETAILED COURSE RESULTS'];
        $rows[] = [
            'Session: ' . ($this->data['session_label'] ?? '') .
            '  |  Semester: ' . ($this->data['semester_label'] ?? '') .
            '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A')
        ];
        $rows[] = [''];
        $currentRow = 5;

        foreach ($facultyData as $fac) {
            // Faculty section header
            $rows[] = ['FACULTY: ' . strtoupper($fac['name']) . ' (' . $fac['shortcode'] . ')'];
            $this->sectionRows[] = $currentRow;
            $currentRow++;

            foreach ($fac['departments'] ?? [] as $dept) {
                // Department sub-section
                $rows[] = ['  Department: ' . $dept['name'] . ' (' . $dept['shortcode'] . ')' . ($dept['head_name'] ? ' — HOD: ' . $dept['head_name'] : '')];
                $this->sectionRows[] = $currentRow;
                $currentRow++;

                // Column headers
                $header = [
                    '#', 'Course Code', 'Course Title', 'Credit Value', 'Type',
                    'Lecturer(s)', 'Coverage (%)',
                    'Registered', 'Examined', 'Passed', 'Failed',
                    'Pass Rate (%)', 'Avg Mark',
                ];
                foreach ($gradeNames as $g) {
                    $header[] = $g;
                }
                $rows[] = $header;
                $this->headerRows[] = $currentRow;
                $currentRow++;

                // Course data rows
                $sn = 1;
                $courses = $dept['courses'] ?? [];
                $deptReg = $deptExam = $deptPass = $deptFail = $deptMarksSum = 0;
                foreach ($courses as $c) {
                    $row = [
                        $sn++,
                        $c['code'],
                        $c['title'],
                        $c['credit_value'],
                        $c['type'] ?? '-',
                        $c['lecturers'] ?? '-',
                        is_numeric($c['coverage']) ? number_format($c['coverage'], 0) . '%' : $c['coverage'],
                        $c['candidates_registered'],
                        $c['candidates_examined'],
                        $c['passed'],
                        $c['failed'],
                        number_format($c['pass_rate'], 1) . '%',
                        number_format($c['average_marks'], 1),
                    ];

                    // Grade distribution columns
                    $gradeDist = $c['grade_distribution'] ?? [];
                    foreach ($gradeNames as $g) {
                        $row[] = $gradeDist[$g] ?? 0;
                    }

                    $rows[] = $row;
                    $currentRow++;

                    $deptReg      += $c['candidates_registered'];
                    $deptExam     += $c['candidates_examined'];
                    $deptPass     += $c['passed'];
                    $deptFail     += $c['failed'];
                    $deptMarksSum += $c['average_marks'] * $c['candidates_examined'];
                }

                // Department totals
                $deptAvg = $deptExam > 0 ? $deptMarksSum / $deptExam : 0;
                $deptPassRate = ($deptReg > 0) ? ($deptPass / $deptReg) * 100 : 0;
                $totalRow = [
                    '', 'DEPT TOTAL (' . count($courses) . ' courses)', '', '', '', '', '',
                    $deptReg, $deptExam, $deptPass, $deptFail,
                    number_format($deptPassRate, 1) . '%',
                    number_format($deptAvg, 1),
                ];
                foreach ($gradeNames as $g) {
                    $totalRow[] = ''; // blank for grade totals
                }
                $rows[] = $totalRow;
                $this->totalRows[] = $currentRow;
                $currentRow++;

                $rows[] = ['']; // separator
                $currentRow++;
            }
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
                $grades = $this->data['grades'] ?? collect([]);
                $totalCols = 13 + $grades->count();
                $lastCol = self::colLetter($totalCols);

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

                // Style section title rows (faculty & dept)
                foreach ($this->sectionRows as $r) {
                    $val = $sheet->getCell("A{$r}")->getValue();
                    $isFaculty = strpos($val, 'FACULTY:') !== false;
                    $bg = $isFaculty ? '2c3e50' : '495057';
                    $this->writeSectionTitle($sheet, $r, $lastCol, $val, $bg);
                }

                // Style header rows (column headers)
                foreach ($this->headerRows as $r) {
                    $this->styleHeaderRow($sheet, $r, 'A', $lastCol, '343a40');
                }

                // Style totals rows
                foreach ($this->totalRows as $r) {
                    $this->styleTotalsRow($sheet, $r, 'A', $lastCol, 'e8f4f8');
                }

                // Color-code pass rates in data rows
                $highestRow = $sheet->getHighestRow();
                for ($r = 5; $r <= $highestRow; $r++) {
                    if (in_array($r, $this->sectionRows) || in_array($r, $this->headerRows) || in_array($r, $this->totalRows)) {
                        continue;
                    }
                    $cellVal = $sheet->getCell("L{$r}")->getValue();
                    if ($cellVal && strpos($cellVal, '%') !== false) {
                        $num = floatval(str_replace('%', '', $cellVal));
                        if ($num >= 70) {
                            $this->conditionalFill($sheet, "L{$r}", 'd4edda');
                        } elseif ($num >= 50) {
                            $this->conditionalFill($sheet, "L{$r}", 'fff3cd');
                        } else {
                            $this->conditionalFill($sheet, "L{$r}", 'f8d7da');
                        }
                    }
                }

                $this->autoSizeColumns($sheet, $lastCol);
                $this->configurePrint($sheet, 'landscape');
            },
        ];
    }
}
