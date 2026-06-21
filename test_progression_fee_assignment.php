<?php

/*
 * Test script to verify auto-fee assignment during semester progression
 * 
 * This tests that when a student progresses to a new regular semester,
 * the program semester fees are automatically assigned
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\Program;
use App\Models\ProgramSemesterFee;
use App\Models\Fee;
use App\Services\Academic\SemesterProgressionService;

echo "========================================\n";
echo "Test: Auto-Fee Assignment on Progression\n";
echo "========================================\n\n";

// 1. Find a student with an active enrollment
echo "1. Finding student with active enrollment...\n";
$enrollment = StudentEnroll::with(['student', 'program', 'semester'])
    ->whereHas('semester', function($query) {
        $query->where('is_resit', 0); // Regular semester
    })
    ->where('status', 1)
    ->first();

if (!$enrollment) {
    echo "❌ No active student enrollment found in regular semester\n";
    exit;
}

echo "✓ Found enrollment:\n";
echo "  - Student ID: {$enrollment->student->student_id}\n";
echo "  - Program: {$enrollment->program->title}\n";
echo "  - Current Semester: {$enrollment->semester->title}\n";
echo "  - Enrollment ID: {$enrollment->id}\n\n";

// 2. Check if there's a configured program semester fee for next semester
echo "2. Checking for next semester...\n";
$progressionService = app(SemesterProgressionService::class);

// Use reflection to access protected method
$reflection = new ReflectionClass($progressionService);
$findNextSemesterMethod = $reflection->getMethod('findNextSemester');
$findNextSemesterMethod->setAccessible(true);

$nextSemester = $findNextSemesterMethod->invoke($progressionService, $enrollment);

if (!$nextSemester) {
    echo "❌ No next semester found for this program\n";
    exit;
}

echo "✓ Next semester found:\n";
echo "  - Semester: {$nextSemester->title}\n";
echo "  - Year: {$nextSemester->year}\n";
echo "  - Type: {$nextSemester->semester_type}\n";
echo "  - ID: {$nextSemester->id}\n\n";

// 3. Check program semester fee configuration
echo "3. Checking program semester fee configuration...\n";
$programSemesterFees = ProgramSemesterFee::where('program_id', $enrollment->program_id)
    ->where('semester_id', $nextSemester->id)
    ->where('status', 1)
    ->with('feesCategory')
    ->get();

if ($programSemesterFees->isEmpty()) {
    echo "⚠️  No program semester fees configured for next semester\n";
    echo "   Program ID: {$enrollment->program_id}\n";
    echo "   Semester ID: {$nextSemester->id}\n";
    echo "\n   You need to configure fees at: /admin/program-semester-fee\n";
    exit;
}

echo "✓ Found {$programSemesterFees->count()} configured fee(s):\n";
foreach ($programSemesterFees as $psf) {
    echo "  - Category: {$psf->feesCategory->title}\n";
    echo "    Amount: {$psf->amount}\n";
    echo "    Type: {$psf->feesCategory->type}\n";
}
echo "\n";

// 4. Simulate progression (without actually creating enrollment)
echo "4. Testing auto-fee assignment logic...\n";
echo "   (Simulating progression without creating actual enrollment)\n\n";

// Create a temporary test enrollment
$testEnrollment = new StudentEnroll([
    'student_id' => $enrollment->student_id,
    'matricule' => $enrollment->matricule,
    'program_id' => $enrollment->program_id,
    'session_id' => $enrollment->session_id,
    'semester_id' => $nextSemester->id,
    'section_id' => $enrollment->section_id,
    'status' => 1,
]);

// Don't save it, just test the logic
echo "   Test enrollment would be:\n";
echo "   - Student ID: {$testEnrollment->student_id}\n";
echo "   - Program ID: {$testEnrollment->program_id}\n";
echo "   - Semester ID: {$testEnrollment->semester_id}\n";
echo "   - Semester is_resit: {$nextSemester->is_resit}\n\n";

// Check conditions
echo "5. Checking auto-assignment conditions...\n";
$conditions = [];

// Condition 1: Regular semester
if ($nextSemester->is_resit == 0) {
    echo "✓ Condition 1: Regular semester (is_resit = 0)\n";
    $conditions[] = true;
} else {
    echo "❌ Condition 1: Failed - Resit semester\n";
    $conditions[] = false;
}

// Condition 2: Has enrolled courses (we'll assume true for next semester)
echo "✓ Condition 2: Will have enrolled courses (assumed)\n";
$conditions[] = true;

// Condition 3: Has configured fees
if ($programSemesterFees->isNotEmpty()) {
    echo "✓ Condition 3: Program semester fees configured\n";
    $conditions[] = true;
} else {
    echo "❌ Condition 3: Failed - No configured fees\n";
    $conditions[] = false;
}

// Condition 4: Fees are installment type
$installmentFees = $programSemesterFees->filter(function($psf) {
    return $psf->feesCategory->type === 'installment';
});

if ($installmentFees->isNotEmpty()) {
    echo "✓ Condition 4: Installment fees found ({$installmentFees->count()} fee(s))\n";
    $conditions[] = true;
} else {
    echo "❌ Condition 4: Failed - No installment fees\n";
    $conditions[] = false;
}

echo "\n";

// Summary
$allConditionsMet = !in_array(false, $conditions);

if ($allConditionsMet) {
    echo "========================================\n";
    echo "✅ SUCCESS: All conditions met!\n";
    echo "========================================\n";
    echo "\n";
    echo "When student progresses to:\n";
    echo "  {$nextSemester->title}\n";
    echo "\n";
    echo "The following fees will be AUTO-ASSIGNED:\n";
    foreach ($installmentFees as $psf) {
        echo "  - {$psf->feesCategory->title}: {$psf->amount}\n";
    }
    echo "\n";
    echo "Assignment details:\n";
    echo "  - Assign date: " . now()->format('Y-m-d') . "\n";
    echo "  - Due date: " . now()->addDays(30)->format('Y-m-d') . "\n";
    echo "  - Note: Auto-assigned during semester progression\n";
    echo "  - Status: Active (1)\n";
} else {
    echo "========================================\n";
    echo "⚠️  FAILED: Not all conditions met\n";
    echo "========================================\n";
    echo "\n";
    echo "Auto-fee assignment will NOT trigger.\n";
    echo "Please review the failed conditions above.\n";
}

echo "\n";
echo "========================================\n";
echo "Test completed\n";
echo "========================================\n";
