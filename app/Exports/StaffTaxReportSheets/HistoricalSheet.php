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
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;

class HistoricalSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Historical Trend';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['HISTORICAL PAYROLL TAX TREND'];
        $rows[] = ['Last 6 months of processed payrolls showing salary and tax trends over time.'];
        $rows[] = [''];

        // Headers
        $rows[] = [
            'Month',
            'Payrolls Processed',
            'Total Gross Salary',
            'Employee Tax',
            'Employer Tax',
            'Total Tax (Combined)',
            'Net Salary',
            'Avg Tax Rate (%)',
            'MoM Change (%)',
        ];

        $prevGross = 0;
        $historical = $this->data['historical_data'] ?? [];

        foreach ($historical as $idx => $h) {
            $combinedTax = ($h['total_tax'] ?? 0) + ($h['employer_tax'] ?? 0);
            $avgRate = $h['total_gross'] > 0
                ? round(($h['total_tax'] / $h['total_gross']) * 100, 2)
                : 0;

            $momChange = '-';
            if ($idx > 0 && $prevGross > 0) {
                $momChange = round((($h['total_gross'] - $prevGross) / $prevGross) * 100, 1) . '%';
            }
            $prevGross = $h['total_gross'];

            $rows[] = [
                $h['label'],
                $h['count'],
                $h['total_gross'],
                $h['total_tax'] ?? 0,
                $h['employer_tax'] ?? 0,
                $combinedTax,
                $h['total_net'] ?? 0,
                $avgRate,
                $momChange,
            ];
        }

        // Summary row
        $totalGross = array_sum(array_column($historical, 'total_gross'));
        $totalEmpTax = array_sum(array_map(function ($h) { return $h['total_tax'] ?? 0; }, $historical));
        $totalEmprTax = array_sum(array_map(function ($h) { return $h['employer_tax'] ?? 0; }, $historical));
        $totalNet = array_sum(array_map(function ($h) { return $h['total_net'] ?? 0; }, $historical));
        $totalCount = array_sum(array_column($historical, 'count'));

        $rows[] = [''];
        $rows[] = [
            '6-MONTH TOTAL',
            $totalCount,
            $totalGross,
            $totalEmpTax,
            $totalEmprTax,
            $totalEmpTax + $totalEmprTax,
            $totalNet,
            $totalGross > 0 ? round(($totalEmpTax / $totalGross) * 100, 2) : 0,
            '',
        ];

        // Sparkline-like visual trend row
        $rows[] = [''];
        $rows[] = ['TREND VISUALIZATION (Gross Salary)'];
        $maxGross = max(1, max(array_column($historical, 'total_gross')));
        foreach ($historical as $h) {
            $barLen = (int)round(($h['total_gross'] / $maxGross) * 30);
            $bar = str_repeat('█', $barLen);
            $rows[] = [$h['label'], $bar, number_format($h['total_gross'], 0)];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $histCount = count($this->data['historical_data'] ?? []);
        $headerRow = 4;
        $dataStart = 5;
        $dataEnd = $dataStart + $histCount - 1;
        $totalRow = $dataEnd + 2;
        $trendTitleRow = $totalRow + 2;
        $trendStart = $trendTitleRow + 1;
        $trendEnd = $trendStart + $histCount - 1;

        // Title
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '4E73DF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1CC88A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '17A673']]],
        ]);

        if ($histCount > 0) {
            // Data
            $sheet->getStyle("A{$dataStart}:I{$dataEnd}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
                'font' => ['size' => 9],
            ]);

            // Number formats
            $sheet->getStyle("C{$dataStart}:G{$dataEnd}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("C{$dataStart}:G{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Zebra
            for ($r = $dataStart; $r <= $dataEnd; $r++) {
                if (($r - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$r}:I{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F9F0']],
                    ]);
                }
            }

            // Total row
            $sheet->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4EDDA']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1CC88A']]],
            ]);
            $sheet->getStyle("C{$totalRow}:G{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

            // Trend visualization section
            $sheet->mergeCells("A{$trendTitleRow}:C{$trendTitleRow}");
            $sheet->getStyle("A{$trendTitleRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1a1a2e']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8ECF6']],
            ]);

            if ($trendStart <= $trendEnd) {
                $sheet->getStyle("A{$trendStart}:A{$trendEnd}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                ]);
                $sheet->getStyle("B{$trendStart}:B{$trendEnd}")->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '1CC88A']],
                ]);
                $sheet->getStyle("C{$trendStart}:C{$trendEnd}")->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '666666']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
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
                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->freezePane('A5');
                $sheet->getRowDimension(4)->setRowHeight(25);
            },
        ];
    }
}
