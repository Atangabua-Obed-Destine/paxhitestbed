<?php
/**
 * Debug script to check course registration data loading
 * Access: http://localhost/paxhitest/debug-course-registration.php?matricule=PAX25TSC001H
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;

$matricule = $_GET['matricule'] ?? null;

if (!$matricule) {
    die("Please provide matricule: ?matricule=PAX25XXX");
}

echo "=== COURSE REGISTRATION DEBUG ===\n";
echo "Matricule: $matricule\n\n";

// Find student
$student = Student::where('matricule', $matricule)->first();
if (!$student) {
    die("Student not found\n");
}

echo "Student: {$student->first_name} {$student->last_name}\n";
echo "Student ID: {$student->id}\n\n";

// Get all enrollments
$enrollments = StudentEnroll::where('student_id', $student->id)
    ->with(['program', 'semester', 'session'])
    ->orderBy('id', 'desc')
    ->get();

echo "=== ALL ENROLLMENTS ===\n";
foreach ($enrollments as $enroll) {
    echo "Enrollment ID: {$enroll->id}\n";
    echo "  Program ID: {$enroll->program_id}\n";
    echo "  Program: {$enroll->program->title}\n";
    echo "  Matricule: {$enroll->matricule}\n";
    echo "  Semester: {$enroll->semester->title}\n";
    echo "  Status: " . ($enroll->status == 1 ? 'Active' : 'Inactive') . "\n";
    echo "  Current?: " . ($enroll->id == $student->current_enroll_id ? 'YES' : 'NO') . "\n\n";
}

// Check session (simulated - won't work in CLI)
echo "=== SESSION CHECK ===\n";
echo "Note: Session data is not available in CLI scripts\n";
echo "The selected_enrollment_id would normally come from session('selected_enrollment_id')\n\n";

// Simulate what CourseRegistrationController does
echo "=== SIMULATING COURSE REGISTRATION LOGIC ===\n\n";

foreach ($enrollments as $testEnroll) {
    echo "--- Testing with Enrollment ID: {$testEnroll->id} ({$testEnroll->program->title}) ---\n";
    
    $student->load([
        'studentEnrolls.subjectMarks' => function($query) {
            $query->with('subject');
        }
    ]);
    
    // Count validated subjects for THIS program
    $validatedSubjects = [];
    foreach ($student->studentEnrolls as $enroll) {
        if ($enroll->program_id != $testEnroll->program_id) {
            continue;
        }
        
        if (isset($enroll->subjectMarks)) {
            foreach ($enroll->subjectMarks as $mark) {
                if ($mark->is_visible_to_student && $mark->total_marks >= 50) {
                    $validatedSubjects[$mark->subject_id] = $mark->subject->subject_name ?? 'Unknown';
                }
            }
        }
    }
    
    echo "Validated subjects for Program {$testEnroll->program_id}: " . count($validatedSubjects) . "\n";
    if (count($validatedSubjects) > 0) {
        echo "Sample validated subjects:\n";
        $count = 0;
        foreach ($validatedSubjects as $subjectId => $subjectName) {
            echo "  - {$subjectName} (ID: {$subjectId})\n";
            if (++$count >= 5) {
                echo "  ... and " . (count($validatedSubjects) - 5) . " more\n";
                break;
            }
        }
    }
    echo "\n";
    
    // Count total credits and CGPA for THIS program
    $totalCredits = 0;
    $totalCgpa = 0;
    $coursesAttempted = 0;
    $coursesPassed = 0;
    
    foreach ($student->studentEnrolls as $enroll) {
        if ($enroll->program_id != $testEnroll->program_id) {
            continue;
        }
        
        if (isset($enroll->subjectMarks)) {
            foreach ($enroll->subjectMarks as $mark) {
                if (!$mark->is_visible_to_student) continue;
                if (!isset($mark->subject)) continue;
                
                $coursesAttempted++;
                $creditHour = (float) $mark->subject->credit_hour;
                $totalCredits += $creditHour;
                
                if ($mark->total_marks >= 50) {
                    $coursesPassed++;
                }
            }
        }
    }
    
    echo "Total Credits: {$totalCredits}\n";
    echo "Courses Attempted: {$coursesAttempted}\n";
    echo "Courses Passed: {$coursesPassed}\n";
    echo "Pass Rate: " . ($coursesAttempted > 0 ? round(($coursesPassed / $coursesAttempted) * 100, 1) : 0) . "%\n";
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "If you're seeing wrong data in course registration:\n";
echo "1. Check which enrollment_id is in session('selected_enrollment_id')\n";
echo "2. Verify that enrollment belongs to the correct program\n";
echo "3. Make sure the program selector is updating the session correctly\n";
echo "4. Clear browser cache and cookies\n";
echo "5. Check if middleware is running on the route\n";
