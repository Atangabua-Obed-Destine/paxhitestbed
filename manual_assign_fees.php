<?php

/*
 * Test script to manually assign fees to enrollment 89
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StudentEnroll;
use App\Models\ProgramSemesterFee;
use App\Models\Fee;
use App\Models\Semester;

echo "========================================\n";
echo "Manual Fee Assignment Test\n";
echo "========================================\n\n";

$enrollmentId = 89;

echo "Getting enrollment ID: {$enrollmentId}...\n";
$enrollment = StudentEnroll::find($enrollmentId);

if (!$enrollment) {
    echo "❌ Enrollment not found\n";
    exit;
}

echo "✓ Enrollment found\n";
echo "  - Student ID: {$enrollment->student_id}\n";
echo "  - Program ID: {$enrollment->program_id}\n";
echo "  - Semester ID: {$enrollment->semester_id}\n\n";

// Check semester
echo "1. Checking if semester is regular...\n";
$semester = Semester::find($enrollment->semester_id);
if ($semester && $semester->is_resit) {
    echo "❌ This is a resit semester - fees won't be assigned\n";
    exit;
}
echo "✓ Regular semester\n\n";

// Check configured fees
echo "2. Checking for configured fees...\n";
$configuredFees = ProgramSemesterFee::where('program_id', $enrollment->program_id)
    ->where('semester_id', $enrollment->semester_id)
    ->where('status', 1)
    ->with('feesCategory')
    ->get();

if ($configuredFees->isEmpty()) {
    echo "❌ No fees configured\n";
    exit;
}

echo "✓ Found {$configuredFees->count()} configured fee(s):\n";
foreach ($configuredFees as $config) {
    echo "  - {$config->feesCategory->title}: {$config->amount}\n";
}
echo "\n";

// Assign fees
echo "3. Assigning fees...\n";
$feesCreated = 0;

foreach ($configuredFees as $feeConfig) {
    // Check if already exists
    $existingFee = Fee::where('student_enroll_id', $enrollment->id)
        ->where('category_id', $feeConfig->fees_category_id)
        ->first();

    if ($existingFee) {
        echo "  ⚠️  Fee already exists for category {$feeConfig->feesCategory->title} - skipping\n";
        continue;
    }

    // Create fee
    $fee = new Fee();
    $fee->student_enroll_id = $enrollment->id;
    $fee->category_id = $feeConfig->fees_category_id;
    $fee->fee_amount = $feeConfig->amount;
    $fee->assign_date = now()->format('Y-m-d');
    $fee->due_date = now()->addDays(30)->format('Y-m-d');
    $fee->note = 'Manually assigned via test script';
    $fee->status = 1;
    $fee->save();

    echo "  ✓ Created fee: {$feeConfig->feesCategory->title} - {$feeConfig->amount}\n";
    $feesCreated++;
}

echo "\n";
echo "========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Enrollment ID: {$enrollment->id}\n";
echo "Fees Created: {$feesCreated}\n";
echo "\n";

if ($feesCreated > 0) {
    echo "✅ SUCCESS: Fees assigned!\n";
    echo "\nView at:\n";
    echo "- /admin/fees-student\n";
    echo "- Student profile → Fees tab\n";
} else {
    echo "ℹ️  No new fees created (may already exist)\n";
}

echo "\n========================================\n";
