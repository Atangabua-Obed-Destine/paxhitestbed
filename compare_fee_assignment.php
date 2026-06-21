<?php

/*
 * Compare Regular vs Transfer-In Fee Assignment
 * Shows the difference between the two approaches
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Semester;
use App\Models\ProgramSemesterFee;

echo "========================================\n";
echo "Regular vs Transfer-In Fee Comparison\n";
echo "========================================\n\n";

$programId = $argv[1] ?? 5;
$year = $argv[2] ?? 1;

echo "Configuration:\n";
echo "  Program ID: {$programId}\n";
echo "  Academic Year: {$year}\n\n";

// Get all semesters for this year
$yearSemesters = Semester::where('year', $year)
    ->where('is_resit', 0)
    ->where('status', 1)
    ->orderBy('semester_type', 'asc')
    ->get();

if ($yearSemesters->isEmpty()) {
    echo "❌ No semesters found for Year {$year}\n";
    exit;
}

echo "Semesters in Year {$year}:\n";
foreach ($yearSemesters as $sem) {
    $fees = ProgramSemesterFee::where('program_id', $programId)
        ->where('semester_id', $sem->id)
        ->where('status', 1)
        ->with('feesCategory')
        ->get();
    
    echo "  {$sem->title} (Type {$sem->semester_type}):\n";
    if ($fees->isEmpty()) {
        echo "    - No fees configured\n";
    } else {
        foreach ($fees as $fee) {
            echo "    - {$fee->feesCategory->title}: {$fee->amount}\n";
        }
    }
}
echo "\n";

// Show comparison for each semester type
foreach ($yearSemesters as $enrollmentSemester) {
    echo str_repeat("=", 80) . "\n";
    echo "ENROLLMENT INTO: {$enrollmentSemester->title}\n";
    echo str_repeat("=", 80) . "\n\n";
    
    echo "┌" . str_repeat("─", 38) . "┬" . str_repeat("─", 39) . "┐\n";
    echo "│ REGULAR ENROLLMENT (Year-Based)     │ TRANSFER-IN (Smart Filter)          │\n";
    echo "├" . str_repeat("─", 38) . "┼" . str_repeat("─", 39) . "┤\n";
    
    foreach ($yearSemesters as $yearSemester) {
        // Regular enrollment logic
        $regularStatus = "✓ ASSIGN ALL";
        $regularReason = "All year semesters";
        
        // Transfer-in logic
        if ($yearSemester->semester_type < $enrollmentSemester->semester_type) {
            $transferStatus = "✗ SKIP";
            $transferReason = "Past semester";
        } elseif ($yearSemester->semester_type == $enrollmentSemester->semester_type) {
            $transferStatus = "✓ ASSIGN";
            $transferReason = "Current (30 days)";
        } else {
            $transferStatus = "✓ ASSIGN";
            $transferReason = "Future (60 days)";
        }
        
        $semesterName = str_pad($yearSemester->title, 20);
        $regularInfo = str_pad($regularStatus, 14);
        $transferInfo = str_pad($transferStatus, 14);
        
        echo "│ {$semesterName} {$regularInfo} │ {$semesterName} {$transferInfo} │\n";
        
        $regularReasonPad = str_pad("  ({$regularReason})", 38);
        $transferReasonPad = str_pad("  ({$transferReason})", 39);
        echo "│{$regularReasonPad}│{$transferReasonPad}│\n";
    }
    
    echo "└" . str_repeat("─", 38) . "┴" . str_repeat("─", 39) . "┘\n\n";
    
    // Count fees
    $regularCount = $yearSemesters->count();
    $transferCount = $yearSemesters->filter(function($sem) use ($enrollmentSemester) {
        return $sem->semester_type >= $enrollmentSemester->semester_type;
    })->count();
    
    echo "Summary:\n";
    echo "  Regular Enrollment: Assigns fees for {$regularCount} semester(s)\n";
    echo "  Transfer-In: Assigns fees for {$transferCount} semester(s)\n";
    
    if ($transferCount < $regularCount) {
        $saved = $regularCount - $transferCount;
        echo "  → Transfer students save {$saved} semester fee(s)! ✓\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "KEY DIFFERENCES\n";
echo str_repeat("=", 80) . "\n\n";

echo "REGULAR ENROLLMENT:\n";
echo "  • Use Case: New students starting at your institution\n";
echo "  • Logic: Assign fees for ALL semesters of the academic year\n";
echo "  • Example: Enroll in Sem 1 → Get fees for Sem 1 + Sem 2\n";
echo "  • Controllers: StudentController, ApplicationController\n\n";

echo "TRANSFER-IN ENROLLMENT:\n";
echo "  • Use Case: Students transferring from another institution\n";
echo "  • Logic: Assign fees ONLY for current and future semesters\n";
echo "  • Example: Transfer into Sem 2 → Get fees for Sem 2 ONLY (skip Sem 1)\n";
echo "  • Controller: StudentTransferInController\n\n";

echo "WHY THE DIFFERENCE?\n";
echo "  Transfer students already completed past semesters elsewhere.\n";
echo "  They should NOT be charged for semesters they won't attend.\n";
echo "  This smart filtering prevents overcharging! ✓\n\n";

echo str_repeat("=", 80) . "\n";
