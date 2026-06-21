<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\StudentAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AssignmentMarksImport implements ToCollection, WithHeadingRow
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
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function collection(Collection $rows)
    {
        Validator::make($rows->toArray(), [
            '*.matricule' => 'required',
            '*.marks' => 'nullable|numeric',
        ])->validate();

        foreach ($rows as $row) {

            $student = Student::where('matricule', $row['matricule'])->first();

            StudentAssignment::where('student_enroll_id', $student->currentEnroll->id)
                ->where('assignment_id', $this->data['assignment'])
                ->update([
                'attendance'    => $row['attendance'] ?? null,
                'date'    => $row['date'] ?? null,
                'marks'    => $row['marks'] ?? null,
                'updated_by'    => Auth::guard('web')->user()->id,
            ]);
        }
    }
}
