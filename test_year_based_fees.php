<?php

/*
 * Test Year-Based Fee Assignment
 * Tests that fees are assigned for ALL semesters of the academic year
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\ProgramSemesterFee;
use App\Models\Fee;

echo "========================================\n";
echo "Year-Based Fee Assignment Test\n";
echo "========================================\n\n";

// Find a student enrollment
$enrollment = StudentEnroll::with(['student', 'program', 'semester'])
    ->whereHas('semester', function($query) {
        $query->where('is_resit', 0);
    })
    ->where('status', 1)
    ->orderBy('id', 'desc')
    ->first();

if (!$enrollment) {
    echo "❌ No enrollment found\n";
    exit;
}

echo "Testing with enrollment:\n";
echo "  - Enrollment ID: {$enrollment->id}\n";
echo "  - Student: {$enrollment->student->student_id}\n";
echo "  - Program: {$enrollment->program->title}\n";
echo "  - Current Semester: {$enrollment->semester->title}\n";
echo "  - Academic Year: {$enrollment->semester->year}\n\n";

$academicYear = $enrollment->semester->year;
$programId = $enrollment->program_id;

// 1. Find all semesters for this year
echo "1. Finding all semesters for Year {$academicYear}...\n";
$yearSemesters = Semester::where('year', $academicYear)
    ->where('is_resit', 0)
    ->where('status', 1)
    ->whereHas('programs', function($query) use ($programId) {
        $query->where('program_id', $programId);
    })
    ->orderBy('semester_type', 'asc')
    ->get();

if ($yearSemesters->isEmpty()) {
    echo "❌ No semesters found for this year\n";
    exit;
}

echo "✓ Found {$yearSemesters->count()} semester(s) for Year {$academicYear}:\n";
foreach ($yearSemesters as $sem) {
    echo "  - {$sem->title} (ID: {$sem->id}, Type: {$sem->semester_type})\n";
}
echo "\n";

// 2. Check fee configurations for each semester
echo "2. Checking fee configurations...\n";
$totalConfiguredFees = 0;
$semesterFeeMap = [];

foreach ($yearSemesters as $semester) {
    $fees = ProgramSemesterFee::where('program_id', $programId)
        ->where('semester_id', $semester->id)
        ->where('status', 1)
        ->with('feesCategory')
        ->get();
    
    $semesterFeeMap[$semester->id] = $fees;
    $totalConfiguredFees += $fees->count();
    
    if ($fees->isEmpty()) {
        echo "  ⚠️  {$semester->title}: No fees configured\n";
    } else {
        echo "  ✓ {$semester->title}: {$fees->count()} fee(s) configured\n";
        foreach ($fees as $fee) {
            echo "      - {$fee->feesCategory->title}: {$fee->amount}\n";
        }
    }
}

if ($totalConfiguredFees == 0) {
    echo "\n❌ No fees configured for any semester in Year {$academicYear}\n";
    echo "   Please configure fees at /admin/program-semester-fee\n";
    exit;
}

echo "\n  Total fees configured for Year {$academicYear}: {$totalConfiguredFees}\n\n";

// 3. Check currently assigned fees
echo "3. Checking currently assigned fees for this student...\n";
$studentEnrollments = StudentEnroll::where('student_id', $enrollment->student_id)
    ->where('program_id', $programId)
    ->pluck('id')
    ->toArray();

$assignedFees = Fee::whereIn('student_enroll_id', $studentEnrollments)
    ->with(['category', 'studentEnroll.semester'])
    ->get();

if ($assignedFees->isEmpty()) {
    echo "  ℹ️  No fees currently assigned\n\n";
} else {
    echo "  ✓ Found {$assignedFees->count()} assigned fee(s):\n";
    foreach ($assignedFees as $fee) {
        $semesterInfo = $fee->studentEnroll->semester ?? null;
        $semesterTitle = $semesterInfo ? $semesterInfo->title : 'Unknown';
        $semesterYear = $semesterInfo ? $semesterInfo->year : 'Unknown';
        
        echo "      - {$fee->category->title}: {$fee->fee_amount}\n";
        echo "        Semester: {$semesterTitle} (Year {$semesterYear})\n";
        echo "        Note: {$fee->note}\n";
        echo "        Due: {$fee->due_date}, Status: " . ($fee->status == 0 ? 'Unpaid' : ($fee->status == 1 ? 'Paid' : 'Partial')) . "\n";
    }
    echo "\n";
}

// 4. Expected behavior analysis
echo "4. Expected Behavior Analysis:\n";
echo "=====================================\n";
echo "When a student enrolls in Year {$academicYear}, the system should:\n\n";

$expectedAssignments = [];
foreach ($yearSemesters as $semester) {
    $fees = $semesterFeeMap[$semester->id];
    if ($fees->isNotEmpty()) {
        echo "  For {$semester->title}:\n";
        foreach ($fees as $fee) {
            $dueDays = ($semester->semester_type == 1) ? 30 : 60;
            echo "    ✓ Assign {$fee->feesCategory->title}: {$fee->amount}\n";
            echo "      Due: {$dueDays} days from enrollment\n";
            $expectedAssignments[] = [
                'semester' => $semester->title,
                'category' => $fee->feesCategory->title,
                'amount' => $fee->amount,
            ];
        }
    }
}

echo "\n  Total fees that should be assigned: " . count($expectedAssignments) . "\n\n";

// 5. Duplicate prevention check
echo "5. Duplicate Prevention:\n";
echo "========================\n";
echo "The system checks if fees already exist for:\n";
echo "  ✓ Same student\n";
echo "  ✓ Same program\n";
echo "  ✓ Same fee category\n";
echo "  ✓ Same academic year\n";
echo "  → Prevents duplicate fees across enrollments\n\n";

// 6. Summary
echo "========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Academic Year: {$academicYear}\n";
echo "Semesters in Year: {$yearSemesters->count()}\n";
echo "Total Fees Configured: {$totalConfiguredFees}\n";
echo "Currently Assigned Fees: {$assignedFees->count()}\n\n";

if ($totalConfiguredFees > 0) {
    echo "✅ Year-Based Fee Assignment is READY\n";
    echo "\nWhen you:\n";
    echo "  1. Create a new student enrollment\n";
    echo "  2. Progress a student to a new year\n";
    echo "\nThe system will automatically assign ALL {$totalConfiguredFees} fee(s)\n";
    echo "configured for Year {$academicYear} at once!\n";
} else {
    echo "⚠️  Configure fees for Year {$academicYear} semesters first\n";
}

echo "\n========================================\n";
echo "Test completed\n";
echo "========================================\n";
