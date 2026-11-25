<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The senate results preview for several semesters of one type (Year 1 and
 * Year 2 of "First Semester") in one workbook: each semester's own set of
 * sheets, exactly as its single-semester export, titled with its prefix
 * (sheet_prefix, e.g. "Y1 ", "Y2 "). Figures are never merged across semesters.
 */
class SenateResultsPreviewMultiExport implements WithMultipleSheets
{
    /** @param array[] $perSemester one SenateResultsPreviewExport data array each, with sheet_prefix */
    public function __construct(protected array $perSemester)
    {
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->perSemester as $data) {
            foreach ((new SenateResultsPreviewExport($data))->sheets() as $key => $sheet) {
                $sheets[($data['sheet_prefix'] ?? '') . $key] = $sheet;
            }
        }

        return $sheets;
    }
}
