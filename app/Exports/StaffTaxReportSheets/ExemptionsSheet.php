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

class ExemptionsSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Exemptions';
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['STAFF TAX EXEMPTIONS'];
        $rows[] = ['Staff members with active tax exemptions — custom rates or full exclusions from specific taxes.'];
        $rows[] = [''];

        $exemptions = $this->data['exemption_details'] ?? collect();
        $rows[] = ['Total Active Exemptions: ' . $exemptions->count()];
        $rows[] = [''];

        // Headers
        $rows[] = ['#', 'Staff Name', 'Department', 'Tax Setting', 'Reason', 'Custom Rate (%)', 'Expires On', 'Status', 'Days Remaining'];

        if ($exemptions->count() > 0) {
            foreach ($exemptions as $idx => $ex) {
                $expiresOn = $ex->expires_at ? \Carbon\Carbon::parse($ex->expires_at)->format('M d, Y') : 'Never';
                $daysLeft = $ex->expires_at
                    ? max(0, \Carbon\Carbon::today()->diffInDays(\Carbon\Carbon::parse($ex->expires_at), false))
                    : '∞';

                $status = 'Active';
                if ($ex->expires_at && \Carbon\Carbon::parse($ex->expires_at)->isPast()) {
                    $status = 'Expired';
                } elseif ($ex->expires_at && $daysLeft <= 30) {
                    $status = 'Expiring Soon';
                }

                $rows[] = [
                    $idx + 1,
                    $ex->user->name ?? '-',
                    $ex->user->department->title ?? '-',
                    $ex->taxSetting->title ?? $ex->taxSetting->name ?? '-',
                    $ex->reason ?? '-',
                    $ex->custom_percentage ?? 'Full Exempt',
                    $expiresOn,
                    $status,
                    is_numeric($daysLeft) ? $daysLeft . ' days' : $daysLeft,
                ];
            }
        } else {
            $rows[] = ['', 'No active exemptions found.', '', '', '', '', '', '', ''];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $exemptions = $this->data['exemption_details'] ?? collect();
        $headerRow = 6;
        $dataStart = 7;
        $dataEnd = $dataStart + max($exemptions->count(), 1) - 1;

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

        // Count badge
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
        ]);

        // Header row
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E74A3B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C7392E']]],
        ]);

        if ($exemptions->count() > 0) {
            $sheet->getStyle("A{$dataStart}:I{$dataEnd}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
                'font' => ['size' => 9],
            ]);

            // Zebra + conditional status coloring
            $row = $dataStart;
            foreach ($exemptions as $ex) {
                // Zebra
                if (($row - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDF2F2']],
                    ]);
                }

                // Status color
                $expiresAt = $ex->expires_at ? \Carbon\Carbon::parse($ex->expires_at) : null;
                if ($expiresAt && $expiresAt->isPast()) {
                    $sheet->getStyle("H{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'E74A3B']],
                    ]);
                } elseif ($expiresAt && $expiresAt->diffInDays(\Carbon\Carbon::today()) <= 30) {
                    $sheet->getStyle("H{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'D48806']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E1']],
                    ]);
                } else {
                    $sheet->getStyle("H{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '1CC88A']],
                    ]);
                }

                $row++;
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
                $sheet->freezePane('A7');
            },
        ];
    }
}
