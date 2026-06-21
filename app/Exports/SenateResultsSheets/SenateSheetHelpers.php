<?php

namespace App\Exports\SenateResultsSheets;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Shared styling helpers for all Senate Results Preview sheets.
 */
trait SenateSheetHelpers
{
    /**
     * Convert 1-based column number to Excel letter (1=A, 27=AA, etc.)
     */
    protected static function colLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $mod = ($col - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $col = (int)(($col - $mod) / 26);
        }
        return $letter;
    }

    /**
     * Write the standard institution header block (rows 1-4) on a sheet.
     * Returns the next available row number.
     */
    protected function writeHeader(Worksheet $sheet, string $sheetTitle, string $lastCol): int
    {
        $institutionName = $this->data['institution_name'] ?? config('app.name', 'Institution');
        $sessionLabel    = $this->data['session_label'] ?? '';
        $semesterLabel   = $this->data['semester_label'] ?? '';

        // Row 1: Institution
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', strtoupper($institutionName));
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1a1a2e']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 2: Report type
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'SENATE RESULTS PREVIEW — ' . strtoupper($sheetTitle));
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '2c3e50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 3: Session / Semester / Generated
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A3',
            'Session: ' . $sessionLabel . '  |  Semester: ' . $semesterLabel
            . '  |  Generated: ' . now()->format('l, F d, Y \a\t h:i A')
            . '  |  By: ' . (auth()->user()->name ?? 'System')
        );
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 4: blank separator
        $sheet->getRowDimension(4)->setRowHeight(6);

        return 5; // next available row
    }

    /**
     * Apply header row styling.
     */
    protected function styleHeaderRow(Worksheet $sheet, int $row, string $firstCol, string $lastCol, string $bgColor = '2c3e50'): void
    {
        $range = "{$firstCol}{$row}:{$lastCol}{$row}";
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'aaaaaa']],
            ],
        ]);
    }

    /**
     * Apply data area borders and alignment.
     */
    protected function styleDataRange(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'cccccc']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    /**
     * Apply totals row styling.
     */
    protected function styleTotalsRow(Worksheet $sheet, int $row, string $firstCol, string $lastCol, string $bgColor = 'e8f4f8'): void
    {
        $range = "{$firstCol}{$row}:{$lastCol}{$row}";
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => [
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '333333']],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '333333']],
            ],
        ]);
    }

    /**
     * Apply conditional fill to a cell.
     */
    protected function conditionalFill(Worksheet $sheet, string $cell, string $color): void
    {
        $sheet->getStyle($cell)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($color);
    }

    /**
     * Auto-size columns A through the given last column.
     */
    protected function autoSizeColumns(Worksheet $sheet, string $lastCol): void
    {
        $col = 'A';
        while ($col !== $lastCol) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        $sheet->getColumnDimension($lastCol)->setAutoSize(true);
    }

    /**
     * Set sheet print orientation and margins.
     */
    protected function configurePrint(Worksheet $sheet, string $orientation = 'landscape'): void
    {
        $sheet->getPageSetup()->setOrientation(
            $orientation === 'landscape'
                ? \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
                : \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT
        );
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.5);
        $sheet->getPageMargins()->setBottom(0.5);
        $sheet->getPageMargins()->setLeft(0.4);
        $sheet->getPageMargins()->setRight(0.4);
    }

    /**
     * Section title row (merged, colored background).
     */
    protected function writeSectionTitle(Worksheet $sheet, int $row, string $lastCol, string $text, string $bgColor = '34495e'): void
    {
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", $text);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }
}
