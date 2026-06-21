<?php
/**
 * Quick check for PAX25TSC003H
 * Access via browser: http://localhost/paxhitest/check-student-PAX25TSC003H.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Services\GraduationEligibilityService;

echo "=== CHECKING STUDENT: PAX25TSC003H ===\n\n";

// Find enrollment
$enrollment = StudentEnroll::where('matricule', 'PAX25TSC003H')
    ->with(['student', 'program', 'semester', 'session', 'section'])
    ->first();

if (!$enrollment) {
    die("Enrollment not found!\n");
}

$student = $enrollment->student;
$programId = $enrollment->program_id;

echo "Student: {$student->first_name} {$student->last_name}\n";
echo "Student ID: {$student->student_id}\n";
echo "Program: {$enrollment->program->title} (ID: {$programId})\n";
echo "Session: {$enrollment->session->title}\n";
echo "Semester: {$enrollment->semester->title}\n";
echo "Section: {$enrollment->section->title}\n\n";

// Get all program subjects
$programSubjects = Subject::whereHas('programs', function($query) use ($programId) {
    $query->where('program_id', $programId);
})->with(['programs' => function($query) use ($programId) {
    $query->where('program_id', $programId);
}])->get();

$compulsoryCount = $programSubjects->where('subject_type', 1)->count();
$universityReqCount = $programSubjects->where('subject_type', 2)->count();
$optionalCount = $programSubjects->where('subject_type', 0)->count();

echo "=== PROGRAM REQUIREMENTS ===\n";
echo "Total Subjects: {$programSubjects->count()}\n";
echo "Compulsory: {$compulsoryCount}\n";
echo "University Requirement: {$universityReqCount}\n";
echo "Optional: {$optionalCount}\n";
echo "Total Credits: " . $programSubjects->sum('credit_hour') . "\n\n";

// Get student's marks for this program only
echo "=== STUDENT'S MARKS (PROGRAM {$programId} ONLY) ===\n";
$totalMarks = 0;
$passedCount = 0;
$failedCount = 0;
$creditsEarned = 0;

foreach ($student->studentEnrolls as $enroll) {
    if ($enroll->program_id != $programId) {
        echo "Skipping enrollment {$enroll->id} (Program {$enroll->program_id})\n";
        continue;
    }
    
    echo "\nEnrollment {$enroll->id}: {$enroll->semester->title} - {$enroll->session->title}\n";
    
    if (isset($enroll->subjectMarks)) {
        foreach ($enroll->subjectMarks as $mark) {
            $subject = $mark->subject;
            if (!$subject) continue;
            
            $isVisible = $mark->is_visible_to_student ? 'YES' : 'NO';
            $status = $mark->total_marks >= 50 ? 'PASS' : 'FAIL';
            $typeLabel = $subject->subject_type == 1 ? 'Compulsory' : 
                        ($subject->subject_type == 2 ? 'Univ Req' : 'Optional');
            
            echo "  - {$subject->code}: {$subject->subject_name}\n";
            echo "    Type: {$typeLabel} | Marks: {$mark->total_marks}% | Status: {$status} | Published: {$isVisible}\n";
            
            if ($mark->is_visible_to_student) {
                $totalMarks++;
                if ($mark->total_marks >= 50) {
                    $passedCount++;
                    $creditsEarned += $subject->credit_hour;
                } else {
                    $failedCount++;
                }
            }
        }
    } else {
        echo "  No marks found\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total Courses Taken (Published): {$totalMarks}\n";
echo "Passed: {$passedCount}\n";
echo "Failed: {$failedCount}\n";
echo "Credits Earned: {$creditsEarned}\n";

// Run graduation service
echo "\n=== GRADUATION ELIGIBILITY CHECK ===\n";
$graduationService = app(\App\Services\GraduationEligibilityService::class);
$eligibility = $graduationService->checkEligibility($student, $programId);

echo "Eligible: " . ($eligibility['is_eligible'] ? 'YES ✓' : 'NO ✗') . "\n";
echo "Total Credits Required: {$eligibility['total_credits']}\n";
echo "Completed Credits: {$eligibility['completed_credits']}\n\n";

echo "Compulsory: {$eligibility['compulsory']['passed_subjects']}/{$eligibility['compulsory']['total_subjects']} passed";
echo " (All Passed: " . ($eligibility['compulsory']['all_passed'] ? 'YES' : 'NO') . ")\n";

echo "University Req: {$eligibility['university_requirement']['passed_subjects']}/{$eligibility['university_requirement']['total_subjects']} passed";
echo " (All Passed: " . ($eligibility['university_requirement']['all_passed'] ? 'YES' : 'NO') . ")\n";

echo "Optional: {$eligibility['optional']['passed_subjects']}/{$eligibility['optional']['total_subjects']} passed\n";

if (!empty($eligibility['reasons'])) {
    echo "\n=== REASONS FOR INELIGIBILITY ===\n";
    foreach ($eligibility['reasons'] as $reason) {
        echo "✗ $reason\n";
    }
}

// Check for missing compulsory courses
if ($eligibility['compulsory']['missing_subjects'] > 0) {
    echo "\n=== MISSING COMPULSORY COURSES ===\n";
    $courseBreakdown = $graduationService->getCourseBreakdown($student, $programId);
    foreach ($courseBreakdown['compulsory'] as $course) {
        if ($course['status'] === 'Not Taken') {
            echo "- {$course['code']}: {$course['title']} ({$course['credits']} credits)\n";
        }
    }
}

// Check for failed compulsory courses
if (!empty($eligibility['compulsory']['failed_list'])) {
    echo "\n=== FAILED COMPULSORY COURSES ===\n";
    foreach ($eligibility['compulsory']['failed_list'] as $failed) {
        echo "- {$failed['code']}: {$failed['title']} - {$failed['marks']}%\n";
    }
}

// Check for missing university requirement courses
if ($eligibility['university_requirement']['missing_subjects'] > 0) {
    echo "\n=== MISSING UNIVERSITY REQUIREMENT COURSES ===\n";
    $courseBreakdown = $graduationService->getCourseBreakdown($student, $programId);
    foreach ($courseBreakdown['university_requirement'] as $course) {
        if ($course['status'] === 'Not Taken') {
            echo "- {$course['code']}: {$course['title']} ({$course['credits']} credits)\n";
        }
    }
}

// Check for failed university requirement courses
if (!empty($eligibility['university_requirement']['failed_list'])) {
    echo "\n=== FAILED UNIVERSITY REQUIREMENT COURSES ===\n";
    foreach ($eligibility['university_requirement']['failed_list'] as $failed) {
        echo "- {$failed['code']}: {$failed['title']} - {$failed['marks']}%\n";
    }
}

echo "\n=== DIAGNOSIS ===\n";
if ($eligibility['is_eligible']) {
    echo "✓ Student IS eligible for graduation!\n";
    echo "  All compulsory and university requirement courses have been passed.\n";
} else {
    if (!$eligibility['compulsory']['all_passed']) {
        $missing = $eligibility['compulsory']['missing_subjects'];
        $failed = $eligibility['compulsory']['failed_subjects'];
        if ($missing > 0) {
            echo "✗ Student has {$missing} MISSING compulsory course(s)\n";
        }
        if ($failed > 0) {
            echo "✗ Student has {$failed} FAILED compulsory course(s)\n";
        }
    }
    
    if (!$eligibility['university_requirement']['all_passed']) {
        $missing = $eligibility['university_requirement']['missing_subjects'];
        $failed = $eligibility['university_requirement']['failed_subjects'];
        if ($missing > 0) {
            echo "✗ Student has {$missing} MISSING university requirement course(s)\n";
        }
        if ($failed > 0) {
            echo "✗ Student has {$failed} FAILED university requirement course(s)\n";
        }
    }
}
