<?php

namespace App\Exports\StaffTaxReportSheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Color;

class DistributionSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Tax Distribution';
    }

    public function array(): array
    {
        $taxGroups = $this->data['tax_groups'];
        $standaloneTaxes = $this->data['standalone_taxes'];
        $staffRows = $this->data['staff_rows'];
        $grandTotals = $this->data['grand_totals'];
        $setting = $this->data['setting'];

        $rows = [];

        // Row 1: Institution name
        $rows[] = [$setting->title ?? 'PAX HIGHER INSTITUTE (PAXHI)'];
        // Row 2: Report title
        $rows[] = ['STAFF TAX DISTRIBUTION REPORT'];
        // Row 3: Generated timestamp & filters
        $rows[] = [
            'Generated: ' . $this->data['generated_at']->format('l, F d, Y \a\t h:i:s A')
            . '  |  By: ' . ($this->data['generated_by']->name ?? 'System')
            . '  |  Ref: TAX-RPT-' . $this->data['generated_at']->format('Ymd-His')
        ];
        // Row 4: Applied filters
        $rows[] = ['Filters: ' . ($this->data['applied_filters'] ?? 'None')];
        // Row 5: blank
        $rows[] = [''];

        // Row 6: KPI Summary row
        $rows[] = [
            'Staff Count', $this->data['staff_count'],
            '', 'Total Gross Salary', $grandTotals['basic_salary'],
            '', 'Employee Tax', $grandTotals['employee_tax_total'],
            '', 'Employer Tax', $grandTotals['employer_tax_total'],
            '', 'Net Pay', $grandTotals['net_salary'],
            '', 'Avg Eff. Rate', $this->data['avg_effective_rate'] . '%',
        ];
        // Row 7: blank
        $rows[] = [''];

        // Row 8: Column Headers
        $headers = ['#', 'Staff Name', 'Department', 'Designation', 'Basic Salary'];
        foreach ($taxGroups as $group) {
            $headers[] = $group->title . ' (Empl.)';
            $headers[] = $group->title . ' (Empr.)';
        }
        foreach ($standaloneTaxes as $tax) {
            $label = $tax->title . ($tax->is_dependent ? ' [Dep]' : '');
            $headers[] = $label . ' (Empl.)';
            $headers[] = $label . ' (Empr.)';
        }
        $headers = array_merge($headers, [
            'Total Employee Tax',
            'Total Employer Tax',
            'Net Salary',
            'Total Cost to Institution',
            'Effective Rate (%)',
        ]);
        $rows[] = $headers;

        // Data rows (starting row 9)
        foreach ($staffRows as $index => $sr) {
            $row = [
                $index + 1,
                $sr['user']->name . ($sr['exemption_count'] > 0 ? ' [E]' : ''),
                $sr['user']->department->title ?? '-',
                $sr['user']->designation->title ?? '-',
                $sr['basic_salary'],
            ];

            foreach ($taxGroups as $group) {
                $g = $sr['groups'][$group->id] ?? ['employee' => 0, 'employer' => 0];
                $row[] = $g['employee'];
                $row[] = $g['employer'];
            }

            foreach ($standaloneTaxes as $tax) {
                $s = $sr['standalone'][$tax->id] ?? ['employee' => 0, 'employer' => 0, 'is_exempt' => false];
                $row[] = $s['is_exempt'] ? 'EXEMPT' : $s['employee'];
                $row[] = $s['is_exempt'] ? 'EXEMPT' : $s['employer'];
            }

            $rate = $sr['basic_salary'] > 0
                ? round(($sr['employee_tax_total'] / $sr['basic_salary']) * 100, 2)
                : 0;

            $row[] = $sr['employee_tax_total'];
            $row[] = $sr['employer_tax_total'];
            $row[] = $sr['net_salary'];
            $row[] = $sr['total_cost'];
            $row[] = $rate;

            $rows[] = $row;
        }

        // Totals row
        $totalsRow = ['', 'TOTALS (' . $this->data['staff_count'] . ' staff)', '', '', $grandTotals['basic_salary']];
        foreach ($taxGroups as $group) {
            $totalsRow[] = $grandTotals['groups'][$group->id]['employee'];
            $totalsRow[] = $grandTotals['groups'][$group->id]['employer'];
        }
        foreach ($standaloneTaxes as $tax) {
            $totalsRow[] = $grandTotals['standalone'][$tax->id]['employee'];
            $totalsRow[] = $grandTotals['standalone'][$tax->id]['employer'];
        }
        $totalsRow[] = $grandTotals['employee_tax_total'];
        $totalsRow[] = $grandTotals['employer_tax_total'];
        $totalsRow[] = $grandTotals['net_salary'];
        $totalsRow[] = $grandTotals['total_cost'];
        $totalsRow[] = $this->data['avg_effective_rate'];
        $rows[] = $totalsRow;

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $taxGroups = $this->data['tax_groups'];
        $standaloneTaxes = $this->data['standalone_taxes'];
        $numTaxCols = (count($taxGroups) + count($standaloneTaxes)) * 2; // empl + empr per tax
        $totalCols = 5 + $numTaxCols + 5; // fixed left + tax cols + summary cols
        $lastCol = self::colLetter($totalCols);
        $headerRow = 8;
        $dataStartRow = 9;
        $staffCount = count($this->data['staff_rows']);
        $totalsRow = $dataStartRow + $staffCount;

        // Row 1: Institution title
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1a1a2e']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 2: Report title
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '4e73df']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 3: Timestamp
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '666666'], 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 4: Filters
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => 'd48806']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBE6']],
        ]);

        // Row 6: KPI summary — light blue background
        $sheet->mergeCells("A6:{$lastCol}6");
        // We won't merge, we already have the KPI pairs across columns
        // Instead, style the whole row
        $sheet->getStyle("A6:{$lastCol}6")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8ECF6']],
        ]);
        // un-merge because the KPI data is across columns
        $sheet->unmergeCells("A6:{$lastCol}6");

        // Row 8: Column headers
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4e73df']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3b5ec2']]],
        ]);

        // Color group tax header columns (darker blue for groups, orange for standalone)
        $colIndex = 6; // starts after Basic Salary (col 5)
        foreach ($taxGroups as $group) {
            $emplCol = self::colLetter($colIndex);
            $emprCol = self::colLetter($colIndex + 1);
            $sheet->getStyle("{$emplCol}{$headerRow}:{$emprCol}{$headerRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3b5ec2']],
            ]);
            $colIndex += 2;
        }
        foreach ($standaloneTaxes as $tax) {
            $emplCol = self::colLetter($colIndex);
            $emprCol = self::colLetter($colIndex + 1);
            $sheet->getStyle("{$emplCol}{$headerRow}:{$emprCol}{$headerRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D48806']],
            ]);
            $colIndex += 2;
        }

        // Summary header columns (dark)
        $summaryStart = self::colLetter($colIndex);
        $summaryEnd = self::colLetter($colIndex + 4);
        $sheet->getStyle("{$summaryStart}{$headerRow}:{$summaryEnd}{$headerRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c3e50']],
        ]);

        // Data rows styling
        if ($staffCount > 0) {
            $dataEndRow = $dataStartRow + $staffCount - 1;

            // All data borders
            $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataEndRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['size' => 9],
            ]);

            // Number format for salary/tax columns (col 5 onwards, except last col which is %)
            $salaryCol = self::colLetter(5);
            $secondLastCol = self::colLetter($totalCols - 1);
            $rateCol = self::colLetter($totalCols);
            $sheet->getStyle("{$salaryCol}{$dataStartRow}:{$secondLastCol}{$dataEndRow}")
                ->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("{$rateCol}{$dataStartRow}:{$rateCol}{$dataEndRow}")
                ->getNumberFormat()->setFormatCode('0.00"%"');

            // Right-align numeric columns
            $sheet->getStyle("{$salaryCol}{$dataStartRow}:{$lastCol}{$dataEndRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);

            // Zebra striping
            for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
                if (($r - $dataStartRow) % 2 === 1) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FC']],
                    ]);
                }
            }

            // Employee Tax total column — red text
            $emplTaxCol = self::colLetter($colIndex);
            $sheet->getStyle("{$emplTaxCol}{$dataStartRow}:{$emplTaxCol}{$dataEndRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'E74A3B']],
            ]);

            // Employer Tax total column — orange text
            $emprTaxCol = self::colLetter($colIndex + 1);
            $sheet->getStyle("{$emprTaxCol}{$dataStartRow}:{$emprTaxCol}{$dataEndRow}")->applyFromArray([
                'font' => ['color' => ['rgb' => 'D48806']],
            ]);

            // Net Salary column — green text
            $netCol = self::colLetter($colIndex + 2);
            $sheet->getStyle("{$netCol}{$dataStartRow}:{$netCol}{$dataEndRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1CC88A']],
            ]);

            // Effective rate — conditional color per cell
            for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
                $cellValue = $sheet->getCell("{$rateCol}{$r}")->getValue();
                $rateVal = is_numeric($cellValue) ? (float)$cellValue : 0;
                $color = $rateVal < 10 ? '1CC88A' : ($rateVal < 20 ? 'F6C23E' : 'E74A3B');
                $sheet->getStyle("{$rateCol}{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => $color]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }
        }

        // Totals row
        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8ECF6']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '4E73DF']],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '4E73DF']],
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Totals number format
        $salaryCol = self::colLetter(5);
        $secondLastCol = self::colLetter($totalCols - 1);
        $rateCol = self::colLetter($totalCols);
        $sheet->getStyle("{$salaryCol}{$totalsRow}:{$secondLastCol}{$totalsRow}")
            ->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("{$rateCol}{$totalsRow}")
            ->getNumberFormat()->setFormatCode('0.00"%"');
        $sheet->getStyle("{$salaryCol}{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Auto-size first 4 columns (text columns)
                for ($i = 1; $i <= 4; $i++) {
                    $sheet->getColumnDimension(self::colLetter($i))->setAutoSize(true);
                }

                // Set fixed width for numeric columns
                $taxGroups = $this->data['tax_groups'];
                $standaloneTaxes = $this->data['standalone_taxes'];
                $numTaxCols = (count($taxGroups) + count($standaloneTaxes)) * 2;
                $totalCols = 5 + $numTaxCols + 5;

                for ($i = 5; $i <= $totalCols; $i++) {
                    $sheet->getColumnDimension(self::colLetter($i))->setWidth(14);
                }

                // Freeze panes: freeze header row and first 4 columns
                $sheet->freezePane('E9');

                // Set row height for header
                $sheet->getRowDimension(8)->setRowHeight(30);

                // Print setup
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }

    /**
     * Convert 1-based column index to Excel letter (1=A, 2=B, ..., 27=AA, etc.)
     */
    public static function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }
}
