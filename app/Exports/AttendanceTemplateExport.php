<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceTemplateExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $students;
    protected $date;

    public function __construct($students, $date = null)
    {
        $this->students = $students;
        $this->date = $date;
    }

    public function collection()
    {
        return $this->students;
    }

    public function map($student): array
    {
        return [
            $this->date ?? date('Y-m-d'),
            $student->student->student_id,
            $student->student->first_name . ' ' . $student->student->last_name,
            'P', // Default attendance
            '', // Note
        ];
    }

    public function headings(): array
    {
        return [
            'date',
            'student_id',
            'student_name',
            'attendance',
            'note',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1    => ['font' => ['bold' => true]],
        ];
    }
}
