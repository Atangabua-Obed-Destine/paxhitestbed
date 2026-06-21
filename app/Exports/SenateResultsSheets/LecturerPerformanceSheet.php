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

class LecturerPerformanceSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    use SenateSheetHelpers;

    protected $data;
    protected $summaryEndRow = 0;
    protected $detailSections = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Lecturer Performance';
    }

    public function array(): array
    {
        $lecturers = $this->data['lecturer_performance'] ?? [];
        $rows = [];

        // Header rows
        $rows[] = [strtoupper($this->data['institution_name'] ?? config('app.name'))];
        $rows[] = ['SENATE RESULTS PREVIEW — LECTURER PERFORMANCE INDEX'];
        $rows[] = [
            'Session: ' . ($this->data['session_label'] ?? '') .
            '  |  Semester: ' . ($this->data['semester_label'] ?? '') .
            '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A')
        ];
        $rows[] = [''];

        // ── Section 1: Performance Rating Summary ──
        $rows[] = ['PERFORMANCE RATING DISTRIBUTION'];
        $currentRow = 6;

        $outstanding = $good = $satisfactory = $needs = $critical = 0;
        foreach ($lecturers as $l) {
            $rating = $l['aggregate']['rating'] ?? '';
            if ($rating === 'Outstanding') $outstanding++;
            elseif ($rating === 'Good') $good++;
            elseif ($rating === 'Satisfactory') $satisfactory++;
            elseif ($rating === 'Needs Improvement') $needs++;
            elseif ($rating === 'Critical') $critical++;
        }
        $total = max(count($lecturers), 1);

        $rows[] = ['Rating', 'Count', 'Percentage', 'Description'];
        $currentRow++;
        $ratings = [
            ['Outstanding', $outstanding, '≥ 80% pass rate, high coverage'],
            ['Good', $good, '70–79% pass rate'],
            ['Satisfactory', $satisfactory, '60–69% pass rate'],
            ['Needs Improvement', $needs, '50–59% pass rate'],
            ['Critical', $critical, '< 50% pass rate'],
        ];
        foreach ($ratings as $r) {
            $rows[] = [$r[0], $r[1], number_format(($r[1] / $total) * 100, 1) . '%', $r[2]];
            $currentRow++;
        }
        $rows[] = ['Total Lecturers', count($lecturers), '100%', ''];
        $currentRow++;
        $this->summaryEndRow = $currentRow;
        $rows[] = [''];
        $currentRow++;

        // ── Section 2: Comprehensive Ranked Table ──
        $rows[] = ['COMPREHENSIVE LECTURER RANKING'];
        $currentRow++;
        $rows[] = [
            'Rank', 'Lecturer Name', 'Faculty', 'Department',
            'Courses', 'Total Scripts', 'Passed', 'Failed',
            'Pass Rate (%)', 'Average Mark', 'Rating',
        ];
        $currentRow++;

        foreach ($lecturers as $l) {
            $rows[] = [
                $l['rank'],
                $l['name'],
                $l['faculty'],
                $l['department'],
                $l['aggregate']['course_count'],
                $l['aggregate']['total_scripts'],
                $l['aggregate']['passed'],
                $l['aggregate']['failed'],
                number_format($l['aggregate']['pass_rate'], 1) . '%',
                number_format($l['aggregate']['average_mark'], 1),
                $l['aggregate']['rating'],
            ];
            $currentRow++;
        }
        $rows[] = [''];
        $currentRow++;

        // ── Section 3: Per-Lecturer Course Breakdown ──
        $rows[] = ['DETAILED COURSE BREAKDOWN PER LECTURER'];
        $currentRow++;

        foreach ($lecturers as $l) {
            $lecName = $l['rank'] . '. ' . $l['name'] . ' — ' . $l['faculty'] . ' / ' . $l['department']
                     . ' — Rating: ' . $l['aggregate']['rating']
                     . ' (Pass Rate: ' . number_format($l['aggregate']['pass_rate'], 1) . '%)';
            $rows[] = [$lecName];
            $this->detailSections[] = $currentRow;
            $currentRow++;

            $rows[] = [
                '#', 'Course Code', 'Course Title', 'Credit',
                'Examined', 'Passed', 'Failed', 'Pass Rate (%)',
                'Average Mark', 'Coverage (%)',
            ];
            $currentRow++;

            $sn = 1;
            foreach ($l['courses'] ?? [] as $c) {
                $rows[] = [
                    $sn++,
                    $c['code'],
                    $c['title'],
                    $c['credit'],
                    $c['examined'],
                    $c['passed'],
                    $c['failed'],
                    number_format($c['pass_rate'], 1) . '%',
                    number_format($c['average'], 1),
                    is_numeric($c['coverage']) ? number_format($c['coverage'], 0) . '%' : ($c['coverage'] ?? '-'),
                ];
                $currentRow++;
            }
            $rows[] = [''];
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

                // Rating summary section
                $this->writeSectionTitle($sheet, 5, $lastCol, '  PERFORMANCE RATING DISTRIBUTION', '6c757d');
                $this->styleHeaderRow($sheet, 6, 'A', 'D', '495057');
                $this->styleDataRange($sheet, 'A7:D11');
                $this->styleTotalsRow($sheet, 12, 'A', 'D', 'e8f4f8');

                // Color-code rating rows
                $ratingColors = [7 => 'd4edda', 8 => 'd1ecf1', 9 => 'cce5ff', 10 => 'fff3cd', 11 => 'f8d7da'];
                foreach ($ratingColors as $r => $c) {
                    $this->conditionalFill($sheet, "A{$r}", $c);
                }

                // Main ranking section
                $lecturers = $this->data['lecturer_performance'] ?? [];
                $rankSectionStart = 14;
                $this->writeSectionTitle($sheet, $rankSectionStart, $lastCol, '  COMPREHENSIVE LECTURER RANKING', '2c3e50');

                $headerRow = $rankSectionStart + 1;
                $this->styleHeaderRow($sheet, $headerRow, 'A', $lastCol, '343a40');

                $dataStart = $headerRow + 1;
                $dataEnd = $dataStart + count($lecturers) - 1;
                if (count($lecturers) > 0) {
                    $this->styleDataRange($sheet, "A{$dataStart}:{$lastCol}{$dataEnd}");

                    // Color-code pass rates and ratings
                    for ($r = $dataStart; $r <= $dataEnd; $r++) {
                        $passVal = floatval(str_replace('%', '', $sheet->getCell("I{$r}")->getValue()));
                        if ($passVal >= 70) {
                            $this->conditionalFill($sheet, "I{$r}", 'd4edda');
                        } elseif ($passVal >= 50) {
                            $this->conditionalFill($sheet, "I{$r}", 'fff3cd');
                        } else {
                            $this->conditionalFill($sheet, "I{$r}", 'f8d7da');
                        }

                        $rating = $sheet->getCell("K{$r}")->getValue();
                        $rColor = match ($rating) {
                            'Outstanding'       => 'd4edda',
                            'Good'              => 'd1ecf1',
                            'Satisfactory'      => 'cce5ff',
                            'Needs Improvement' => 'fff3cd',
                            'Critical'          => 'f8d7da',
                            default             => 'ffffff',
                        };
                        $this->conditionalFill($sheet, "K{$r}", $rColor);
                    }
                }

                // Detail sections
                foreach ($this->detailSections as $r) {
                    $this->writeSectionTitle($sheet, $r, $lastCol, $sheet->getCell("A{$r}")->getValue(), '34495e');
                    // Style sub-header
                    $subHeader = $r + 1;
                    $this->styleHeaderRow($sheet, $subHeader, 'A', 'J', '6c757d');
                }

                // Set auto filter on main ranking
                if (count($lecturers) > 0) {
                    $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$dataEnd}");
                }

                $this->autoSizeColumns($sheet, $lastCol);
                $this->configurePrint($sheet, 'landscape');
            },
        ];
    }
}
