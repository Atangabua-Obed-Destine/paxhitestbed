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

class SalaryBandsSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Salary Bands';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['SALARY BAND DISTRIBUTION'];
        $rows[] = ['Staff grouped by salary range showing tax concentration across income brackets.'];
        $rows[] = [''];

        // Headers
        $rows[] = ['Salary Range', 'Staff Count', '% of Staff', 'Total Basic Salary', 'Total Employee Tax', 'Total Employer Tax', 'Average Effective Rate', 'Visual Distribution'];

        $bands = $this->data['salary_bands'] ?? [];
        $totalStaff = 0;
        foreach ($bands as $band) {
            $totalStaff += $band['count'];
        }

        foreach ($bands as $label => $band) {
            $pctStaff = $totalStaff > 0 ? round(($band['count'] / $totalStaff) * 100, 1) : 0;
            $employeeTax = $band['total_employee_tax'] ?? $band['total_tax'] ?? 0;
            $employerTax = $band['total_employer_tax'] ?? 0;
            $avgRate = $band['total_salary'] > 0
                ? round(($employeeTax / $band['total_salary']) * 100, 2)
                : 0;
            $bar = str_repeat('█', (int)round($pctStaff / 2)) . ' ' . $pctStaff . '%';

            $rows[] = [
                $label,
                $band['count'],
                $pctStaff . '%',
                $band['total_salary'],
                $employeeTax,
                $employerTax,
                $avgRate . '%',
                $bar,
            ];
        }

        $totalSalary = 0;
        $totalEmpTax = 0;
        $totalEmprTax = 0;
        foreach ($bands as $band) {
            $totalSalary += $band['total_salary'];
            $totalEmpTax += $band['total_employee_tax'] ?? $band['total_tax'] ?? 0;
            $totalEmprTax += $band['total_employer_tax'] ?? 0;
        }

        $rows[] = [''];
        $rows[] = [
            'TOTAL',
            $totalStaff,
            '100%',
            $totalSalary,
            $totalEmpTax,
            $totalEmprTax,
            '',
            '',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $bandCount = count($this->data['salary_bands'] ?? []);
        $headerRow = 4;
        $dataStart = 5;
        $dataEnd = $dataStart + $bandCount - 1;
        $totalRow = $dataEnd + 2;

        // Title
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '4E73DF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '36B9CC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2C9DAF']]],
        ]);

        if ($bandCount > 0) {
            // Data area
            $sheet->getStyle("A{$dataStart}:H{$dataEnd}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
                'font' => ['size' => 9],
            ]);

            // Number formats
            $sheet->getStyle("D{$dataStart}:F{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("D{$dataStart}:F{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Zebra striping
            for ($r = $dataStart; $r <= $dataEnd; $r++) {
                if (($r - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$r}:H{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FAFB']],
                    ]);
                }
            }

            // Total row
            $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F6F8']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '36B9CC']]],
            ]);
            $sheet->getStyle("D{$totalRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->freezePane('A5');
            },
        ];
    }
}
