<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * One sheet of a report workbook: a few lines saying what it is, then a table.
 *
 * A table sheet gets a bold, shaded heading row, frozen so it stays in view,
 * and a filter on every column — the point of a workbook, as against a PDF, is
 * that people sort and filter it themselves.
 *
 * WithStrictNullComparison is not optional here. Without it Laravel Excel
 * writes every 0 as an empty cell, and in a report whose purpose is to show
 * which lines have nothing against them, a blank and a zero must not look
 * alike.
 *
 * This was the admissions board report's sheet, generalised when the fees
 * report needed the same thing. ApplicationReportSheet is now a name for it,
 * so the two workbooks cannot drift into looking different.
 */
class ReportSheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize, WithStrictNullComparison
{
    /**
     * @param string $title     the tab name
     * @param array  $preamble  lines above the table
     * @param array  $headings  column headings; empty for a free-form sheet
     * @param array  $rows      the rows
     * @param array  $emphasis  indexes into $rows to set in bold — section titles
     * @param array  $text      0-based column indexes to write as text, never
     *                          as figures: phone numbers, matricules, anything
     *                          that only looks like a number. PhpSpreadsheet
     *                          converts a numeric-looking string to a number,
     *                          which right-aligns it and drops a leading zero.
     */
    public function __construct(
        protected string $title,
        protected array $preamble,
        protected array $headings,
        protected array $rows,
        protected array $emphasis = [],
        protected array $text = []
    ) {
    }

    public function title(): string
    {
        return mb_substr(preg_replace('~[:\\\\/?*\[\]]~', '-', $this->title), 0, 31);
    }

    public function array(): array
    {
        $out = array_map(fn ($line) => [$line], $this->preamble);

        if ($this->preamble) {
            $out[] = [''];
        }

        if ($this->headings) {
            $out[] = $this->headings;
        }

        foreach ($this->rows as $row) {
            $out[] = array_values($row);
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $width = max(
                    1,
                    count($this->headings),
                    ...array_map(fn ($row) => count($row), $this->rows ?: [[]])
                );
                $lastColumn = Coordinate::stringFromColumnIndex($width);

                if ($this->preamble) {
                    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                    $sheet->getStyle('A2')->getFont()->setBold(true);
                }

                $offset = $this->preamble ? count($this->preamble) + 1 : 0;

                if ($this->headings) {
                    $headingRow = $offset + 1;
                    $range = "A{$headingRow}:{$lastColumn}{$headingRow}";

                    $sheet->getStyle($range)->getFont()->setBold(true);
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFE8EBEF');
                    $sheet->freezePane('A' . ($headingRow + 1));

                    if ($this->rows) {
                        $sheet->setAutoFilter("A{$headingRow}:{$lastColumn}" . ($headingRow + count($this->rows)));
                    }
                }

                $firstDataRow = $offset + ($this->headings ? 2 : 1);

                foreach ($this->emphasis as $index) {
                    $row = $firstDataRow + $index;
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFont()->setBold(true);
                }

                // Rewritten explicitly, because FromArray hands the value to the
                // default binder, which turns "0675..." into the number 675.
                foreach ($this->text as $column) {
                    $letter = Coordinate::stringFromColumnIndex($column + 1);

                    foreach ($this->rows as $index => $row) {
                        $value = array_values($row)[$column] ?? null;

                        if ($value === null || $value === '') {
                            continue;
                        }

                        $cell = $letter . ($firstDataRow + $index);
                        $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                    }
                }
            },
        ];
    }
}
