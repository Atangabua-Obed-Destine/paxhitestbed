<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * One sheet of the admissions board workbook: a few lines saying what it is,
 * then a table.
 *
 * A table sheet gets a bold, shaded heading row, frozen so it stays in view, and
 * a filter on every column — the point of the workbook, as against the PDF, is
 * that people sort and filter it themselves.
 *
 * WithStrictNullComparison is not optional here. Without it Laravel Excel
 * writes every 0 as an empty cell, and in a report whose whole purpose is to
 * show which programmes have no applicants, a blank and a zero must not look
 * alike.
 */
class ApplicationReportSheet implements FromArray, WithTitle, WithEvents, ShouldAutoSize, WithStrictNullComparison
{
    /**
     * @param string $title     the tab name
     * @param array  $preamble  lines above the table
     * @param array  $headings  column headings; empty for a free-form sheet
     * @param array  $rows      the rows
     * @param array  $emphasis  indexes into $rows to set in bold — section titles
     */
    public function __construct(
        protected string $title,
        protected array $preamble,
        protected array $headings,
        protected array $rows,
        protected array $emphasis = []
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
            },
        ];
    }
}
