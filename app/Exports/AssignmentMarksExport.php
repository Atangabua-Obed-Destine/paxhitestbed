<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Models\StudentAssignment;

class AssignmentMarksExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;

    /**
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return StudentAssignment::with('studentEnroll')->where('assignment_id', $this->data['id'])->get()->sortBy(function($q){
            return $q->studentEnroll->student->matricule;
        });
    }

    public function headings(): array
    {
        return [
            'matricule',
            'first_name',
            'last_name',
            'program',
            'semester',
            'section',
            'attendance',
            'date',
            'marks',
        ];
    }

    public function map($stuAssignment): array
    {
        return [
            $stuAssignment->studentEnroll->student->matricule,
            $stuAssignment->studentEnroll->student->first_name,
            $stuAssignment->studentEnroll->student->last_name,
            $stuAssignment->studentEnroll->program->title,
            $stuAssignment->studentEnroll->semester->title,
            $stuAssignment->studentEnroll->section->title,
            $stuAssignment->attendance ?? 0,
            $stuAssignment->date,
            $stuAssignment->marks,
        ];
    }
}
