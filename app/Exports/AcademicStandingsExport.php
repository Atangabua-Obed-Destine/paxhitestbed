<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AcademicStandingsExport implements FromCollection, WithTitle, WithEvents
{
    protected array $data;
    protected int $detailHeaderRow = 0;
    protected int $detailFirstRow  = 0;
    protected int $detailLastRow   = 0;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Academic Standings';
    }

    public function collection()
    {
        $rows = new Collection();
        $lastColIdx = 15; // O column — matches detail table width

        $institution = $this->data['institution_name'] ?? config('app.name', 'Institution');
        $session     = $this->data['session_label'] ?? '-';
        $semester    = $this->data['semester_label'] ?? '-';
        $facultyLbl  = $this->data['faculty_label'] ?? 'All Faculties';
        $programLbl  = $this->data['program_label'] ?? 'All Programs';
        $standingLbl = $this->data['standing_label'] ?? 'All Standings';
        $generatedBy = $this->data['generated_by'] ?? 'System';

        // ── Report header (rows 1-3) ──
        $rows->push($this->padRow([strtoupper($institution)], $lastColIdx));
        $rows->push($this->padRow(['ACADEMIC STANDINGS REPORT'], $lastColIdx));
        $rows->push($this->padRow([
            'Session: ' . $session . '  |  Semester: ' . $semester .
            '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A') .
            '  |  By: ' . $generatedBy,
        ], $lastColIdx));
        $rows->push($this->padRow([''], $lastColIdx));

        // ── Filters block (rows 5-8) ──
        $rows->push($this->padRow(['APPLIED FILTERS'], $lastColIdx));
        $rows->push($this->padRow(['Faculty', $facultyLbl], $lastColIdx));
        $rows->push($this->padRow(['Program', $programLbl], $lastColIdx));
        $rows->push($this->padRow(['Standing', $standingLbl], $lastColIdx));
        $rows->push($this->padRow([''], $lastColIdx));

        // ── Summary block ──
        $summary         = $this->data['summary'] ?? [];
        $totalStudents   = $summary['total_students'] ?? 0;
        $avgGpa          = $summary['avg_gpa'] ?? 0;
        $highestGpa      = $summary['highest_gpa'] ?? 0;
        $lowestGpa       = $summary['lowest_gpa'] ?? 0;
        $totalCo         = $summary['total_co_courses'] ?? 0;
        $totalCoCredits  = $summary['total_co_credits'] ?? 0;

        $rows->push($this->padRow(['SUMMARY'], $lastColIdx));
        $rows->push($this->padRow(['Total Students', $totalStudents], $lastColIdx));
        $rows->push($this->padRow(['Average GPA', number_format($avgGpa, 2)], $lastColIdx));
        $rows->push($this->padRow(['Highest GPA', number_format($highestGpa, 2)], $lastColIdx));
        $rows->push($this->padRow(['Lowest GPA', number_format($lowestGpa, 2)], $lastColIdx));
        $rows->push($this->padRow(['Total Carry-Over Courses', $totalCo], $lastColIdx));
        $rows->push($this->padRow([
            'Total Carry-Over Credits',
            rtrim(rtrim(number_format($totalCoCredits, 1), '0'), '.'),
        ], $lastColIdx));
        $rows->push($this->padRow([''], $lastColIdx));

        // ── Standing distribution ──
        $rows->push($this->padRow(['STANDING DISTRIBUTION'], $lastColIdx));
        $rows->push($this->padRow(['Standing', 'Count', '% of Total'], $lastColIdx));
        $standingLabels = $this->data['standing_labels'] ?? [];
        $standingCounts = $this->data['standing_counts'] ?? [];
        foreach ($standingLabels as $code => $label) {
            $count = (int) ($standingCounts[$code] ?? 0);
            $pct   = $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0;
            $rows->push($this->padRow([$label, $count, $pct . '%'], $lastColIdx));
        }
        $rows->push($this->padRow([''], $lastColIdx));

        // ── Detail header ──
        $this->detailHeaderRow = $rows->count() + 1;
        $rows->push($this->padRow([
            'S/N', 'Matricule', 'Student Name', 'Faculty', 'Program',
            'Cr. Reg.', 'Cr. Earned',
            'Courses Reg.', 'Passed', 'Failed',
            '# Carry-Over', 'CO Cr.',
            'GPA', 'Standing', 'Previous Standing',
        ], $lastColIdx));

        // ── Detail rows ──
        $this->detailFirstRow = $rows->count() + 1;
        $sn = 1;
        foreach ($this->data['standings'] ?? [] as $s) {
            $rows->push($this->padRow([
                $sn++,
                $s['matricule'],
                $s['student_name'],
                $s['faculty'],
                $s['program'],
                $s['credits_registered'],
                $s['credits_earned'],
                $s['courses_registered'],
                $s['courses_passed'],
                $s['courses_failed'],
                $s['carry_over_courses'],
                $s['carry_over_credits'],
                number_format($s['gpa'], 2),
                $s['standing_label'],
                $s['previous_standing_label'],
            ], $lastColIdx));
        }
        $this->detailLastRow = $rows->count();

        return $rows;
    }

    private function padRow(array $row, int $length): array
    {
        while (count($row) < $length) $row[] = '';
        return $row;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = 'O'; // 15

                // Title rows ──────────────────────────────────────────────
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1a1a2e']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '2c3e50']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(22);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Section titles (merged banners) ─────────────────────────
                foreach ([5 => 'APPLIED FILTERS', 10 => 'SUMMARY', 18 => 'STANDING DISTRIBUTION'] as $row => $_unusedLabel) {
                    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'ffffff']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '34495e']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(22);
                }

                // Label/value rows for Filters (6-8) and Summary (11-16)
                foreach ([6, 7, 8, 11, 12, 13, 14, 15, 16] as $row) {
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '495057']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f1f3f5']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'dee2e6']]],
                        'alignment' => ['indent' => 1, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("B{$row}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'dee2e6']]],
                        'alignment' => ['indent' => 1, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Standing distribution header (row 19)
                $sheet->getStyle('A19:C19')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'ffffff']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '495057']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'aaaaaa']]],
                ]);
                $sheet->getRowDimension(19)->setRowHeight(20);

                // Standing distribution rows (20..20+count-1)
                $labelCount = count($this->data['standing_labels'] ?? []);
                $distEnd = 19 + $labelCount;
                if ($labelCount > 0) {
                    $sheet->getStyle("A20:C{$distEnd}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'dddddd']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                    ]);
                    // Apply color swatches to the standing label cells
                    $standingColors = [
                        'deans_list'            => 'd1e7dd',
                        'good'                  => 'd4edda',
                        'academic_warning'      => 'fff3cd',
                        'academic_probation'    => 'ffe5b4',
                        'recommended_dismissal' => 'f8d7da',
                    ];
                    $r = 20;
                    foreach ($this->data['standing_labels'] ?? [] as $code => $_label) {
                        $rgb = $standingColors[$code] ?? 'f8f9fa';
                        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
                        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                        $r++;
                    }
                }

                // Detail header row (dark blue, bold, white) ─────────────
                $hdr = $this->detailHeaderRow;
                $sheet->getStyle("A{$hdr}:{$lastCol}{$hdr}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'ffffff']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c3e50']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'aaaaaa']]],
                ]);
                $sheet->getRowDimension($hdr)->setRowHeight(32);

                // Detail data area ───────────────────────────────────────
                $first = $this->detailFirstRow;
                $last  = $this->detailLastRow;
                if ($last >= $first) {
                    $sheet->getStyle("A{$first}:{$lastCol}{$last}")->applyFromArray([
                        'font' => ['size' => 10],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'dddddd']]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    // Zebra striping
                    for ($r = $first; $r <= $last; $r++) {
                        if ((($r - $first) % 2) === 1) {
                            $sheet->getStyle("A{$r}:{$lastCol}{$r}")
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('f8f9fa');
                        }
                    }

                    // Per-row colour-coding on Standing column (N) & #CO column (K)
                    $rowIdx = 0;
                    foreach ($this->data['standings'] ?? [] as $s) {
                        $r = $first + $rowIdx++;
                        // Standing color
                        $standingColors = [
                            'deans_list'            => ['bg' => '198754', 'fg' => 'ffffff'],
                            'good'                  => ['bg' => '20c997', 'fg' => 'ffffff'],
                            'academic_warning'      => ['bg' => 'ffc107', 'fg' => '212529'],
                            'academic_probation'    => ['bg' => 'fd7e14', 'fg' => 'ffffff'],
                            'recommended_dismissal' => ['bg' => 'dc3545', 'fg' => 'ffffff'],
                        ];
                        $code = $s['standing'] ?? null;
                        if (isset($standingColors[$code])) {
                            $sheet->getStyle("N{$r}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => $standingColors[$code]['fg']]],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $standingColors[$code]['bg']]],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            ]);
                        }
                        // Carry-over highlight
                        if (($s['carry_over_courses'] ?? 0) > 0) {
                            $sheet->getStyle("K{$r}:L{$r}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => 'fd7e14']],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            ]);
                        }
                        // GPA: red if < 2.0, green if >= 3.5
                        $gpa = (float) ($s['gpa'] ?? 0);
                        if ($gpa >= 3.5) {
                            $sheet->getStyle("M{$r}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '198754']],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            ]);
                        } elseif ($gpa < 2.0) {
                            $sheet->getStyle("M{$r}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => 'dc3545']],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            ]);
                        } else {
                            $sheet->getStyle("M{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }

                        // Center-align numeric columns
                        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("F{$r}:L{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }

                    // Enable autofilter on detail table (no freeze pane — it was
                    // locking all meta rows at the top and preventing vertical scroll)
                    $sheet->setAutoFilter("A{$hdr}:{$lastCol}{$last}");
                }

                // Column widths ──────────────────────────────────────────
                $widths = [
                    'A' => 6,   // S/N
                    'B' => 18,  // Matricule
                    'C' => 30,  // Name
                    'D' => 22,  // Faculty
                    'E' => 26,  // Program
                    'F' => 10,  // Cr Reg
                    'G' => 11,  // Cr Earn
                    'H' => 12,  // Courses Reg
                    'I' => 9,   // Passed
                    'J' => 9,   // Failed
                    'K' => 13,  // #CO
                    'L' => 9,   // CO Cr
                    'M' => 9,   // GPA
                    'N' => 20,  // Standing
                    'O' => 20,  // Previous
                ];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }

                // Print setup
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setBottom(0.5);
                $sheet->getPageMargins()->setLeft(0.3);
                $sheet->getPageMargins()->setRight(0.3);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($hdr, $hdr);
            },
        ];
    }
}
