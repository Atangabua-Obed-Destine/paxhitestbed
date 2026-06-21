<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get student by matricule
$studentId = $argv[1] ?? 'PAX25TSC001H';
$student = App\Models\Student::where('student_id', $studentId)->first();

if (!$student) {
    echo "Student not found: $studentId\n";
    exit(1);
}

echo "Testing Progression API for: {$student->first_name} {$student->last_name} ($studentId)\n";
echo str_repeat("=", 80) . "\n\n";

// Create the service
$eligibilityService = app(App\Services\Student\ProgressionEligibilityService::class);

// Check eligibility
$result = $eligibilityService->checkEligibility($student);

echo "API Response:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "Eligible: " . ($result['eligible'] ? 'YES' : 'NO') . "\n";

if ($result['eligible']) {
    echo "Type: " . ($result['type'] ?? 'N/A') . "\n";
    echo "Target Semester: " . ($result['target_semester_title'] ?? 'N/A') . "\n";
    echo "Message: " . ($result['summary']['message'] ?? 'N/A') . "\n";
} else {
    echo "Reason: Not eligible\n";
}

echo "\nDone.\n";
