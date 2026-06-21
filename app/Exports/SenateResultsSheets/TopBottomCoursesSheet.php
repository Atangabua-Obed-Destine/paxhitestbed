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

class TopBottomCoursesSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    use SenateSheetHelpers;

    protected $data;
    protected $bottomSectionRow = 0;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Top & Bottom Courses';
    }

    public function array(): array
    {
        $topCourses    = $this->data['top_courses'] ?? [];
        $bottomCourses = $this->data['bottom_courses'] ?? [];
        $rows = [];

        // Header
        $rows[] = [strtoupper($this->data['institution_name'] ?? config('app.name'))];
        $rows[] = ['SENATE RESULTS PREVIEW — TOP & BOTTOM PERFORMING COURSES'];
        $rows[] = [
            'Session: ' . ($this->data['session_label'] ?? '') .
            '  |  Semester: ' . ($this->data['semester_label'] ?? '') .
            '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A')
        ];
        $rows[] = [''];

        // ── Section 1: Top Performing Courses ──
        $rows[] = ['TOP 10 PERFORMING COURSES (Highest Pass Rates)'];
        $currentRow = 6;

        $rows[] = [
            '#', 'Course Code', 'Course Title', 'Faculty', 'Department',
            'Credit', 'Registered', 'Examined', 'Passed', 'Failed',
            'Pass Rate (%)', 'Average Mark', 'Lecturer(s)',
        ];
        $currentRow++;

        foreach ($topCourses as $i => $c) {
            $rows[] = [
                $i + 1,
                $c['course_code'] ?? $c['code'] ?? '',
                $c['course_title'] ?? $c['title'] ?? '',
                $c['faculty'] ?? '-',
                $c['department'] ?? '-',
                $c['credit_hour'] ?? $c['credit_value'] ?? '',
                $c['total_scripts'] ?? $c['candidates_registered'] ?? 0,
                $c['total_scripts'] ?? $c['candidates_examined'] ?? 0,
                $c['passed_scripts'] ?? $c['passed'] ?? 0,
                $c['failed_scripts'] ?? $c['failed'] ?? 0,
                number_format($c['pass_rate'] ?? 0, 1) . '%',
                number_format($c['average_marks'] ?? 0, 1),
                $c['lecturers'] ?? '-',
            ];
            $currentRow++;
        }
        $rows[] = [''];
        $currentRow++;

        // ── Section 2: Bottom Performing Courses ──
        $this->bottomSectionRow = $currentRow;
        $rows[] = ['BOTTOM 10 PERFORMING COURSES (Lowest Pass Rates)'];
        $currentRow++;

        $rows[] = [
            '#', 'Course Code', 'Course Title', 'Faculty', 'Department',
            'Credit', 'Registered', 'Examined', 'Passed', 'Failed',
            'Pass Rate (%)', 'Average Mark', 'Lecturer(s)',
        ];
        $currentRow++;

        foreach ($bottomCourses as $i => $c) {
            $rows[] = [
                $i + 1,
                $c['course_code'] ?? $c['code'] ?? '',
                $c['course_title'] ?? $c['title'] ?? '',
                $c['faculty'] ?? '-',
                $c['department'] ?? '-',
                $c['credit_hour'] ?? $c['credit_value'] ?? '',
                $c['total_scripts'] ?? $c['candidates_registered'] ?? 0,
                $c['total_scripts'] ?? $c['candidates_examined'] ?? 0,
                $c['passed_scripts'] ?? $c['passed'] ?? 0,
                $c['failed_scripts'] ?? $c['failed'] ?? 0,
                number_format($c['pass_rate'] ?? 0, 1) . '%',
                number_format($c['average_marks'] ?? 0, 1),
                $c['lecturers'] ?? '-',
            ];
            $currentRow++;
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
                $lastCol = 'M';
                $topCourses    = $this->data['top_courses'] ?? [];
                $bottomCourses = $this->data['bottom_courses'] ?? [];

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

                // Top section
                $this->writeSectionTitle($sheet, 5, $lastCol, '  TOP 10 PERFORMING COURSES (Highest Pass Rates)', '28a745');
                $this->styleHeaderRow($sheet, 6, 'A', $lastCol, '2e7d32');
                $topDataStart = 7;
                $topDataEnd = $topDataStart + count($topCourses) - 1;
                if (count($topCourses) > 0) {
                    $this->styleDataRange($sheet, "A{$topDataStart}:{$lastCol}{$topDataEnd}");
                    // Green tint for high pass rate rows
                    for ($r = $topDataStart; $r <= $topDataEnd; $r++) {
                        $this->conditionalFill($sheet, "K{$r}", 'd4edda');
                    }
                }

                // Bottom section
                $bRow = $this->bottomSectionRow;
                $this->writeSectionTitle($sheet, $bRow, $lastCol, '  BOTTOM 10 PERFORMING COURSES (Lowest Pass Rates)', 'dc3545');
                $bHeaderRow = $bRow + 1;
                $this->styleHeaderRow($sheet, $bHeaderRow, 'A', $lastCol, 'c62828');
                $bDataStart = $bHeaderRow + 1;
                $bDataEnd = $bDataStart + count($bottomCourses) - 1;
                if (count($bottomCourses) > 0) {
                    $this->styleDataRange($sheet, "A{$bDataStart}:{$lastCol}{$bDataEnd}");
                    // Red tint for low pass rate rows
                    for ($r = $bDataStart; $r <= $bDataEnd; $r++) {
                        $val = floatval(str_replace('%', '', $sheet->getCell("K{$r}")->getValue()));
                        if ($val < 50) {
                            $this->conditionalFill($sheet, "K{$r}", 'f8d7da');
                        } else {
                            $this->conditionalFill($sheet, "K{$r}", 'fff3cd');
                        }
                    }
                }

                $this->autoSizeColumns($sheet, $lastCol);
                $this->configurePrint($sheet, 'landscape');
            },
        ];
    }
}
