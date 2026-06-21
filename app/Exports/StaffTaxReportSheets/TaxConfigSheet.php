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

class TaxConfigSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected $data;
    protected $tablePositions = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Tax Configuration';
    }

    public function array(): array
    {
        $rows = [];
        $currentRow = 1;

        $rows[] = ['TAX CONFIGURATION REFERENCE'];
        $rows[] = ['Active tax rules and brackets as of ' . ($this->data['effective_date'] ?? 'today') . '.'];
        $rows[] = [''];
        $currentRow = 4;

        // Tax Groups
        foreach ($this->data['tax_groups'] as $group) {
            $titleRow = $currentRow;
            $type = $group->is_progressive ? 'Progressive' : 'Flat Rate';
            $rows[] = ['TAX GROUP: ' . strtoupper($group->title), '', 'Type: ' . $type, '', 'Code: ' . ($group->code ?? '-')];
            $currentRow++;

            // Bracket headers
            $rows[] = ['#', 'Bracket Name', 'Min Amount', 'Max Amount', 'Tax Type', 'Rate (%)', 'Paid By', 'Employer Rate (%)', 'Non-Taxable Amount'];
            $headerPos = $currentRow;
            $currentRow++;

            $this->tablePositions[] = ['title' => $titleRow, 'header' => $headerPos, 'dataStart' => $currentRow, 'dataCount' => 0];
            $tIdx = count($this->tablePositions) - 1;

            foreach ($group->brackets as $bIdx => $bracket) {
                $rows[] = [
                    $bIdx + 1,
                    $bracket->title ?? ('Bracket ' . ($bIdx + 1)),
                    $bracket->min_amount,
                    $bracket->max_amount,
                    $bracket->tax_type == 1 ? 'Percentage' : 'Fixed Amount',
                    $bracket->percentange ?? 0,
                    ucfirst($bracket->paid_by ?? 'employee'),
                    $bracket->employer_percentage ?? 0,
                    $bracket->max_no_taxable_amount ?? 0,
                ];
                $currentRow++;
                $this->tablePositions[$tIdx]['dataCount']++;
            }

            if (count($group->brackets) === 0) {
                $rows[] = ['', 'No brackets configured', '', '', '', '', '', '', ''];
                $currentRow++;
                $this->tablePositions[$tIdx]['dataCount']++;
            }

            $rows[] = [''];
            $currentRow++;
        }

        // Standalone taxes
        if (count($this->data['standalone_taxes']) > 0) {
            $titleRow = $currentRow;
            $rows[] = ['STANDALONE TAXES'];
            $currentRow++;

            $rows[] = ['#', 'Tax Name', 'Min Amount', 'Max Amount', 'Tax Type', 'Employee Rate (%)', 'Employer Rate (%)', 'Paid By', 'Status'];
            $headerPos = $currentRow;
            $currentRow++;

            $this->tablePositions[] = ['title' => $titleRow, 'header' => $headerPos, 'dataStart' => $currentRow, 'dataCount' => 0];
            $tIdx = count($this->tablePositions) - 1;

            foreach ($this->data['standalone_taxes'] as $sIdx => $tax) {
                $taxName = $tax->title ?? $tax->name ?? '-';
                if ($tax->is_dependent) {
                    $taxName .= ' [DEPENDENT - from: ' . ($tax->dependency_label ?? 'source tax') . ']';
                }
                $rows[] = [
                    $sIdx + 1,
                    $taxName,
                    $tax->min_amount,
                    $tax->max_amount,
                    $tax->tax_type == 1 ? 'Percentage' : 'Fixed Amount',
                    $tax->percentange ?? 0,
                    $tax->employer_percentage ?? 0,
                    ucfirst($tax->paid_by ?? 'employee'),
                    $tax->status ? 'Active' : 'Inactive',
                ];
                $currentRow++;
                $this->tablePositions[$tIdx]['dataCount']++;
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Title rows
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

        // Style each table section
        foreach ($this->tablePositions as $pos) {
            $tr = $pos['title'];
            $hr = $pos['header'];
            $ds = $pos['dataStart'];
            $dc = $pos['dataCount'];
            $de = $ds + $dc - 1;

            // Title row for each group
            $sheet->getStyle("A{$tr}:I{$tr}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1a1a2e']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8ECF6']],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '4E73DF']]],
            ]);

            // Header row
            $sheet->getStyle("A{$hr}:I{$hr}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5A5C69']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '4A4C59']]],
            ]);

            if ($dc > 0) {
                $sheet->getStyle("A{$ds}:I{$de}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DEE2E6']]],
                    'font' => ['size' => 9],
                ]);

                // Number format for amount columns
                $sheet->getStyle("C{$ds}:D{$de}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("C{$ds}:D{$de}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);
                $sheet->getStyle("I{$ds}:I{$de}")->getNumberFormat()->setFormatCode('#,##0');

                // Zebra striping
                for ($r = $ds; $r <= $de; $r++) {
                    if (($r - $ds) % 2 === 1) {
                        $sheet->getStyle("A{$r}:I{$r}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FC']],
                        ]);
                    }
                }
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
            },
        ];
    }
}
