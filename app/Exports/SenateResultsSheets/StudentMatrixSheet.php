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
use PhpOffice\PhpSpreadsheet\Style\Border;

class StudentMatrixSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    use SenateSheetHelpers;

    protected $data;
    protected $facMatrix;
    protected $programBlocks = []; // [{headerRow1, headerRow2, dataStart, dataEnd, footerRow, totalCols, subjectCount}]
    protected $backToTopRows = [];
    protected $tocHeaderRow = null;
    protected $tocSubHeaderRow = null;
    protected $tocDataStartRow = null;
    protected $tocDataEndRow = null;

    public function __construct(array $data, array $facMatrix)
    {
        $this->data = $data;
        $this->facMatrix = $facMatrix;
    }

    public function title(): string
    {
        $name = 'Students — ' . substr($this->facMatrix['shortcode'] ?? 'FAC', 0, 20);
        return substr(preg_replace('/[\\[\\]\\*\\/\\\\\\?\\:]/', '', $name), 0, 31);
    }

    public function array(): array
    {
        $fac = $this->facMatrix;
        $programs = $fac['programs'] ?? [];
        $rows = [];

        // Header rows 1-4
        $rows[] = [strtoupper($this->data['institution_name'] ?? config('app.name'))];
        $rows[] = ['SENATE RESULTS PREVIEW — STUDENT RESULTS MATRIX — FACULTY: ' . strtoupper($fac['name'])];
        $rows[] = [
            'Session: ' . ($this->data['session_label'] ?? '') .
            '  |  Semester: ' . ($this->data['semester_label'] ?? '') .
            '  |  Faculty: ' . $fac['name'] . ' (' . $fac['shortcode'] . ')' .
            '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A')
        ];
        $rows[] = [''];
        $currentRow = 5;

        // ── TABLE OF CONTENTS (only if multiple programs) ──
        if (count($programs) > 1) {
            $rows[] = ['PROGRAMME INDEX (' . count($programs) . ' Programmes in this Faculty)'];
            $this->tocHeaderRow = $currentRow;
            $currentRow++;

            $rows[] = ['#', 'Programme', 'Degree', 'Department', 'Students', 'Courses', 'Pass Rate', '→ Jump To'];
            $this->tocSubHeaderRow = $currentRow;
            $currentRow++;

            $this->tocDataStartRow = $currentRow;
            foreach ($programs as $pIdx => $prog) {
                $overall = $prog['overall'] ?? [];
                $passRate = ($overall['total_students'] ?? 0) > 0
                    ? round(($overall['total_passed'] ?? 0) / ($overall['total_students'] ?? 1) * 100, 1) . '%'
                    : '-';
                $rows[] = [
                    $pIdx + 1,
                    $prog['title'] ?? '-',
                    $prog['degree'] ?? '-',
                    $prog['department'] ?? '-',
                    $overall['total_students'] ?? 0,
                    count($prog['subjects'] ?? []),
                    $passRate,
                    '▼ Click to jump',
                ];
                $currentRow++;
            }
            $this->tocDataEndRow = $currentRow - 1;

            // Empty separator after TOC
            $rows[] = [''];
            $rows[] = [''];
            $currentRow += 2;
        }

        foreach ($programs as $pIdx => $prog) {
            $subjects = $prog['subjects'] ?? [];
            $students = $prog['students'] ?? [];
            $cStats   = $prog['course_stats'] ?? [];
            $overall  = $prog['overall'] ?? [];
            $subCount = count($subjects);

            // Fixed columns: S/N, Matricule, Name, Section = 4
            // Per subject: Att, CA, EX, TOT, Grd = 5 each
            // Summary: TCR, TCE, QP, GPA, Pass, Fail, Resit#, ResitCr, CO#, COCr = 10
            $totalCols = 4 + ($subCount * 5) + 10;

            // ── Programme Title Row ──
            $progTitle = strtoupper($prog['title']) . ' (' . $prog['degree'] . ')'
                       . ' — Dept: ' . $prog['department']
                       . ' — Students: ' . $overall['total_students']
                       . ' | Courses: ' . $subCount
                       . ' | Pass: ' . $overall['total_passed']
                       . ' | Fail: ' . $overall['total_failed'];
            $rows[] = [$progTitle];
            $progTitleRow = $currentRow;
            $currentRow++;

            // ── Header Row 1: Subject group headers + Summary ──
            $h1 = ['', '', '', '']; // S/N, Mat, Name, Sect placeholders
            foreach ($subjects as $subj) {
                $h1[] = $subj['code'] . ' — ' . $subj['title'] . ' (CV:' . $subj['credit_hour'] . ')';
                $h1[] = ''; $h1[] = ''; $h1[] = ''; $h1[] = ''; // 4 empty for merge
            }
            $h1[] = 'SUMMARY';
            $h1[] = ''; $h1[] = ''; $h1[] = ''; $h1[] = ''; $h1[] = ''; $h1[] = ''; $h1[] = ''; $h1[] = ''; $h1[] = ''; // 9 empty for merge
            $rows[] = $h1;
            $headerRow1 = $currentRow;
            $currentRow++;

            // ── Header Row 2: Sub-column headers ──
            $h2 = ['S/N', 'Matricule', 'Student Name', 'Section'];
            foreach ($subjects as $subj) {
                $h2[] = 'Att';
                $h2[] = 'CA';
                $h2[] = 'EX';
                $h2[] = 'TOT';
                $h2[] = 'Grd';
            }
            $h2[] = 'TCR';
            $h2[] = 'TCE';
            $h2[] = 'QP';
            $h2[] = 'GPA';
            $h2[] = 'Pass';
            $h2[] = 'Fail';
            $h2[] = 'Resit#';
            $h2[] = 'ResitCr';
            $h2[] = 'CO#';
            $h2[] = 'COCr';
            $rows[] = $h2;
            $headerRow2 = $currentRow;
            $currentRow++;

            // ── Lecturer Info Row (optional) ──
            $lecRow = ['', '', 'Lecturer:', ''];
            foreach ($subjects as $subj) {
                $lecRow[] = $subj['lecturer'] ?? '-';
                $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = '';
            }
            $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = ''; $lecRow[] = '';
            $rows[] = $lecRow;
            $lecInfoRow = $currentRow;
            $currentRow++;

            // ── Data Rows ──
            $dataStart = $currentRow;
            foreach ($students as $stu) {
                $row = [
                    $stu['sn'],
                    $stu['matricule'],
                    $stu['name'],
                    $stu['section'] ?? '-',
                ];

                foreach ($subjects as $subj) {
                    $c = $stu['courses'][$subj['id']] ?? null;

                    if (!$c || !$c['registered']) {
                        // Not Registered
                        $row[] = 'NR'; $row[] = 'NR'; $row[] = 'NR'; $row[] = 'NR'; $row[] = 'NR';
                    } elseif ($c['is_not_submitted'] || $c['status'] === 'N/S') {
                        // Not Submitted
                        $att = is_numeric($c['attendance_marks']) ? number_format($c['attendance_marks'], 1) : ($c['attendance_marks'] ?? 'N/S');
                        $row[] = $att; $row[] = 'N/S'; $row[] = 'N/S'; $row[] = 'N/S'; $row[] = 'N/S';
                    } elseif ($c['is_absent'] || $c['status'] === 'ABS') {
                        // Absent
                        $row[] = '-'; $row[] = '-'; $row[] = '-'; $row[] = '-'; $row[] = 'ABS';
                    } else {
                        // Normal (P/F)
                        $att = is_numeric($c['attendance_marks']) ? number_format($c['attendance_marks'], 1) : ($c['attendance_marks'] ?? '-');
                        $ca  = $c['ca_absent'] ? 'ABS' : (is_numeric($c['ca_marks']) ? number_format($c['ca_marks'], 1) : ($c['ca_marks'] ?? '-'));
                        $ex  = $c['final_absent'] ? 'ABS' : (is_numeric($c['exam_marks']) ? number_format($c['exam_marks'], 1) : ($c['exam_marks'] ?? '-'));
                        $tot = is_numeric($c['total_marks']) ? number_format($c['total_marks'], 1) : ($c['total_marks'] ?? '-');
                        $grd = $c['grade'] ?? '-';
                        if (!empty($c['decision_code'])) {
                            $grd .= "\n[" . $c['decision_code'] . "]";
                        }

                        // Append zero-contribution indicator
                        if ($c['has_zero_contribution'] && is_numeric($c['total_marks'])) {
                            $tot .= ' ⚠';
                        }

                        $row[] = $att;
                        $row[] = $ca;
                        $row[] = $ex;
                        $row[] = $tot;
                        $row[] = $grd;
                    }
                }

                // Summary columns
                $sum = $stu['summary'];
                $row[] = $sum['total_credits_registered'];
                $row[] = $sum['total_credits_earned'];
                $row[] = number_format($sum['total_quality_points'], 1);
                $row[] = number_format($sum['gpa'], 2);
                $row[] = $sum['courses_passed'];
                $row[] = $sum['courses_failed'];
                $row[] = $sum['scheduled_resit_courses'];
                $row[] = number_format($sum['scheduled_resit_credits'], 1);
                $row[] = $sum['carry_over_courses'];
                $row[] = number_format($sum['carry_over_credits'], 1);

                $rows[] = $row;
                $currentRow++;
            }
            $dataEnd = $currentRow - 1;

            // ── Course Stats Footer ──
            $statsRow = ['', '', 'COURSE STATS', ''];
            foreach ($subjects as $subj) {
                $cs = $cStats[$subj['id']] ?? null;
                if ($cs) {
                    $statsRow[] = 'Reg:' . $cs['registered'];
                    $statsRow[] = 'Exm:' . $cs['examined'];
                    $statsRow[] = 'P:' . $cs['passed'];
                    $statsRow[] = 'F:' . $cs['failed'];
                    $statsRow[] = $cs['pass_rate'] . '%';
                } else {
                    $statsRow[] = '-'; $statsRow[] = '-'; $statsRow[] = '-'; $statsRow[] = '-'; $statsRow[] = '-';
                }
            }
            $statsRow[] = $overall['total_students'];
            $statsRow[] = '';
            $statsRow[] = '';
            $statsRow[] = '';
            $statsRow[] = $overall['total_passed'];
            $statsRow[] = $overall['total_failed'];
            $statsRow[] = '';
            $statsRow[] = '';
            $statsRow[] = '';
            $statsRow[] = '';
            $rows[] = $statsRow;
            $footerRow = $currentRow;
            $currentRow++;

            // Store block info for styling
            $this->programBlocks[] = [
                'progTitleRow' => $progTitleRow,
                'headerRow1'   => $headerRow1,
                'headerRow2'   => $headerRow2,
                'lecInfoRow'   => $lecInfoRow,
                'dataStart'    => $dataStart,
                'dataEnd'      => $dataEnd,
                'footerRow'    => $footerRow,
                'totalCols'    => $totalCols,
                'subjectCount' => $subCount,
                'subjects'     => $subjects,
                'students'     => $students,
                'progIndex'    => $pIdx,
                'progTitle'    => $prog['title'] ?? '',
            ];

            // "Back to Top" link row (only if multiple programs)
            if (count($fac['programs'] ?? []) > 1) {
                $rows[] = ['', '', '', '', '', '', '', '▲ Back to Programme Index'];
                $this->backToTopRows[] = $currentRow;
                $currentRow++;
            }

            // Separator between programs (3 blank rows for clear visual break)
            $rows[] = [''];
            $rows[] = [''];
            $rows[] = [''];
            $currentRow += 3;
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
                $fac = $this->facMatrix;
                $hasMultipleProgs = count($this->programBlocks) > 1;

                // Find the widest program block for header merges
                $maxCols = 10;
                foreach ($this->programBlocks as $blk) {
                    $maxCols = max($maxCols, $blk['totalCols']);
                }
                $lastCol = self::colLetter($maxCols);

                // ── Top header rows (1-4) ──
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

                // ── TABLE OF CONTENTS styling and hyperlinks ──
                if ($hasMultipleProgs && $this->tocHeaderRow) {
                    $tocHR = $this->tocHeaderRow;
                    $sheet->mergeCells("A{$tocHR}:H{$tocHR}");
                    $sheet->getStyle("A{$tocHR}:H{$tocHR}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'ffffff']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c3e50']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    ]);
                    $sheet->getRowDimension($tocHR)->setRowHeight(24);

                    // Sub-header row
                    $tocSR = $this->tocSubHeaderRow;
                    $sheet->getStyle("A{$tocSR}:H{$tocSR}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'ffffff']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '495057']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6c757d']]],
                    ]);

                    // TOC data rows with hyperlinks
                    for ($tocRow = $this->tocDataStartRow; $tocRow <= $this->tocDataEndRow; $tocRow++) {
                        $pIdx = $tocRow - $this->tocDataStartRow;
                        $blk = $this->programBlocks[$pIdx] ?? null;

                        // Zebra striping
                        $bgColor = ($pIdx % 2 === 0) ? 'f8f9fa' : 'ffffff';
                        $sheet->getStyle("A{$tocRow}:H{$tocRow}")->applyFromArray([
                            'font' => ['size' => 10],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'dee2e6']]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                        // Left-align programme name
                        $sheet->getStyle("B{$tocRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                        // Add internal hyperlink from "Jump To" column to program title row
                        if ($blk) {
                            $targetRow = $blk['progTitleRow'];
                            $sheetTitle = $this->title();
                            $sheet->getCell("H{$tocRow}")
                                ->getHyperlink()
                                ->setUrl("sheet://'{$sheetTitle}'!A{$targetRow}");
                            $sheet->getStyle("H{$tocRow}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '0066cc'], 'underline' => true, 'size' => 10],
                            ]);
                        }

                        // Color-code pass rate
                        $sheet->getStyle("G{$tocRow}")->applyFromArray([
                            'font' => ['bold' => true],
                        ]);
                    }

                    // Set TOC column widths
                    $sheet->getColumnDimension('A')->setWidth(5);
                    $sheet->getColumnDimension('B')->setWidth(30);
                    $sheet->getColumnDimension('C')->setWidth(10);
                    $sheet->getColumnDimension('D')->setWidth(20);
                    $sheet->getColumnDimension('E')->setWidth(10);
                    $sheet->getColumnDimension('F')->setWidth(10);
                    $sheet->getColumnDimension('G')->setWidth(10);
                    $sheet->getColumnDimension('H')->setWidth(16);
                }

                // ── Style each program block ──
                foreach ($this->programBlocks as $blkIdx => $blk) {
                    $tc = $blk['totalCols'];
                    $lc = self::colLetter($tc);
                    $subCount = $blk['subjectCount'];

                    // Programme title row — enhanced with programme number badge
                    $r = $blk['progTitleRow'];
                    $progLabel = 'PROGRAMME ' . ($blk['progIndex'] + 1) . ' OF ' . count($this->programBlocks) . ':  '
                        . $sheet->getCell("A{$r}")->getValue();
                    $sheet->setCellValue("A{$r}", $progLabel);
                    $this->writeSectionTitle($sheet, $r, $lc, $progLabel, '1a237e');
                    $sheet->getRowDimension($r)->setRowHeight(28);

                    // Header Row 1: merge subject group cols and summary
                    $r1 = $blk['headerRow1'];
                    // Merge S/N through Section (cols 1-4) as rowspan placeholder
                    $sheet->mergeCells("A{$r1}:A" . ($r1 + 1));
                    $sheet->mergeCells("B{$r1}:B" . ($r1 + 1));
                    $sheet->mergeCells("C{$r1}:C" . ($r1 + 1));
                    $sheet->mergeCells("D{$r1}:D" . ($r1 + 1));
                    $sheet->setCellValue("A{$r1}", 'S/N');
                    $sheet->setCellValue("B{$r1}", 'Matricule');
                    $sheet->setCellValue("C{$r1}", 'Student Name');
                    $sheet->setCellValue("D{$r1}", 'Section');

                    // Merge subject groups (5 cols each)
                    $colOffset = 5; // starts at column E (col 5)
                    foreach ($blk['subjects'] as $subj) {
                        $startLetter = self::colLetter($colOffset);
                        $endLetter = self::colLetter($colOffset + 4);
                        $sheet->mergeCells("{$startLetter}{$r1}:{$endLetter}{$r1}");
                        $sheet->setCellValue("{$startLetter}{$r1}",
                            $subj['code'] . "\n" . $subj['title'] . "\nCV: " . $subj['credit_hour']
                        );
                        $sheet->getStyle("{$startLetter}{$r1}")->getAlignment()->setWrapText(true);
                        $colOffset += 5;
                    }

                    // Merge summary group (10 cols)
                    $sumStart = self::colLetter($colOffset);
                    $sumEnd = self::colLetter($colOffset + 9);
                    $sheet->mergeCells("{$sumStart}{$r1}:{$sumEnd}{$r1}");
                    $sheet->setCellValue("{$sumStart}{$r1}", 'SUMMARY');

                    // Style both header rows
                    $this->styleHeaderRow($sheet, $r1, 'A', $lc, '343a40');
                    $r2 = $blk['headerRow2'];
                    $this->styleHeaderRow($sheet, $r2, 'A', $lc, '495057');

                    // Style summary sub-header colors
                    $sumStartCol = 4 + ($subCount * 5) + 1;
                    for ($ci = $sumStartCol; $ci <= $sumStartCol + 9; $ci++) {
                        $cl = self::colLetter($ci);
                        $sheet->getStyle("{$cl}{$r2}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('17a2b8');
                    }

                    // Lecturer info row
                    $lr = $blk['lecInfoRow'];
                    $sheet->getStyle("A{$lr}:{$lc}{$lr}")->applyFromArray([
                        'font' => ['italic' => true, 'size' => 8, 'color' => ['rgb' => '6c757d']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f8f9fa']],
                    ]);
                    // Merge lecturer info per subject group
                    $colOffset = 5;
                    foreach ($blk['subjects'] as $subj) {
                        $startLetter = self::colLetter($colOffset);
                        $endLetter = self::colLetter($colOffset + 4);
                        $sheet->mergeCells("{$startLetter}{$lr}:{$endLetter}{$lr}");
                        $colOffset += 5;
                    }

                    // Data rows
                    $ds = $blk['dataStart'];
                    $de = $blk['dataEnd'];
                    if ($de >= $ds) {
                        $this->styleDataRange($sheet, "A{$ds}:{$lc}{$de}");
                        $sheet->getStyle("A{$ds}:{$lc}{$de}")->applyFromArray([
                            'font' => ['size' => 9],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);

                        // Left-align name column
                        $sheet->getStyle("C{$ds}:C{$de}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                        // Color-code individual cells
                        foreach ($blk['students'] as $idx => $stu) {
                            $rowNum = $ds + $idx;
                            $hasFail = $stu['summary']['courses_failed'] > 0;

                            // Highlight entire row if student has failures
                            if ($hasFail) {
                                $sheet->getStyle("A{$rowNum}:{$lc}{$rowNum}")->getFill()
                                    ->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setRGB('fff3cd');
                            }

                            // Color-code GPA
                            $gpaCol = self::colLetter(4 + ($subCount * 5) + 4); // GPA column
                            $gpa = $stu['summary']['gpa'];
                            if ($gpa >= 2.0) {
                                $this->conditionalFill($sheet, "{$gpaCol}{$rowNum}", 'd4edda');
                            } else if ($gpa >= 1.0) {
                                $this->conditionalFill($sheet, "{$gpaCol}{$rowNum}", 'fff3cd');
                            } else if ($stu['summary']['courses_registered'] > 0) {
                                $this->conditionalFill($sheet, "{$gpaCol}{$rowNum}", 'f8d7da');
                            }

                            // Color-code grade cells per subject
                            $colOffset = 5;
                            foreach ($blk['subjects'] as $subj) {
                                $c = $stu['courses'][$subj['id']] ?? null;
                                $grdCol = self::colLetter($colOffset + 4); // Grade column

                                if ($c && $c['registered']) {
                                    if ($c['status'] === 'P') {
                                        $this->conditionalFill($sheet, "{$grdCol}{$rowNum}", 'd4edda');
                                    } elseif ($c['status'] === 'F') {
                                        $this->conditionalFill($sheet, "{$grdCol}{$rowNum}", 'f8d7da');
                                    } elseif ($c['status'] === 'ABS' || $c['is_absent']) {
                                        $this->conditionalFill($sheet, "{$grdCol}{$rowNum}", 'e2e3e5');
                                    } elseif ($c['status'] === 'N/S' || $c['is_not_submitted']) {
                                        $this->conditionalFill($sheet, "{$grdCol}{$rowNum}", 'fff3cd');
                                    }

                                    // Color-code total marks
                                    $totCol = self::colLetter($colOffset + 3);
                                    if ($c['status'] === 'P') {
                                        $sheet->getStyle("{$totCol}{$rowNum}")->getFont()->getColor()->setRGB('28a745');
                                    } elseif ($c['status'] === 'F') {
                                        $sheet->getStyle("{$totCol}{$rowNum}")->getFont()->getColor()->setRGB('dc3545');
                                        $sheet->getStyle("{$totCol}{$rowNum}")->getFont()->setBold(true);
                                    }
                                }

                                $colOffset += 5;
                            }

                            // Color-code pass/fail count columns
                            $passCol = self::colLetter(4 + ($subCount * 5) + 5);
                            $failCol = self::colLetter(4 + ($subCount * 5) + 6);
                            $resitCountCol = self::colLetter(4 + ($subCount * 5) + 7);
                            $resitCreditCol = self::colLetter(4 + ($subCount * 5) + 8);
                            $carryOverCountCol = self::colLetter(4 + ($subCount * 5) + 9);
                            $carryOverCreditCol = self::colLetter(4 + ($subCount * 5) + 10);
                            if ($stu['summary']['courses_passed'] > 0) {
                                $this->conditionalFill($sheet, "{$passCol}{$rowNum}", 'd4edda');
                            }
                            if ($stu['summary']['courses_failed'] > 0) {
                                $this->conditionalFill($sheet, "{$failCol}{$rowNum}", 'f8d7da');
                            }
                            if (($stu['summary']['scheduled_resit_courses'] ?? 0) > 0) {
                                $this->conditionalFill($sheet, "{$resitCountCol}{$rowNum}", 'e7f1ff');
                                $this->conditionalFill($sheet, "{$resitCreditCol}{$rowNum}", 'e7f1ff');
                            }
                            if (($stu['summary']['carry_over_courses'] ?? 0) > 0) {
                                $this->conditionalFill($sheet, "{$carryOverCountCol}{$rowNum}", 'fff0e1');
                                $this->conditionalFill($sheet, "{$carryOverCreditCol}{$rowNum}", 'fff0e1');
                            }
                        }
                    }

                    // Footer row
                    $fr = $blk['footerRow'];
                    $this->styleTotalsRow($sheet, $fr, 'A', $lc, 'e8f4f8');
                    $sheet->getStyle("A{$fr}:{$lc}{$fr}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 8],
                    ]);

                    // Add subject borders (vertical separator every 5 cols)
                    $colOffset = 5;
                    for ($s = 0; $s < $subCount; $s++) {
                        $borderCol = self::colLetter($colOffset);
                        $borderRange = "{$borderCol}{$r1}:{$borderCol}{$fr}";
                        $sheet->getStyle($borderRange)->applyFromArray([
                            'borders' => [
                                'left' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '333333']],
                            ],
                        ]);
                        $colOffset += 5;
                    }
                    // Summary group border
                    $sumBorderCol = self::colLetter(4 + ($subCount * 5) + 1);
                    $sheet->getStyle("{$sumBorderCol}{$r1}:{$sumBorderCol}{$fr}")->applyFromArray([
                        'borders' => [
                            'left' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '17a2b8']],
                        ],
                    ]);

                    // Column widths (set per block to handle different subject counts)
                    if ($blkIdx === 0) {
                        $sheet->getColumnDimension('A')->setWidth(5);   // S/N
                        $sheet->getColumnDimension('B')->setWidth(14);  // Matricule
                        $sheet->getColumnDimension('C')->setWidth(22);  // Name
                        $sheet->getColumnDimension('D')->setWidth(8);   // Section
                    }

                    // Set subject sub-column widths
                    $colOffset = 5;
                    for ($s = 0; $s < $subCount; $s++) {
                        $sheet->getColumnDimension(self::colLetter($colOffset))->setWidth(5.5);     // Att
                        $sheet->getColumnDimension(self::colLetter($colOffset + 1))->setWidth(5.5); // CA
                        $sheet->getColumnDimension(self::colLetter($colOffset + 2))->setWidth(5.5); // EX
                        $sheet->getColumnDimension(self::colLetter($colOffset + 3))->setWidth(7);   // TOT
                        $sheet->getColumnDimension(self::colLetter($colOffset + 4))->setWidth(5);   // Grd
                        $colOffset += 5;
                    }
                    // Summary column widths
                    $sheet->getColumnDimension(self::colLetter($colOffset))->setWidth(5.5);     // TCR
                    $sheet->getColumnDimension(self::colLetter($colOffset + 1))->setWidth(5.5); // TCE
                    $sheet->getColumnDimension(self::colLetter($colOffset + 2))->setWidth(6.5); // QP
                    $sheet->getColumnDimension(self::colLetter($colOffset + 3))->setWidth(6.5); // GPA
                    $sheet->getColumnDimension(self::colLetter($colOffset + 4))->setWidth(5.5); // Pass
                    $sheet->getColumnDimension(self::colLetter($colOffset + 5))->setWidth(5.5); // Fail
                    $sheet->getColumnDimension(self::colLetter($colOffset + 6))->setWidth(7);   // Resit#
                    $sheet->getColumnDimension(self::colLetter($colOffset + 7))->setWidth(8);   // ResitCr
                    $sheet->getColumnDimension(self::colLetter($colOffset + 8))->setWidth(6.5); // CO#
                    $sheet->getColumnDimension(self::colLetter($colOffset + 9))->setWidth(8);   // COCr
                }

                // ── "Back to Top" hyperlinks ──
                if ($hasMultipleProgs && $this->tocHeaderRow) {
                    $sheetTitle = $this->title();
                    foreach ($this->backToTopRows as $btrRow) {
                        $sheet->getCell("H{$btrRow}")
                            ->getHyperlink()
                            ->setUrl("sheet://'{$sheetTitle}'!A{$this->tocHeaderRow}");
                        $sheet->getStyle("A{$btrRow}:H{$btrRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e8f4f8']],
                        ]);
                        $sheet->getStyle("H{$btrRow}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => '0066cc'], 'underline' => true, 'size' => 10],
                        ]);
                    }
                }

                // ── Freeze panes: first program's data area (or TOC if multiple) ──
                if (!empty($this->programBlocks)) {
                    $firstBlk = $this->programBlocks[0];
                    $sheet->freezePane('E' . $firstBlk['dataStart']);
                }

                $this->configurePrint($sheet, 'landscape');
            },
        ];
    }
}
