<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The daybook as a workbook — the month, then its summary.
 *
 * Two sheets rather than one, because that is how the Bursar's own file is
 * arranged: a month of movements, and a summary that turns those columns into
 * budget-versus-actual. Anyone comparing the two files should find the same
 * shape on the same tabs.
 */
class DaybookExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(protected array $book, protected ?array $summary = null)
    {
    }

    public function sheets(): array
    {
        $sheets = [new DaybookMonthSheet($this->book)];

        if ($this->summary) {
            $sheets[] = new DaybookSummarySheet($this->summary);
        }

        return $sheets;
    }
}
