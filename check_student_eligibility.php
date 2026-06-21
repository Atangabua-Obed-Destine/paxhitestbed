<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get the student - provide student ID
$studentId = $argv[1] ?? 'PAX25FBF009'; // Default to this student if no arg provided

$student = App\Models\Student::where('student_id', $studentId)->first();

if (!$student) {
    echo "Student $studentId not found!\n";
    exit;
}

echo "Student: {$student->first_name} {$student->last_name}\n";
echo "Student ID: {$student->student_id}\n";
echo "Email: {$student->email}\n\n";

$enrollment = $student->currentEnroll;

if (!$enrollment) {
    echo "No active enrollment found\n";
    exit;
}

echo "Current Enrollment:\n";
echo "  ID: {$enrollment->id}\n";
echo "  Session: {$enrollment->session->title}\n";
echo "  Semester: {$enrollment->semester->title} (ID: {$enrollment->semester_id})\n";
echo "  Is Resit Semester: " . ($enrollment->semester->is_resit ? 'Yes' : 'No') . "\n";
echo "  Status: {$enrollment->status}\n\n";

// Check resit requests
$resitRequests = App\Models\ResitRequest::where('student_enroll_id', $enrollment->id)->get();
echo "Resit Requests: {$resitRequests->count()}\n";
foreach ($resitRequests as $request) {
    echo "  - Subject: {$request->subject->title}, State: {$request->workflow_state}\n";
}
echo "\n";

// Check progression eligibility
$eligibilityService = app(App\Services\Student\ProgressionEligibilityService::class);
$eligibility = $eligibilityService->checkEligibility($student);

echo "Progression Eligibility:\n";
echo "  Eligible: " . ($eligibility['eligible'] ? 'YES' : 'NO') . "\n";
echo "  Type: " . ($eligibility['type'] ?? 'N/A') . "\n";
echo "  Message: " . ($eligibility['message'] ?? 'N/A') . "\n";

if (!$eligibility['eligible']) {
    // Debug why not eligible
    echo "\nChecking resit semester progression...\n";
    $progressionService = app(App\Services\Academic\SemesterProgressionService::class);
    $resitCheck = $progressionService->checkResitSemesterProgression($enrollment);
    echo "  Can Progress (Resit): " . ($resitCheck['can_progress'] ? 'YES' : 'NO') . "\n";
    echo "  Reason: " . ($resitCheck['reason'] ?? 'N/A') . "\n";
    
    if (!empty($resitCheck['unresolved_courses'])) {
        echo "  Unresolved Courses:\n";
        foreach ($resitCheck['unresolved_courses'] as $course) {
            echo "    - {$course['subject_code']}: {$course['status']}\n";
        }
    }
    
    if (!empty($resitCheck['scheduled_courses'])) {
        echo "  Scheduled Courses:\n";
        foreach ($resitCheck['scheduled_courses'] as $course) {
            echo "    - {$course['subject_code']}\n";
        }
    }
    
    echo "\nChecking regular semester progression...\n";
    $regularCheck = $progressionService->checkProgressionEligibility($enrollment);
    echo "  Eligible (Regular): " . ($regularCheck['eligible'] ? 'YES' : 'NO') . "\n";
    echo "  Reason: " . ($regularCheck['reason'] ?? 'N/A') . "\n";
}

echo "\nDone.\n";
