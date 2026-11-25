<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Academic standings for several semesters of one type (Year 1 and Year 2 of
 * "First Semester"): one AcademicStandingsExport sheet per semester, each
 * exactly what the single-semester export writes, named after its semester.
 */
class AcademicStandingsMultiExport implements WithMultipleSheets
{
    /** @param array[] $perSemester one AcademicStandingsExport data array each, with sheet_title */
    public function __construct(protected array $perSemester)
    {
    }

    public function sheets(): array
    {
        return array_map(fn ($data) => new AcademicStandingsExport($data), array_values($this->perSemester));
    }
}
