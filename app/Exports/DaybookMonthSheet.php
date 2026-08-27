<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * One month of the cash analysis book.
 *
 * Amounts are written as real numbers, not formatted strings: the Bursar pulls
 * this into Excel to re-cut and re-total it, and a cell holding "203,000" is
 * text that cannot be summed.
 */
class DaybookMonthSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    protected int $headingRow = 0;
    protected int $lastRow = 0;
    protected array $totalRows = [];

    public function __construct(protected array $data)
    {
    }

    public function title(): string
    {
        // Excel forbids : \ / ? * [ ] in sheet names and caps them at 31 chars.
        $name = preg_replace('~[:\\\\/?*\[\]]~', '-', (string) $this->data['period']->name);

        return mb_substr($name, 0, 31);
    }

    public function array(): array
    {
        $period = $this->data['period'];
        $budget = $this->data['budget'];

        $rows = [];
        $rows[] = [institution_name()];
        $rows[] = ['DAYBOOK — CASH ANALYSIS BOOK'];
        $rows[] = [$period->name . '   |   ' . $budget->title];
        $rows[] = ['Generated ' . now()->format('d M Y H:i') . '   |   Amounts in FCFA'];
        $rows[] = [''];

        $this->headingRow = count($rows) + 1;
        $rows[] = ['Date', 'Ref', 'Description', 'Analysis column', 'Debit IN', 'Credit Out'];

        foreach ($this->data['rows'] as $row) {
            $line = $row['line_id'] ? ($this->data['lines'][$row['line_id']] ?? null) : null;

            $rows[] = [
                $row['date'],
                $row['ref'],
                $row['description'],
                $line ? $line->code . ' ' . $line->name : 'UNANALYSED',
                $row['direction'] === 'in' ? (float) $row['amount'] : null,
                $row['direction'] === 'out' ? (float) $row['amount'] : null,
            ];
        }

        $rows[] = ['', '', '', 'TOTAL FOR ' . strtoupper($period->name),
            (float) $this->data['totalIn'], (float) $this->data['totalOut']];
        $this->totalRows[] = count($rows);

        $rows[] = ['', '', '', 'NET MOVEMENT',
            (float) ($this->data['totalIn'] - $this->data['totalOut']), null];
        $this->totalRows[] = count($rows);

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 12, 'B' => 14, 'C' => 52, 'D' => 34, 'E' => 14, 'F' => 14];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max($this->lastRow, $this->headingRow);

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->getStyle("A{$this->headingRow}:F{$this->headingRow}")
                    ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$this->headingRow}:F{$this->headingRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34495E');

                // Numbers as numbers, with a dash where nothing moved.
                $sheet->getStyle('E' . ($this->headingRow + 1) . ':F' . $last)
                    ->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);"—"');

                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:F{$row}")->getBorders()->getTop()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('34495E');
                }

                // Keep the date, ref and description in view while scrolling a
                // month that can run to hundreds of rows.
                $sheet->freezePane('D' . ($this->headingRow + 1));
            },
        ];
    }
}
