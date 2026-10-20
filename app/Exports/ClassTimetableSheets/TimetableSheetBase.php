<?php

namespace App\Exports\ClassTimetableSheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Shared shape for every sheet in the timetable workbook.
 *
 * This file leaves the building: it is sent to heads of department and
 * printed for noticeboards, so each sheet has to stand on its own. A reader
 * who opens the workbook at the twentieth tab still needs to know which
 * school this is, which session, which class group, and when it was produced.
 * Every sheet therefore carries the same masthead, and is set up to print
 * properly — landscape, fitted to the page width, with the header repeating
 * on any sheet that runs over one page.
 *
 * The styling lives here once rather than in each of the forty-odd sheets.
 */
abstract class TimetableSheetBase implements FromArray, WithTitle, WithEvents
{
    protected array $data;

    /** Rows to draw as a table header, 1-indexed. */
    protected array $headerRows = [];

    /** Rows to draw as a section title. */
    protected array $sectionRows = [];

    /** [row => 'ok'|'warn'|'bad'] for rows carrying a verdict. */
    protected array $verdictRows = [];

    /** Columns to leave at a fixed width instead of autosizing. */
    protected array $fixedWidths = [];

    /** Print orientation; the grids want landscape. */
    protected string $orientation = PageSetup::ORIENTATION_LANDSCAPE;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** The masthead every sheet opens with. Always five rows. */
    protected function masthead(string $subtitle): array
    {
        $generated = $this->data['generated_at'] ?? now();
        $faculty = $this->data['faculty'] ?? null;

        return [
            [strtoupper(institution_name())],
            [__('Class Timetable')],
            [$subtitle],
            [__('Session') . ': ' . ($this->data['session']->title ?? __('none set'))
                . ($faculty ? '   |   ' . __('field_faculty') . ': ' . $faculty->title : '')
                . '   |   ' . __('Produced') . ' ' . $generated->format('d F Y, H:i')],
            [''],
        ];
    }

    /** Yes/No that reads the same to everyone. */
    protected function yesNo($value): string
    {
        return $value ? __('Yes') : __('No');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highest = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Masthead
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB('555555');
                $sheet->getStyle('A4')->getFont()->setSize(9)->getColor()->setRGB('777777');

                foreach ($this->sectionRows as $row) {
                    $style = $sheet->getStyle("A{$row}:{$highest}{$row}");
                    $style->getFont()->setBold(true)->setSize(11);
                    $style->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('EEF2F5');
                }

                foreach ($this->headerRows as $row) {
                    $style = $sheet->getStyle("A{$row}:{$highest}{$row}");
                    $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $style->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('2C3E50');
                    $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getRowDimension($row)->setRowHeight(22);
                }

                // A verdict is colour AND word, never colour alone — this gets
                // printed in black and white and read by people who cannot
                // rely on the fill coming through at all.
                $palette = [
                    'ok' => ['E8F5E9', '1B5E20'],
                    'warn' => ['FFF8E1', '8D6E00'],
                    'bad' => ['FDECEA', '9C2B18'],
                ];

                foreach ($this->verdictRows as $row => $verdict) {
                    if (!isset($palette[$verdict])) {
                        continue;
                    }

                    [$background, $text] = $palette[$verdict];
                    $style = $sheet->getStyle("A{$row}:{$highest}{$row}");
                    $style->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($background);
                    $style->getFont()->getColor()->setRGB($text);
                }

                // Borders on the body only; the masthead reads better without.
                if ($lastRow > 5) {
                    $sheet->getStyle("A6:{$highest}{$lastRow}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('D5DBDB');
                }

                foreach (range('A', $highest) as $column) {
                    if (isset($this->fixedWidths[$column])) {
                        $sheet->getColumnDimension($column)
                            ->setWidth($this->fixedWidths[$column]);
                        continue;
                    }

                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $sheet->getStyle("A1:{$highest}{$lastRow}")
                    ->getAlignment()->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);

                $sheet->freezePane('A6');

                // Set up for paper, because this workbook gets printed.
                $setup = $sheet->getPageSetup();
                $setup->setOrientation($this->orientation);
                $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
                $setup->setFitToWidth(1);
                $setup->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)
                    ->setLeft(0.4)->setRight(0.4);

                // Repeat the masthead, and the table header only when the
                // sheet is one long table. A sheet with two tables would
                // otherwise reprint everything down to the second header —
                // the whole first table included — at the top of page two.
                $repeatTo = count($this->headerRows) === 1
                    ? max($this->headerRows)
                    : 5;

                $setup->setRowsToRepeatAtTopByStartAndEnd(1, $repeatTo);

                $sheet->getHeaderFooter()->setOddFooter(
                    '&L' . institution_name() . '&C&P / &N&R' . $this->title()
                );
            },
        ];
    }
}
