<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentVerificationController extends Controller
{
    /**
     * Verify student ID
     *
     * @param string $student_id
     * @return \Illuminate\Http\Response
     */
    public function verify($student_id)
    {
        $student = Student::where('student_id', $student_id)->first();

        if (!$student) {
            return view('web.student-verify', [
                'found' => false,
                'student_id' => $student_id,
                'message' => 'Student ID not found in our records.'
            ]);
        }

        return view('web.student-verify', [
            'found' => true,
            'student' => $student,
            'student_id' => $student_id,
            'message' => 'Student ID verified successfully!'
        ]);
    }
}
