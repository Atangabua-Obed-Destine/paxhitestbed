<?php

/*
 * Test Transfer-In Fee Assignment Logic
 * Demonstrates smart semester filtering for transfer students
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Semester;
use App\Models\ProgramSemesterFee;

echo "========================================\n";
echo "Transfer-In Fee Assignment Logic Test\n";
echo "========================================\n\n";

// Get program ID from command line or use default
$programId = $argv[1] ?? 5; // test programs

echo "Testing with Program ID: {$programId}\n\n";

// Test different transfer scenarios
$scenarios = [
    [
        'year' => 1,
        'semester_type' => 1,
        'description' => 'Transfer INTO Year 1, First Semester (Beginning of year)',
    ],
    [
        'year' => 1,
        'semester_type' => 2,
        'description' => 'Transfer INTO Year 1, Second Semester (Mid-year)',
    ],
    [
        'year' => 2,
        'semester_type' => 1,
        'description' => 'Transfer INTO Year 2, First Semester (Beginning of year)',
    ],
    [
        'year' => 2,
        'semester_type' => 2,
        'description' => 'Transfer INTO Year 2, Second Semester (Mid-year)',
    ],
];

foreach ($scenarios as $index => $scenario) {
    echo str_repeat("=", 60) . "\n";
    echo "SCENARIO " . ($index + 1) . ": {$scenario['description']}\n";
    echo str_repeat("=", 60) . "\n";
    
    $academicYear = $scenario['year'];
    $currentSemesterType = $scenario['semester_type'];
    
    // Find the enrollment semester
    $enrollmentSemester = Semester::where('year', $academicYear)
        ->where('semester_type', $currentSemesterType)
        ->where('is_resit', 0)
        ->where('status', 1)
        ->first();
    
    if (!$enrollmentSemester) {
        echo "⚠️  Semester not found for Year {$academicYear}, Type {$currentSemesterType}\n\n";
        continue;
    }
    
    echo "Student transfers into: {$enrollmentSemester->title}\n";
    echo "Academic Year: {$academicYear}\n";
    echo "Semester Type: {$currentSemesterType}\n\n";
    
    // Get all semesters for this year
    $yearSemesters = Semester::where('year', $academicYear)
        ->where('is_resit', 0)
        ->where('status', 1)
        ->orderBy('semester_type', 'asc')
        ->get();
    
    echo "All semesters in Year {$academicYear}:\n";
    foreach ($yearSemesters as $sem) {
        echo "  - {$sem->title} (Type: {$sem->semester_type})\n";
    }
    echo "\n";
    
    // Simulate the smart logic
    echo "SMART LOGIC APPLICATION:\n";
    echo str_repeat("-", 60) . "\n";
    
    $willAssign = [];
    $willSkip = [];
    
    foreach ($yearSemesters as $yearSemester) {
        // Get configured fees
        $configuredFees = ProgramSemesterFee::where('program_id', $programId)
            ->where('semester_id', $yearSemester->id)
            ->where('status', 1)
            ->with('feesCategory')
            ->get();
        
        $feeInfo = [];
        foreach ($configuredFees as $fee) {
            $feeInfo[] = "{$fee->feesCategory->title}: {$fee->amount}";
        }
        
        $feeCount = $configuredFees->count();
        $feeList = $feeCount > 0 ? implode(', ', $feeInfo) : 'No fees configured';
        
        // Apply the smart logic
        if ($yearSemester->semester_type < $currentSemesterType) {
            // SKIP past semesters
            echo "  ❌ SKIP: {$yearSemester->title}\n";
            echo "     Reason: Past semester (Type {$yearSemester->semester_type} < Current {$currentSemesterType})\n";
            echo "     Fees configured: {$feeList}\n";
            echo "     → Student already completed this semester at previous institution\n\n";
            
            $willSkip[] = $yearSemester->title;
        } else {
            // ASSIGN for current and future semesters
            $isCurrent = ($yearSemester->semester_type == $currentSemesterType);
            $dueDays = $isCurrent ? 30 : 60;
            
            echo "  ✅ ASSIGN: {$yearSemester->title}\n";
            echo "     Status: " . ($isCurrent ? "CURRENT semester" : "FUTURE semester") . "\n";
            echo "     Fees: {$feeList}\n";
            echo "     Due: {$dueDays} days from enrollment\n";
            echo "     → Student needs to pay for this semester\n\n";
            
            $willAssign[] = $yearSemester->title;
        }
    }
    
    echo "SUMMARY:\n";
    echo "  Fees WILL be assigned for: " . (count($willAssign) > 0 ? implode(', ', $willAssign) : 'NONE') . "\n";
    echo "  Fees WILL BE skipped for: " . (count($willSkip) > 0 ? implode(', ', $willSkip) : 'NONE') . "\n";
    echo "\n\n";
}

echo "========================================\n";
echo "KEY POINTS:\n";
echo "========================================\n";
echo "1. Transfer students only pay for CURRENT and FUTURE semesters\n";
echo "2. Past semesters are SKIPPED (already completed elsewhere)\n";
echo "3. Current semester gets 30-day due date\n";
echo "4. Future semesters get 60-day due date\n";
echo "5. Regular enrollments assign ALL year semesters\n";
echo "\n";
echo "This prevents overcharging transfer students for\n";
echo "semesters they've already completed!\n";
echo "========================================\n";
