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

class EffectiveRatesSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Effective Rates';
    }

    public function array(): array
    {
        $rows = [];

        // Title rows
        $rows[] = ['EFFECTIVE TAX RATE ANALYSIS'];
        $rows[] = ['The effective rate shows what percentage of basic salary is deducted as employee tax.'];
        $rows[] = [
            'Min Rate: ' . $this->data['min_effective_rate'] . '%',
            '',
            'Avg Rate: ' . $this->data['avg_effective_rate'] . '%',
            '',
            'Max Rate: ' . $this->data['max_effective_rate'] . '%',
        ];
        $rows[] = [''];

        // Headers
        $rows[] = ['#', 'Staff Name', 'Department', 'Designation', 'Basic Salary', 'Employee Tax', 'Employer Tax', 'Net Salary', 'Effective Rate (%)', 'Rate Category'];

        // Data
        foreach ($this->data['staff_rows'] as $index => $sr) {
            $rate = $sr['basic_salary'] > 0
                ? round(($sr['employee_tax_total'] / $sr['basic_salary']) * 100, 2)
                : 0;

            $category = $rate < 10 ? 'Low (< 10%)' : ($rate < 20 ? 'Medium (10-20%)' : 'High (> 20%)');

            $rows[] = [
                $index + 1,
                $sr['user']->name,
                $sr['user']->department->title ?? '-',
                $sr['user']->designation->title ?? '-',
                $sr['basic_salary'],
                $sr['employee_tax_total'],
                $sr['employer_tax_total'],
                $sr['net_salary'],
                $rate,
                $category,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $staffCount = count($this->data['staff_rows']);
        $headerRow = 5;
        $dataStart = 6;
        $dataEnd = $dataStart + $staffCount - 1;

        // Title
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '4E73DF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Stats row
        $sheet->getStyle('A3:E3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8ECF6']],
        ]);

        // Header row
        $sheet->getStyle("A{$headerRow}:J{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4E73DF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B5EC2']]],
        ]);

        if ($staffCount > 0) {
            // Data area
            $sheet->getStyle("A{$dataStart}:J{$dataEnd}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
                'font' => ['size' => 9],
            ]);

            // Number formats
            $sheet->getStyle("E{$dataStart}:H{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("E{$dataStart}:H{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $sheet->getStyle("I{$dataStart}:I{$dataEnd}")->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle("I{$dataStart}:I{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Zebra striping
            for ($r = $dataStart; $r <= $dataEnd; $r++) {
                if (($r - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$r}:J{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FC']],
                    ]);
                }
            }

            // Conditional coloring for rate category
            for ($r = $dataStart; $r <= $dataEnd; $r++) {
                $rate = $sheet->getCell("I{$r}")->getValue();
                $rateVal = is_numeric($rate) ? (float)$rate : 0;
                if ($rateVal < 10) {
                    $color = '1CC88A'; $bgColor = 'E6F9F0';
                } elseif ($rateVal < 20) {
                    $color = 'D48806'; $bgColor = 'FFF8E1';
                } else {
                    $color = 'E74A3B'; $bgColor = 'FDE8E5';
                }
                $sheet->getStyle("I{$r}:J{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => $color]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                ]);
            }
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->freezePane('A6');
                $sheet->getRowDimension(5)->setRowHeight(25);
            },
        ];
    }
}
