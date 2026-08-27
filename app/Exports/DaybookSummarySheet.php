<?php

namespace App\Exports;

use App\Models\BudgetLine;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The Monthly Summary, in the Bursar's own column order:
 * Budget · Monthly · BB Forward · Cumulative · Balance.
 *
 * Named "<Month> MS" to match the workbook's own tab naming, so the two files
 * can be read side by side without translation.
 */
class DaybookSummarySheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    protected int $headingRow = 0;
    protected int $lastRow = 0;
    protected array $sectionRows = [];
    protected array $totalRows = [];
    protected array $headerRows = [];
    protected array $subtotalRows = [];

    public function __construct(protected array $data)
    {
    }

    public function title(): string
    {
        $name = preg_replace('~[:\\\\/?*\[\]]~', '-', (string) $this->data['period']->name);

        return mb_substr($name . ' MS', 0, 31);
    }

    public function array(): array
    {
        $summary = $this->data['summary'];
        $totals = $summary['totals'];
        $period = $this->data['period'];

        $rows = [];
        $rows[] = [institution_name()];
        $rows[] = ['MONTHLY SUMMARY'];
        $rows[] = [$period->name . '   |   ' . $this->data['budget']->title];
        $rows[] = ['Generated ' . now()->format('d M Y H:i') . '   |   Amounts in FCFA'];
        $rows[] = [''];

        $this->headingRow = count($rows) + 1;
        $rows[] = ['Code', 'Description', 'Budget', 'Monthly', 'BB Forward', 'Cummulative', 'Balance'];

        $labels = [
            'income' => 'INCOME',
            'expenditure' => 'EXPENDITURE',
            'capital' => 'CAPITAL EXPENDITURE',
        ];

        foreach ($labels as $section => $label) {
            $rows[] = [$label, '', '', '', '', '', ''];
            $this->sectionRows[] = count($rows);

            foreach ($summary['lines'] as $row) {
                if ($row['line']->section !== $section) {
                    continue;
                }

                // Grouped exactly as the screen and the Income & Expenditure
                // sheet group it: headings at the margin carrying the sum of
                // their lines, those lines indented, each group closed off.
                $rows[] = [
                    $row['line']->code,
                    ($row['is_header'] ? '' : '    ') . $row['line']->name,
                    $this->n($row['budget']),
                    $this->n($row['monthly']),
                    $this->n($row['brought']),
                    $this->n($row['cumulative']),
                    $row['budget'] == 0 ? null : (float) $row['balance'],
                ];

                if ($row['is_header']) {
                    $this->headerRows[] = count($rows);
                }

                $end = $summary['group_ends'][$row['line']->id] ?? null;
                if ($end) {
                    $g = $summary['lines'][$end->id] ?? null;

                    if ($g) {
                        $rows[] = [
                            '',
                            '  Total ' . $end->name,
                            $this->n($g['budget']),
                            $this->n($g['monthly']),
                            $this->n($g['brought']),
                            $this->n($g['cumulative']),
                            $g['budget'] == 0 ? null : (float) $g['balance'],
                        ];
                        $this->subtotalRows[] = count($rows);
                    }
                }
            }

            $rows[] = [
                '', 'TOTAL ' . $label,
                $this->n($totals[$section]['budget']),
                $this->n($totals[$section]['monthly']),
                $this->n($totals[$section]['brought']),
                $this->n($totals[$section]['cumulative']),
                $this->n($totals[$section]['budget'] - $totals[$section]['cumulative']),
            ];
            $this->totalRows[] = count($rows);
        }

        $rows[] = [
            '', 'NET BALANCE',
            $this->n($totals['income']['budget'] - $totals['expenditure']['budget']),
            $this->n($totals['income']['monthly'] - $totals['expenditure']['monthly']),
            $this->n($totals['income']['brought'] - $totals['expenditure']['brought']),
            $this->n($totals['income']['cumulative'] - $totals['expenditure']['cumulative']),
            null,
        ];
        $this->totalRows[] = count($rows);

        // Profit centres re-present lines already counted above; they are set
        // apart so nobody adds them to the totals a second time.
        if (!empty($summary['profit_centres'])) {
            $rows[] = [''];
            $rows[] = ['PROFIT CENTRES — already counted above, shown for margin', '', '', '', '', '', ''];
            $this->sectionRows[] = count($rows);
            $rows[] = ['', 'Activity', 'Earned', 'Cost', '', 'Margin', ''];

            foreach ($summary['profit_centres'] as $name => $centre) {
                $rows[] = [
                    '', $name,
                    $this->n($centre['income']),
                    $this->n($centre['expenditure']),
                    null,
                    $this->n($centre['margin']),
                    null,
                ];
            }
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    /** Zero reads better as a dash than as a 0 in a column of real figures. */
    protected function n($value): ?float
    {
        return ($value === null || $value == 0) ? null : (float) $value;
    }

    public function columnWidths(): array
    {
        return ['A' => 10, 'B' => 46, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max($this->lastRow, $this->headingRow);

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                $sheet->getStyle("A{$this->headingRow}:G{$this->headingRow}")
                    ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$this->headingRow}:G{$this->headingRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34495E');

                $sheet->getStyle('C' . ($this->headingRow + 1) . ':G' . $last)
                    ->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);"—"');

                foreach ($this->sectionRows as $row) {
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('5A6B7D');
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF1F6');
                }

                // A group subtotal is ruled more lightly than a section total:
                // it closes a group, not a half of the sheet.
                foreach ($this->subtotalRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true)->setItalic(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7F9FB');
                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getTop()
                        ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('9AA7B4');
                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('9AA7B4');
                }

                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getTop()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('34495E');
                }

                $sheet->freezePane('C' . ($this->headingRow + 1));
            },
        ];
    }
}
