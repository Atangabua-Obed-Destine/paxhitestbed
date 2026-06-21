<?php

namespace App\Exports\SenateResultsSheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class OverviewSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    use SenateSheetHelpers;

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Overview';
    }

    public function array(): array
    {
        $kpis       = $this->data['kpis'] ?? [];
        $pub        = $this->data['publishing_summary'] ?? [];
        $standings  = $this->data['standing_distribution'] ?? [];
        $sLabels    = $this->data['standingLabels'] ?? [];
        $faculties  = $this->data['faculty_summaries'] ?? [];
        $inst       = $this->data['institution_name'] ?? config('app.name', 'Institution');
        $session    = $this->data['session_label'] ?? '';
        $semester   = $this->data['semester_label'] ?? '';

        $rows = [];

        // Rows 1-4: Header (handled by AfterSheet for styling)
        $rows[] = [strtoupper($inst)];
        $rows[] = ['SENATE RESULTS PREVIEW — COMPREHENSIVE OVERVIEW'];
        $rows[] = [
            'Session: ' . $session . '  |  Semester: ' . $semester
            . '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A')
            . '  |  By: ' . (auth()->user()->name ?? 'System')
        ];
        $rows[] = ['']; // blank

        // ── Section 1: Key Performance Indicators ──
        $rows[] = ['KEY PERFORMANCE INDICATORS (KPIs)'];
        $rows[] = ['Metric', 'Value', '', 'Metric', 'Value'];
        $rows[] = [
            'Total Scripts Written', $kpis['total_scripts'] ?? 0,
            '', 'Unique Students', $kpis['unique_students'] ?? 0,
        ];
        $rows[] = [
            'Scripts Passed', $kpis['passed_scripts'] ?? 0,
            '', 'Unique Courses', $kpis['unique_courses'] ?? 0,
        ];
        $rows[] = [
            'Scripts Failed', $kpis['failed_scripts'] ?? 0,
            '', 'Institution Average Mark', number_format($kpis['average_mark'] ?? 0, 1),
        ];
        $rows[] = [
            'Pass Rate (%)', number_format($kpis['pass_rate'] ?? 0, 1) . '%',
            '', 'Fail Rate (%)', number_format($kpis['fail_rate'] ?? 0, 1) . '%',
        ];
        $rows[] = ['']; // blank

        // ── Section 2: Publishing Readiness ──
        $rows[] = ['PUBLISHING READINESS'];
        $rows[] = ['Status', 'Count', 'Percentage'];
        $total = max($pub['total'] ?? 1, 1);
        $statuses = [
            ['Published', $pub['published'] ?? 0],
            ['Approved', $pub['approved'] ?? 0],
            ['Checked', $pub['checked'] ?? 0],
            ['Submitted', $pub['submitted'] ?? 0],
            ['Draft', $pub['draft'] ?? 0],
        ];
        foreach ($statuses as $s) {
            $rows[] = [$s[0], $s[1], number_format(($s[1] / $total) * 100, 1) . '%'];
        }
        $rows[] = ['Total Course Sections', $pub['total'] ?? 0, '100%'];
        $rows[] = ['Overall Readiness', number_format($pub['readiness'] ?? 0, 1) . '%', ''];
        $rows[] = ['']; // blank

        // ── Section 3: Academic Standing Distribution ──
        $standingsComputed = $this->data['standings_computed'] ?? false;
        $rows[] = ['ACADEMIC STANDING DISTRIBUTION'];
        if ($standingsComputed && !empty($standings)) {
            $rows[] = ['Standing', 'Count', 'Percentage'];
            $totalStandings = array_sum($standings);
            $totalStandings = max($totalStandings, 1);
            foreach ($standings as $key => $count) {
                $label = $sLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                $rows[] = [$label, $count, number_format(($count / $totalStandings) * 100, 1) . '%'];
            }
            $rows[] = ['Total Students Classified', $totalStandings, '100%'];
        } else {
            $rows[] = ['Academic standings have not been computed for this period.'];
        }
        $rows[] = ['']; // blank

        // ── Section 4: Faculty Comparison Summary ──
        $rows[] = ['FACULTY COMPARISON SUMMARY'];
        $rows[] = [
            '#', 'Faculty', 'Code', 'Programmes', 'Students',
            'Scripts Written', 'Passed', 'Failed', 'Pass Rate (%)',
            'Fail Rate (%)', 'Courses Offered', 'Departments',
        ];
        $sn = 1;
        $totProg = $totStu = $totScr = $totP = $totF = $totCrs = 0;
        foreach ($faculties as $f) {
            $rows[] = [
                $sn++,
                $f['name'],
                $f['shortcode'],
                $f['program_count'],
                $f['student_count'],
                $f['scripts_written'],
                $f['passed'],
                $f['failed'],
                number_format($f['pass_rate'], 1) . '%',
                number_format($f['fail_rate'], 1) . '%',
                $f['courses_offered'],
                count($f['departments'] ?? []),
            ];
            $totProg += $f['program_count'];
            $totStu  += $f['student_count'];
            $totScr  += $f['scripts_written'];
            $totP    += $f['passed'];
            $totF    += $f['failed'];
            $totCrs  += $f['courses_offered'];
        }
        $overallRate = $totScr > 0 ? ($totP / $totScr) * 100 : 0;
        $rows[] = [
            '', 'INSTITUTION TOTAL', '',
            $totProg, $totStu, $totScr, $totP, $totF,
            number_format($overallRate, 1) . '%',
            number_format(100 - $overallRate, 1) . '%',
            $totCrs, count($faculties),
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Styles applied via AfterSheet
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'L';

                // —— Header rows ——
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

                // —— Section 1: KPIs (rows 5-11) ——
                $this->writeSectionTitle($sheet, 5, $lastCol, '  KEY PERFORMANCE INDICATORS (KPIs)', '2c3e50');

                $this->styleHeaderRow($sheet, 6, 'A', 'E', '495057');
                $this->styleDataRange($sheet, 'A7:E10');

                // Color code the pass/fail cells
                $kpis = $this->data['kpis'] ?? [];
                $passRate = $kpis['pass_rate'] ?? 0;
                if ($passRate >= 70) {
                    $this->conditionalFill($sheet, 'B10', 'd4edda');
                } elseif ($passRate >= 50) {
                    $this->conditionalFill($sheet, 'B10', 'fff3cd');
                } else {
                    $this->conditionalFill($sheet, 'B10', 'f8d7da');
                }

                // —— Section 2: Publishing Readiness (rows 12-20) ——
                $this->writeSectionTitle($sheet, 12, $lastCol, '  PUBLISHING READINESS', '17a2b8');
                $this->styleHeaderRow($sheet, 13, 'A', 'C', '138496');
                $this->styleDataRange($sheet, 'A14:C19');
                // Readiness row
                $this->styleTotalsRow($sheet, 19, 'A', 'C', 'e8f4f8');
                $this->styleTotalsRow($sheet, 20, 'A', 'C', 'd1ecf1');

                // —— Section 3: Academic Standing (rows 22+) ——
                $standingsComputed = $this->data['standings_computed'] ?? false;
                $standings = $this->data['standing_distribution'] ?? [];
                $row = 22;
                $this->writeSectionTitle($sheet, $row, $lastCol, '  ACADEMIC STANDING DISTRIBUTION', '6c757d');
                if ($standingsComputed && !empty($standings)) {
                    $this->styleHeaderRow($sheet, $row + 1, 'A', 'C', '495057');
                    $endData = $row + 1 + count($standings);
                    $this->styleDataRange($sheet, 'A' . ($row + 2) . ':C' . $endData);
                    $this->styleTotalsRow($sheet, $endData + 1, 'A', 'C', 'e8f4f8');
                    $facStart = $endData + 3;
                } else {
                    $facStart = $row + 3;
                }

                // —— Section 4: Faculty Comparison ——
                $faculties = $this->data['faculty_summaries'] ?? [];
                $this->writeSectionTitle($sheet, $facStart, $lastCol, '  FACULTY COMPARISON SUMMARY', '2c3e50');
                $this->styleHeaderRow($sheet, $facStart + 1, 'A', $lastCol, '343a40');
                $dataEnd = $facStart + 1 + count($faculties);

                if (count($faculties) > 0) {
                    $this->styleDataRange($sheet, 'A' . ($facStart + 2) . ":{$lastCol}" . $dataEnd);
                    $this->styleTotalsRow($sheet, $dataEnd + 1, 'A', $lastCol, 'e8f4f8');

                    // Color pass rate cells in faculty rows
                    for ($i = $facStart + 2; $i <= $dataEnd; $i++) {
                        $val = $sheet->getCell("I{$i}")->getValue();
                        $numVal = floatval(str_replace('%', '', $val));
                        if ($numVal >= 70) {
                            $this->conditionalFill($sheet, "I{$i}", 'd4edda');
                        } elseif ($numVal >= 50) {
                            $this->conditionalFill($sheet, "I{$i}", 'fff3cd');
                        } else {
                            $this->conditionalFill($sheet, "I{$i}", 'f8d7da');
                        }
                    }

                    $sheet->setAutoFilter("A" . ($facStart + 1) . ":{$lastCol}" . ($dataEnd + 1));
                }

                // —— Auto-size & print config ——
                $this->autoSizeColumns($sheet, $lastCol);
                $this->configurePrint($sheet, 'landscape');
            },
        ];
    }
}
