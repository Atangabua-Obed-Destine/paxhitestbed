<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The Income & Expenditure sheet as a workbook.
 *
 * The PDF is for signing; this is for working — the Bursar pulls it into Excel
 * to model next year, so the figures are written as real numbers rather than
 * formatted strings. A cell holding "4,295,000" is text and cannot be summed,
 * which would defeat the point of exporting it at all.
 *
 * Layout mirrors the screen exactly, section for section, so the two can be read
 * side by side without translation.
 */
class BudgetSheetExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    /** Rows that need a particular treatment, filled while building the array. */
    protected array $sectionRows = [];
    protected array $headerRows = [];
    protected array $totalRows = [];
    protected int $headingRow = 0;
    protected int $lastRow = 0;

    public function __construct(protected array $data)
    {
    }

    public function title(): string
    {
        // Excel forbids : \ / ? * [ ] in sheet names and caps them at 31 chars.
        $name = preg_replace('~[:\\\\/?*\[\]]~', '-', (string) $this->data['budget']->title);

        return mb_substr('Budget ' . $name, 0, 31);
    }

    public function array(): array
    {
        $budget = $this->data['budget'];
        $totals = $this->data['totals'];
        $previous = $this->data['previous'];
        $priorTotals = $this->data['priorTotals'];
        $setting = \App\Models\Setting::first();

        $priorLabel = $previous ? $previous->title : 'Last year';

        $rows = [];
        $rows[] = [$setting->title ?? config('app.name')];
        $rows[] = ['INCOME AND EXPENDITURE ANNUAL BUDGET SHEET'];
        $rows[] = [
            $budget->title
            . '   |   ' . optional($budget->start_date)->format('d M Y')
            . ' to ' . optional($budget->end_date)->format('d M Y')
            . '   |   Status: ' . ucfirst(str_replace('_', ' ', $budget->status)),
        ];
        $rows[] = ['Generated ' . now()->format('d M Y H:i') . '   |   Amounts in FCFA'];
        $rows[] = [''];

        $this->headingRow = count($rows) + 1;
        $rows[] = ['Code', 'Description', 'Posts to', $priorLabel, 'Budget', 'Actual', 'Variance'];

        $rows[] = $this->line('', 'OPENING BALANCE', '', $priorTotals['opening'], $budget->opening_balance, $totals['actual']['opening'], null);
        $this->totalRows[] = count($rows);

        $currentSection = null;
        foreach ($this->data['lines'] as $line) {
            if ($line->section !== $currentSection) {
                // Close off the section that just ended before opening the next.
                $this->appendSectionTotals($rows, $currentSection, $line->section);

                $currentSection = $line->section;
                $rows[] = [$this->sectionLabel($currentSection), '', '', '', '', '', ''];
                $this->sectionRows[] = count($rows);
            }

            $b = $this->data['budgeted'][$line->id] ?? 0;
            $a = $this->data['actual'][$line->id] ?? 0;
            $p = $this->data['priorActual'][$line->id] ?? 0;

            $rows[] = $this->line(
                $line->code,
                ($line->is_header ? '' : '    ') . $line->name,
                implode(' · ', $this->data['accountsByLine'][$line->id] ?? []),
                $p,
                $b,
                $a,
                $b ? $a - $b : null
            );

            if ($line->is_header) {
                $this->headerRows[] = count($rows);
            }
        }

        // Whatever section ran last still needs its totals.
        $this->appendSectionTotals($rows, $currentSection, null);

        $rows[] = [''];
        $rows[] = ['CASH POSITION'];
        $this->headerRows[] = count($rows);
        $rows[] = ['', 'Closing balance', '', '', '', $totals['actual']['closing'], null];
        $rows[] = ['', 'Add depreciation (never leaves the bank)', '', '', '', $totals['actual']['depreciation'], null];
        $rows[] = ['', 'Less capital spending', '', '', '', -$totals['actual']['capital'], null];
        $rows[] = ['', 'Cash position', '', '', '', $totals['actual']['cash'], null];
        $this->totalRows[] = count($rows);

        $this->appendReconciliation($rows);

        $this->lastRow = count($rows);

        return $rows;
    }

    /** One figure row, with numbers left as numbers so Excel can sum them. */
    protected function line($code, $name, $account, $prior, $budget, $actual, $variance): array
    {
        $n = fn ($v) => ($v === null || $v == 0) ? null : (float) $v;

        return [$code, $name, $account, $n($prior), $n($budget), $n($actual), $n($variance)];
    }

    protected function sectionLabel(string $section): string
    {
        return [
            'income' => 'INCOME',
            'expenditure' => 'EXPENDITURE',
            'capital' => 'CAPITAL EXPENDITURE ACCOUNTS',
        ][$section] ?? strtoupper($section);
    }

    /**
     * The totals that belong at the foot of a section.
     *
     * Placed exactly where the screen places them: income totals before
     * expenditure opens, and expenditure plus the closing balance before capital.
     */
    protected function appendSectionTotals(array &$rows, ?string $ending, ?string $starting): void
    {
        $totals = $this->data['totals'];
        $prior = $this->data['priorTotals'];

        if ($ending === 'income' && $starting !== 'income') {
            $rows[] = $this->line('', 'TOTAL INCOME', '', $prior['income'], $totals['budget']['income'], $totals['actual']['income'], $totals['actual']['income'] - $totals['budget']['income']);
            $this->totalRows[] = count($rows);
        }

        if ($ending === 'expenditure' && $starting !== 'expenditure') {
            $rows[] = $this->line('', 'TOTAL EXPENDITURE', '', $prior['expenditure'], $totals['budget']['expenditure'], $totals['actual']['expenditure'], $totals['actual']['expenditure'] - $totals['budget']['expenditure']);
            $this->totalRows[] = count($rows);
            $rows[] = $this->line('', 'CLOSING BALANCE', '', $prior['closing'], $totals['budget']['closing'], $totals['actual']['closing'], null);
            $this->totalRows[] = count($rows);
        }

        if ($ending === 'capital' && $starting !== 'capital') {
            $rows[] = $this->line('', 'TOTAL CAPITAL', '', $prior['capital'], $totals['budget']['capital'], $totals['actual']['capital'], $totals['actual']['capital'] - $totals['budget']['capital']);
            $this->totalRows[] = count($rows);
        }
    }

    /** Whether this workbook ties back to the ledger, stated on the workbook. */
    protected function appendReconciliation(array &$rows): void
    {
        $recon = $this->data['reconciliation'] ?? null;
        if (!$recon) {
            return;
        }

        $rows[] = [''];
        $rows[] = ['AGREEMENT WITH THE GENERAL LEDGER'];
        $this->headerRows[] = count($rows);
        $rows[] = ['', 'Section', 'Ledger class', '', 'This sheet', 'Ledger', 'Difference'];
        $this->headerRows[] = count($rows);

        foreach ($recon['sections'] as $s) {
            $rows[] = ['', $s['label'], 'Class ' . $s['class'], null, (float) $s['sheet'], (float) $s['ledger'], $s['difference'] == 0 ? null : (float) $s['difference']];
        }

        foreach ($recon['issues'] as $issue) {
            $rows[] = ['', 'Issue', $issue['detail']];
        }
    }

    public function columnWidths(): array
    {
        return ['A' => 9, 'B' => 46, 'C' => 34, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;

                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('A2:G2');
                $sheet->mergeCells('A3:G3');
                $sheet->mergeCells('A4:G4');

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A3:A4')->getFont()->setSize(9)->getColor()->setRGB('55636B');
                $sheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Column headings.
                $head = 'A' . $this->headingRow . ':G' . $this->headingRow;
                $sheet->getStyle($head)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($head)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34495E');
                $sheet->getStyle($head)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Figures as whole francs, negatives in red and parenthesised so
                // an overspend is visible without reading the minus sign.
                $sheet->getStyle('D' . ($this->headingRow + 1) . ':G' . $last)
                    ->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);"—"');

                foreach ($this->sectionRows as $row) {
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('34495E');
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF1F6');
                }

                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()->getTop()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('34495E');
                }

                // The account reference is supporting detail, not a figure.
                $sheet->getStyle('C' . $this->headingRow . ':C' . $last)
                    ->getFont()->setSize(8)->getColor()->setRGB('8A969C');

                $sheet->getStyle('B' . $this->headingRow . ':B' . $last)
                    ->getAlignment()->setWrapText(false);

                // Keep the headings and the description in view while scrolling
                // a sheet that runs to eighty rows and seven columns.
                $sheet->freezePane('D' . ($this->headingRow + 1));

                $sheet->getStyle('A' . $this->headingRow . ':G' . $last)
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->setSelectedCell('A1');
            },
        ];
    }
}
