<?php

/**
 * Simple direct test of inheritance methods
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\ExamType;
use App\Models\Exam;
use App\Models\StudentAttendance;
use App\Models\SubjectMarking;
use App\Models\SubjectMarkingExamState;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== Direct Test of Inheritance Methods ===\n\n";

try {
    // Find resit semester with parent
    $resitSemester = Semester::where('is_resit', true)->whereNotNull('parent_semester_id')->first();
    $parentSemester = $resitSemester->parentSemester;
    
    // Find enrollment
    $parentEnrollment = StudentEnroll::where('semester_id', $parentSemester->id)
        ->whereHas('subjects')->with('subjects')->first();
    
    $subject = $parentEnrollment->subjects->first();
    
    echo "Setup:\n";
    echo "- Parent enrollment: {$parentEnrollment->id}\n";
    echo "- Subject: {$subject->id}\n\n";
    
    // Create test data
    echo "Creating test data...\n";
    
    $caExamType = ExamType::create([
        'title' => 'DIRECT_TEST_CA',
        'marks' => 20,
        'contribution' => 20,
        'is_final' => false,
        'status' => 1
    ]);
    
    $finalExamType = ExamType::create([
        'title' => 'DIRECT_TEST_FINAL',
        'marks' => 60,
        'contribution' => 60,
        'is_final' => true,
        'status' => 1
    ]);
    
    // Create 2 attendance records
    StudentAttendance::create([
        'student_enroll_id' => $parentEnrollment->id,
        'subject_id' => $subject->id,
        'date' => Carbon::today(),
        'time' => '08:00:00',
        'attendance' => 1,
        'note' => 'DIRECT_TEST attendance 1',
        'status' => 1,
    ]);
    
    StudentAttendance::create([
        'student_enroll_id' => $parentEnrollment->id,
        'subject_id' => $subject->id,
        'date' => Carbon::yesterday(),
        'time' => '09:00:00',
        'attendance' => 1,
        'note' => 'DIRECT_TEST attendance 2',
        'status' => 1,
    ]);
    
    // Create CA exam
    Exam::create([
        'student_enroll_id' => $parentEnrollment->id,
        'subject_id' => $subject->id,
        'exam_type_id' => $caExamType->id,
        'date' => Carbon::today(),
        'time' => '10:00:00',
        'attendance' => 1,
        'marks' => 20,
        'achieve_marks' => 18,
        'contribution' => 20,
        'note' => 'DIRECT_TEST CA',
        'status' => 1,
    ]);
    
    // Create final exam
    Exam::create([
        'student_enroll_id' => $parentEnrollment->id,
        'subject_id' => $subject->id,
        'exam_type_id' => $finalExamType->id,
        'date' => Carbon::today(),
        'time' => '14:00:00',
        'attendance' => 1,
        'marks' => 60,
        'achieve_marks' => 20,
        'contribution' => 60,
        'note' => 'DIRECT_TEST FINAL',
        'status' => 1,
    ]);
    
    echo "✅ Created 2 attendance, 1 CA exam, 1 final exam\n\n";
    
    // Create resit enrollment manually
    echo "Creating resit enrollment...\n";
    $resitEnrollment = StudentEnroll::create([
        'student_id' => $parentEnrollment->student_id,
        'matricule' => $parentEnrollment->matricule,
        'program_id' => $parentEnrollment->program_id,
        'session_id' => $parentEnrollment->session_id,
        'semester_id' => $resitSemester->id,
        'section_id' => $parentEnrollment->section_id,
        'status' => 1,
    ]);
    $resitEnrollment->subjects()->attach([$subject->id]);
    echo "✅ Resit enrollment: {$resitEnrollment->id}\n\n";
    
    // Now test inheritance using reflection to call protected methods
    echo "Testing inheritance methods directly...\n";
    $service = new \App\Services\Academic\SemesterProgressionService();
    
    // Use reflection to access protected methods
    $reflection = new ReflectionClass($service);
    
    // Test inheritAttendance
    $inheritAttendanceMethod = $reflection->getMethod('inheritAttendance');
    $inheritAttendanceMethod->setAccessible(true);
    $attendanceCopied = $inheritAttendanceMethod->invoke(
        $service,
        $parentEnrollment->id,
        $resitEnrollment->id,
        $subject->id
    );
    echo "✅ inheritAttendance: {$attendanceCopied} records copied\n";
    
    // Test inheritCAMarks
    $inheritCAMarksMethod = $reflection->getMethod('inheritCAMarks');
    $inheritCAMarksMethod->setAccessible(true);
    $caMarksCopied = $inheritCAMarksMethod->invoke(
        $service,
        $parentEnrollment->id,
        $resitEnrollment->id,
        $subject->id
    );
    echo "✅ inheritCAMarks: {$caMarksCopied} CA exam records copied\n";
    
    // Verify
    echo "\nVerification:\n";
    $resitAttendance = StudentAttendance::where('student_enroll_id', $resitEnrollment->id)->count();
    $resitCAExams = Exam::where('student_enroll_id', $resitEnrollment->id)
        ->whereHas('type', fn($q) => $q->where('is_final', false))->count();
    $resitFinalExams = Exam::where('student_enroll_id', $resitEnrollment->id)
        ->whereHas('type', fn($q) => $q->where('is_final', true))->count();
    
    echo "- Resit attendance: {$resitAttendance} (expected 2)\n";
    echo "- Resit CA exams: {$resitCAExams} (expected 1)\n";
    echo "- Resit final exams: {$resitFinalExams} (expected 0)\n";
    
    if ($resitAttendance == 2 && $resitCAExams == 1 && $resitFinalExams == 0) {
        echo "\n✅ ALL INHERITANCE WORKING CORRECTLY!\n";
    } else {
        echo "\n❌ INHERITANCE NOT WORKING AS EXPECTED\n";
    }
    
    // Cleanup
    echo "\nCleaning up...\n";
    Exam::where('note', 'like', 'DIRECT_TEST%')->delete();
    Exam::where('note', 'like', 'Inherited from parent%')->delete();
    StudentAttendance::where('note', 'like', 'DIRECT_TEST%')->delete();
    StudentAttendance::where('note', 'like', 'Inherited from parent%')->delete();
    $resitEnrollment->subjects()->detach();
    $resitEnrollment->delete();
    ExamType::where('title', 'like', 'DIRECT_TEST%')->delete();
    echo "✅ Cleanup complete\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
