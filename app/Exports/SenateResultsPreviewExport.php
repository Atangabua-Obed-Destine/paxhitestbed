<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SenateResultsPreviewExport implements WithMultipleSheets
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        $sheets = [
            'Overview'              => new SenateResultsSheets\OverviewSheet($this->data),
            'Faculty Summary'       => new SenateResultsSheets\FacultySummarySheet($this->data),
            'Course Results'        => new SenateResultsSheets\CourseResultsSheet($this->data),
            'Lecturer Performance'  => new SenateResultsSheets\LecturerPerformanceSheet($this->data),
            'Top & Bottom Courses'  => new SenateResultsSheets\TopBottomCoursesSheet($this->data),
        ];

        // Add one sheet per faculty for student matrix detail
        $matrices = $this->data['faculty_student_matrices'] ?? [];
        foreach ($matrices as $facMatrix) {
            $sheetName = 'Students — ' . substr($facMatrix['shortcode'], 0, 20);
            // Excel sheet names max 31 chars, must be unique
            $sheetName = substr(preg_replace('/[\\[\\]\\*\\/\\\\\\?\\:]/', '', $sheetName), 0, 31);
            $sheets[$sheetName] = new SenateResultsSheets\StudentMatrixSheet($this->data, $facMatrix);
        }

        // Reference / Legend sheet last
        $sheets['Reference & Legend'] = new SenateResultsSheets\ReferenceSheet($this->data);

        return $sheets;
    }
}
