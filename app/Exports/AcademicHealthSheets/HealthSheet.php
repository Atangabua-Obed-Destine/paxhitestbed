<?php

namespace App\Exports\AcademicHealthSheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Shared shape for every sheet in the health workbook.
 *
 * The report goes outside the office, so each sheet has to stand on its own:
 * a reader who opens it at the fourth tab still needs to know what school this
 * is, when it was produced, and what the sheet is claiming. Every sheet
 * therefore carries the same masthead, and the styling below is applied once
 * here rather than repeated eight times.
 */
abstract class HealthSheet implements FromArray, WithTitle, WithEvents
{
    protected array $data;

    /** Rows that should be drawn as a table header, 1-indexed. */
    protected array $headerRows = [];

    /** Rows that should be drawn as a section title. */
    protected array $sectionRows = [];

    /** [row => 'ok'|'warn'|'bad'] for rows carrying a verdict. */
    protected array $verdictRows = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** The masthead every sheet opens with. */
    protected function masthead(string $subtitle): array
    {
        $generated = $this->data['generated_at'] ?? now();

        return [
            [strtoupper(institution_name())],
            ['Academic Configuration Health Report'],
            [$subtitle],
            ['Produced ' . $generated->format('d F Y, H:i')
                . '  |  Session: ' . ($this->data['current_session']->title ?? 'none set')],
            [''],
        ];
    }

    /** Yes/No that reads the same to everyone. */
    protected function yesNo($value): string
    {
        return $value ? 'Yes' : 'No';
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
                    $sheet->getStyle("A{$row}:{$highest}{$row}")->getFont()->setBold(true)->setSize(11);
                    $sheet->getStyle("A{$row}:{$highest}{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F5');
                }

                foreach ($this->headerRows as $row) {
                    $style = $sheet->getStyle("A{$row}:{$highest}{$row}");
                    $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2C3E50');
                    $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getRowDimension($row)->setRowHeight(20);
                }

                // A verdict is colour AND word, never colour alone — the report
                // gets printed, photocopied and read by people who cannot rely
                // on the fill coming through.
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
                    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($background);
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
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $sheet->getStyle("A1:{$highest}{$lastRow}")
                    ->getAlignment()->setWrapText(true);

                $sheet->freezePane('A6');
            },
        ];
    }
}
