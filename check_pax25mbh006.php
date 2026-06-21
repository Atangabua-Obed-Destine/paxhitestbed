<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use App\Models\ResitRequest;

// Find student
$enroll = StudentEnroll::where('matricule', 'PAX25MBH006')->first();
if (!$enroll) {
    echo "No enrollment found with matricule PAX25MBH006\n";
    exit;
}

$student = $enroll->student;
echo "Student ID: {$student->id}\n";
echo "Name: {$student->first_name} {$student->last_name}\n";
echo "Program ID: {$student->program_id}\n\n";

// Get all enrollments
$enrolls = $student->enrolls()->with(['semester', 'session', 'program', 'subjects'])->orderBy('id')->get();

foreach ($enrolls as $e) {
    $sem = $e->semester;
    echo "=== Enroll #{$e->id} ===\n";
    echo "  Program: " . ($e->program->title ?? 'N/A') . " (ID={$e->program_id})\n";
    echo "  Semester: " . ($sem->title ?? 'N/A') . " (ID={$e->semester_id}";
    if ($sem) echo ", year={$sem->year}, type={$sem->semester_type}, is_resit={$sem->is_resit}";
    echo ")\n";
    echo "  Session: " . ($e->session->title ?? 'N/A') . " (ID={$e->session_id})\n";
    echo "  Matricule: {$e->matricule} | Status: {$e->status}\n";

    $subjects = $e->subjects;
    echo "  Subjects ({$subjects->count()}):\n";
    foreach ($subjects as $sub) {
        $mark = SubjectMarking::where('student_enroll_id', $e->id)
            ->where('subject_id', $sub->id)->first();
        echo "    - [{$sub->id}] {$sub->code} {$sub->title} (credit={$sub->credit_hour})";
        if ($mark) {
            echo " => total=" . round($mark->total_marks, 2) . ", state={$mark->workflow_state}";
            echo ", pub_date={$mark->publish_date}, pub_time={$mark->publish_time}";
            echo ", visible=" . ($mark->is_visible_to_student ? 'Y' : 'N');
            $passed = round($mark->total_marks) >= 50 ? 'PASS' : 'FAIL';
            echo " [{$passed}]";
        } else {
            echo " => NO MARKS";
        }
        echo "\n";
    }

    // Check resit requests
    $resits = ResitRequest::where('student_enroll_id', $e->id)->get();
    if ($resits->count() > 0) {
        echo "  Resit Requests:\n";
        foreach ($resits as $r) {
            echo "    - Subject #{$r->subject_id}: workflow_state={$r->workflow_state}";
            if ($r->resit_semester_id) echo ", resit_sem_id={$r->resit_semester_id}";
            echo "\n";
        }
    }
    echo "\n";
}

// Check what the progression eligibility service returns for each enrollment
echo "=== Progression Eligibility Checks ===\n";
$service = app(\App\Services\Student\ProgressionEligibilityService::class);

foreach ($enrolls as $e) {
    $sem = $e->semester;
    echo "\n--- Enroll #{$e->id}: {$sem->title} (type={$sem->semester_type}, year={$sem->year}, is_resit={$sem->is_resit}) ---\n";
    
    $result = $service->checkEligibility($student, $e->id);
    echo "  Eligible: " . ($result['eligible'] ? 'YES' : 'NO') . "\n";
    echo "  Type: " . ($result['type'] ?? 'N/A') . "\n";
    echo "  Message: " . ($result['message'] ?? 'N/A') . "\n";
    
    if (!empty($result['summary'])) {
        echo "  Target Semester: " . ($result['summary']['target_semester'] ?? 'N/A') . "\n";
    }
    if (isset($result['target_semester_title'])) {
        echo "  Target: {$result['target_semester_title']}\n";
    }
    
    $carryOvers = $result['carry_over_courses'] ?? [];
    echo "  Carry-over courses: " . count($carryOvers) . "\n";
    foreach ($carryOvers as $co) {
        echo "    - {$co['subject_code']}: {$co['subject_title']} ({$co['best_marks']}%) from {$co['from_semester']}\n";
    }

    // Show regular/resit check details if not eligible
    if (!$result['eligible']) {
        if (!empty($result['regular_check'])) {
            echo "  Regular check reason: " . ($result['regular_check']['reason'] ?? 'N/A') . "\n";
        }
        if (!empty($result['resit_check'])) {
            echo "  Resit check reason: " . ($result['resit_check']['reason'] ?? 'N/A') . "\n";
        }
    }
}

// Also check what the SemesterProgressionService says directly
echo "\n=== Direct SemesterProgressionService Checks ===\n";
$progressionService = app(\App\Services\Academic\SemesterProgressionService::class);

foreach ($enrolls as $e) {
    $sem = $e->semester;
    echo "\n--- Enroll #{$e->id}: {$sem->title} ---\n";
    
    // Check regular progression
    $regResult = $progressionService->checkProgressionEligibility($e);
    echo "  Regular eligible: " . ($regResult['eligible'] ? 'YES' : 'NO') . "\n";
    echo "  Reason: " . ($regResult['reason'] ?? 'N/A') . "\n";
    
    if (!empty($regResult['next_semester'])) {
        echo "  Next semester: " . $regResult['next_semester']->title . "\n";
    }
    
    // Check if all courses have published marks
    $allPublished = $progressionService->allCoursesHavePublishedMarks($e);
    echo "  All marks published: " . ($allPublished ? 'YES' : 'NO') . "\n";
    
    // Check if passed all courses
    $passedAll = $progressionService->passedAllCourses($e);
    echo "  Passed all courses: " . ($passedAll ? 'YES' : 'NO') . "\n";

    // Check active resit requests
    $hasActiveResit = $progressionService->hasActiveResitRequests($e);
    echo "  Has active resit requests: " . ($hasActiveResit ? 'YES' : 'NO') . "\n";
}
