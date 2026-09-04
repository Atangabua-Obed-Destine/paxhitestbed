<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The academic health report as a workbook, for sending to the school.
 *
 * Ordered the way somebody reads it who has been asked "are we ready for the
 * year": the verdict first, then what is wrong, then the evidence behind both.
 * Anyone can stop after the second sheet and have the answer.
 */
class AcademicHealthExport implements WithMultipleSheets
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        return [
            'Summary' => new AcademicHealthSheets\SummarySheet($this->data),
            'Action Required' => new AcademicHealthSheets\FindingsSheet($this->data),
            'Configuration' => new AcademicHealthSheets\ConfigurationSheet($this->data),
            'Programmes' => new AcademicHealthSheets\ProgrammeSheet($this->data),
            'Semester Matrix' => new AcademicHealthSheets\SemesterMatrixSheet($this->data),
            'Course Catalogue' => new AcademicHealthSheets\CourseCatalogueSheet($this->data),
            'Teaching Staff' => new AcademicHealthSheets\StaffSheet($this->data),
            'Results Readiness' => new AcademicHealthSheets\ResultsSheet($this->data),
            'Finance' => new AcademicHealthSheets\FinanceSheet($this->data),
        ];
    }
}
