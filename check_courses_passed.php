<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\Grade;

$studentId = 'PAX25TSC001H';
$student = Student::where('student_id', $studentId)->first();

if (!$student) {
    echo "Student not found!\n";
    exit;
}

echo "Student: {$student->first_name} {$student->last_name} ({$studentId})\n";
echo "=" . str_repeat("=", 70) . "\n\n";

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

$courseStatus = []; // Track unique courses

// Get the selected enrollment (current semester)
$enroll = $student->studentEnrolls()->where('status', 1)->first();
$selectedProgramId = $enroll ? $enroll->program_id : null;

echo "Current Program ID: {$selectedProgramId}\n\n";

foreach ($student->studentEnrolls as $enrollRecord) {
    // Skip if different program
    if ($enrollRecord->program_id != $selectedProgramId) {
        continue;
    }
    
    $semester = $enrollRecord->semester;
    if (!$semester) continue;
    
    $isResitSemester = $semester->is_resit;
    echo "Semester: {$semester->title} (ID: {$semester->id}, is_resit: {$isResitSemester})\n";
    
    if ($enrollRecord->subjectMarks->count() > 0) {
        foreach ($enrollRecord->subjectMarks as $mark) {
            if (!$mark->subject) continue;
            
            $subjectId = $mark->subject_id;
            $subjectName = $mark->subject->subject_name;
            $marksPer = round($mark->total_marks);
            $isPassed = $marksPer >= 50;
            $status = $isPassed ? 'PASSED' : 'FAILED';
            
            // Track unique courses
            if (!isset($courseStatus[$subjectId])) {
                // First time seeing this course
                $courseStatus[$subjectId] = [
                    'name' => $subjectName,
                    'passed' => $isPassed,
                    'marks' => $marksPer,
                    'first_semester' => $semester->title
                ];
                echo "  - {$subjectName}: {$marksPer}% [{$status}]";
            } else {
                // This is a resit
                echo "  - {$subjectName}: {$marksPer}% [{$status}] (RESIT)";
                if ($isPassed && !$courseStatus[$subjectId]['passed']) {
                    // Passed the resit - update status
                    $courseStatus[$subjectId]['passed'] = true;
                    $courseStatus[$subjectId]['marks'] = $marksPer;
                    echo " - STATUS UPDATED TO PASSED";
                }
            }
            echo "\n";
        }
    } else {
        echo "  (No marks recorded)\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 70) . "\n";
echo "UNIQUE COURSES SUMMARY:\n";
echo str_repeat("=", 70) . "\n";
foreach ($courseStatus as $subjectId => $course) {
    $status = $course['passed'] ? 'PASSED' : 'FAILED';
    echo "{$course['name']}: {$course['marks']}% [{$status}] (First taken in: {$course['first_semester']})\n";
}

$totalCourses = count($courseStatus);
$passedCourses = count(array_filter($courseStatus, function($c) { return $c['passed']; }));
$failedCourses = $totalCourses - $passedCourses;

echo "\n" . str_repeat("=", 70) . "\n";
echo "TOTAL UNIQUE COURSES: {$totalCourses}\n";
echo "PASSED: {$passedCourses}\n";
echo "FAILED: {$failedCourses}\n";
echo "COMPLETION RATE: " . ($totalCourses > 0 ? number_format(($passedCourses / $totalCourses) * 100, 1) : 0) . "%\n";
