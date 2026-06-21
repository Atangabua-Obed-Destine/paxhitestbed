<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Student\ProgressionEligibilityService;
use App\Services\Academic\SemesterProgressionService;

// Find student
$enroll = StudentEnroll::where('matricule', 'PAX25MBH057')->first();
if (!$enroll) {
    echo "No enrollment found with matricule PAX25MBH057\n";
    exit;
}

$student = $enroll->student;
echo "Student: {$student->first_name} {$student->last_name} (ID={$student->id})\n\n";

// Test carry-over detection via the service
$service = app(ProgressionEligibilityService::class);

// Get all enrollments to test carry-over from each
$enrolls = $student->enrolls()->with(['semester'])->orderBy('id')->get();
foreach ($enrolls as $e) {
    $sem = $e->semester;
    echo "=== Enroll #{$e->id}: {$sem->title} (type={$sem->semester_type}, year={$sem->year}, is_resit={$sem->is_resit}) ===\n";
    
    $result = $service->checkEligibility($student, $e->id);
    $carryOvers = $result['carry_over_courses'] ?? [];
    
    echo "  Eligible: " . ($result['eligible'] ? 'YES' : 'NO') . "\n";
    echo "  Type: " . ($result['type'] ?? 'N/A') . "\n";
    echo "  Carry-over courses: " . count($carryOvers) . "\n";
    
    if (!empty($carryOvers)) {
        foreach ($carryOvers as $co) {
            echo "    - {$co['subject_code']}: {$co['subject_title']} ({$co['best_marks']}%) from {$co['from_semester']} [type={$co['semester_type']}: {$co['semester_type_label']}]\n";
        }
    } else {
        echo "    NONE DETECTED\n";
    }
    echo "\n";
}
