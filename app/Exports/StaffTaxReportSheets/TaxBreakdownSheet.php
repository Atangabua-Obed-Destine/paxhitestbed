<?php

namespace App\Exports\StaffTaxReportSheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TaxBreakdownSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Tax Breakdown';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['TAX TYPE BREAKDOWN SUMMARY'];
        $rows[] = ['Aggregate employee and employer contributions by each tax type.'];
        $rows[] = [''];

        // Headers
        $rows[] = ['Tax Name', 'Type', 'Employee Total', 'Employer Total', 'Combined Total', '% of Total Tax', 'Applied To'];

        $grandEmployeeTotal = $this->data['grand_totals']['employee_tax_total'] ?? 0;
        $grandEmployerTotal = $this->data['grand_totals']['employer_tax_total'] ?? 0;
        $grandCombined = $grandEmployeeTotal + $grandEmployerTotal;

        // Tax groups
        foreach ($this->data['tax_groups'] as $tg) {
            $empTotal = 0;
            $emprTotal = 0;
            foreach ($this->data['staff_rows'] as $sr) {
                $empTotal += $sr['groups'][$tg->id]['employee'] ?? 0;
                $emprTotal += $sr['groups'][$tg->id]['employer'] ?? 0;
            }
            $combined = $empTotal + $emprTotal;
            $pct = $grandCombined > 0 ? round(($combined / $grandCombined) * 100, 1) : 0;
            $type = $tg->is_progressive ? 'Progressive' : 'Flat Rate';
            $appliedTo = count($this->data['staff_rows']) . ' staff';

            $rows[] = [$tg->title, '📊 Tax Group (' . $type . ')', $empTotal, $emprTotal, $combined, $pct . '%', $appliedTo];
        }

        // Standalone taxes
        foreach ($this->data['standalone_taxes'] as $st) {
            $empTotal = 0;
            $emprTotal = 0;
            foreach ($this->data['staff_rows'] as $sr) {
                $empTotal += $sr['standalone'][$st->id]['employee'] ?? 0;
                $emprTotal += $sr['standalone'][$st->id]['employer'] ?? 0;
            }
            $combined = $empTotal + $emprTotal;
            $pct = $grandCombined > 0 ? round(($combined / $grandCombined) * 100, 1) : 0;
            $appliedTo = count($this->data['staff_rows']) . ' staff';

            $typeLabel = $st->is_dependent ? '🔗 Dependent' : '📋 Standalone';
            $rows[] = [$st->title, $typeLabel, $empTotal, $emprTotal, $combined, $pct . '%', $appliedTo];
        }

        // Grand total row
        $rows[] = [''];
        $rows[] = ['GRAND TOTAL', '', $grandEmployeeTotal, $grandEmployerTotal, $grandCombined, '100%', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $taxCount = count($this->data['tax_groups']) + count($this->data['standalone_taxes']);
        $headerRow = 4;
        $dataStart = 5;
        $dataEnd = $dataStart + $taxCount - 1;
        $totalRow = $dataEnd + 2;

        // Title
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '4E73DF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F6C23E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D4A532']]],
        ]);

        if ($taxCount > 0) {
            $sheet->getStyle("A{$dataStart}:G{$dataEnd}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
                'font' => ['size' => 9],
            ]);

            $sheet->getStyle("C{$dataStart}:E{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("C{$dataStart}:E{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);

            // Color employee vs employer columns
            $sheet->getStyle("C{$dataStart}:C{$dataEnd}")->applyFromArray([
                'font' => ['color' => ['rgb' => 'E74A3B']],
            ]);
            $sheet->getStyle("D{$dataStart}:D{$dataEnd}")->applyFromArray([
                'font' => ['color' => ['rgb' => 'D48806']],
            ]);

            // Zebra
            for ($r = $dataStart; $r <= $dataEnd; $r++) {
                if (($r - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFDF2']],
                    ]);
                }
            }

            // Total
            $sheet->getStyle("A{$totalRow}:G{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'F6C23E']]],
            ]);
            $sheet->getStyle("C{$totalRow}:E{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                foreach (range('A', 'G') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->freezePane('A5');
            },
        ];
    }
}
