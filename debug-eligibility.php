<?php
/**
 * Debug script to check why PAX25TSC003H is not eligible
 * Access: http://localhost/paxhitest/debug-eligibility.php?matricule=PAX25TSC003H
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\StudentEnroll;
use App\Services\GraduationEligibilityService;

$matricule = $_GET['matricule'] ?? null;

if (!$matricule) {
    die("Please provide matricule: ?matricule=PAX25XXX");
}

echo "=== GRADUATION ELIGIBILITY DEBUG ===\n";
echo "Matricule: $matricule\n\n";

// Find enrollment by matricule
$enrollment = StudentEnroll::where('matricule', $matricule)
    ->with(['student', 'program', 'semester', 'session'])
    ->first();

if (!$enrollment) {
    die("Enrollment not found with matricule: $matricule\n");
}

$student = $enrollment->student;
$programId = $enrollment->program_id;

echo "Student: {$student->first_name} {$student->last_name}\n";
echo "Program: {$enrollment->program->title}\n";
echo "Program ID: {$programId}\n\n";

// Check eligibility
$graduationService = app(\App\Services\GraduationEligibilityService::class);
$eligibility = $graduationService->checkEligibility($student, $programId);
$courseBreakdown = $graduationService->getCourseBreakdown($student, $programId);

echo "=== ELIGIBILITY RESULT ===\n";
echo "Eligible: " . ($eligibility['is_eligible'] ? 'YES' : 'NO') . "\n";
echo "Total Credits Required: {$eligibility['total_credits']}\n";
echo "Completed Credits: {$eligibility['completed_credits']}\n\n";

echo "=== COMPULSORY COURSES ===\n";
echo "Total: {$eligibility['compulsory']['total_subjects']}\n";
echo "Passed: {$eligibility['compulsory']['passed_subjects']}\n";
echo "Failed: {$eligibility['compulsory']['failed_subjects']}\n";
echo "Missing: {$eligibility['compulsory']['missing_subjects']}\n";
echo "All Passed: " . ($eligibility['compulsory']['all_passed'] ? 'YES' : 'NO') . "\n";

if (!empty($eligibility['compulsory']['failed_list'])) {
    echo "\nFailed Compulsory Courses:\n";
    foreach ($eligibility['compulsory']['failed_list'] as $failed) {
        echo "  - {$failed['code']}: {$failed['title']} - {$failed['marks']}%\n";
    }
}

if (!empty($eligibility['compulsory']['missing_list'])) {
    echo "\nMissing Compulsory Courses:\n";
    foreach ($eligibility['compulsory']['missing_list'] as $missing) {
        echo "  - {$missing['code']}: {$missing['title']}\n";
    }
}

echo "\n=== UNIVERSITY REQUIREMENT COURSES ===\n";
echo "Total: {$eligibility['university_requirement']['total_subjects']}\n";
echo "Passed: {$eligibility['university_requirement']['passed_subjects']}\n";
echo "Failed: {$eligibility['university_requirement']['failed_subjects']}\n";
echo "Missing: {$eligibility['university_requirement']['missing_subjects']}\n";
echo "All Passed: " . ($eligibility['university_requirement']['all_passed'] ? 'YES' : 'NO') . "\n";

if (!empty($eligibility['university_requirement']['failed_list'])) {
    echo "\nFailed University Requirement Courses:\n";
    foreach ($eligibility['university_requirement']['failed_list'] as $failed) {
        echo "  - {$failed['code']}: {$failed['title']} - {$failed['marks']}%\n";
    }
}

if (!empty($eligibility['university_requirement']['missing_list'])) {
    echo "\nMissing University Requirement Courses:\n";
    foreach ($eligibility['university_requirement']['missing_list'] as $missing) {
        echo "  - {$missing['code']}: {$missing['title']}\n";
    }
}

echo "\n=== OPTIONAL COURSES ===\n";
echo "Total: {$eligibility['optional']['total_subjects']}\n";
echo "Passed: {$eligibility['optional']['passed_subjects']}\n";
echo "Failed: {$eligibility['optional']['failed_subjects']}\n";

echo "\n=== REASONS FOR INELIGIBILITY ===\n";
if (empty($eligibility['reasons'])) {
    echo "None - Student should be ELIGIBLE!\n";
} else {
    foreach ($eligibility['reasons'] as $reason) {
        echo "- $reason\n";
    }
}

echo "\n=== STUDENT'S MARKS (PUBLISHED ONLY) ===\n";
foreach ($student->studentEnrolls as $enroll) {
    if ($enroll->program_id != $programId) continue;
    
    echo "\nEnrollment: {$enroll->semester->title} - {$enroll->session->title}\n";
    if (isset($enroll->subjectMarks)) {
        foreach ($enroll->subjectMarks as $mark) {
            if (!$mark->is_visible_to_student) continue;
            
            $subject = $mark->subject;
            $status = $mark->total_marks >= 50 ? 'PASS' : 'FAIL';
            $type = $subject->subject_type == 1 ? 'Compulsory' : ($subject->subject_type == 2 ? 'Univ Req' : 'Optional');
            
            echo "  - {$subject->code}: {$subject->subject_name} [{$type}] - {$mark->total_marks}% [{$status}]\n";
        }
    }
}
