<?php

/*
 * Verify Year-Based Fee Assignment
 * Shows what fees WOULD be assigned for a specific enrollment
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\ProgramSemesterFee;
use App\Models\Fee;

// Get enrollment ID from command line or use the latest
$enrollmentId = $argv[1] ?? null;

if ($enrollmentId) {
    $enrollment = StudentEnroll::with(['student', 'program', 'semester'])->find($enrollmentId);
} else {
    $enrollment = StudentEnroll::with(['student', 'program', 'semester'])
        ->whereHas('semester', function($query) {
            $query->where('is_resit', 0);
        })
        ->where('status', 1)
        ->orderBy('id', 'desc')
        ->first();
}

if (!$enrollment) {
    echo "❌ Enrollment not found\n";
    echo "Usage: php verify_year_fees.php [enrollment_id]\n";
    exit;
}

echo "========================================\n";
echo "Year-Based Fee Assignment Verification\n";
echo "========================================\n\n";

echo "Enrollment Details:\n";
echo "  ID: {$enrollment->id}\n";
echo "  Student: {$enrollment->student->student_id} - {$enrollment->student->first_name} {$enrollment->student->last_name}\n";
echo "  Program: {$enrollment->program->title}\n";
echo "  Semester: {$enrollment->semester->title}\n";
echo "  Year: {$enrollment->semester->year}\n";
echo "  Created: {$enrollment->created_at}\n\n";

// Skip if resit
if ($enrollment->semester->is_resit) {
    echo "⚠️  This is a RESIT semester - fees not auto-assigned\n";
    exit;
}

$academicYear = $enrollment->semester->year;
$programId = $enrollment->program_id;
$studentId = $enrollment->student_id;

// Get all semesters for this year
$yearSemesters = Semester::where('year', $academicYear)
    ->where('is_resit', 0)
    ->where('status', 1)
    ->whereHas('programs', function($query) use ($programId) {
        $query->where('program_id', $programId);
    })
    ->orderBy('semester_type', 'asc')
    ->get();

echo "Year {$academicYear} Semesters ({$yearSemesters->count()}):\n";
foreach ($yearSemesters as $sem) {
    echo "  - {$sem->title}\n";
}
echo "\n";

// Get all student's enrollments for duplicate checking
$studentEnrollments = StudentEnroll::where('student_id', $studentId)
    ->where('program_id', $programId)
    ->pluck('id')
    ->toArray();

echo "Student has {" . count($studentEnrollments) . "} enrollment(s) in this program\n\n";

// Process each semester
$totalFeesToAssign = 0;
$totalFeesSkipped = 0;
$assignments = [];

echo "========================================\n";
echo "Fee Assignment Simulation\n";
echo "========================================\n\n";

foreach ($yearSemesters as $yearSemester) {
    echo "Semester: {$yearSemester->title}\n";
    echo str_repeat("-", 40) . "\n";
    
    // Get configured fees
    $configuredFees = ProgramSemesterFee::where('program_id', $programId)
        ->where('semester_id', $yearSemester->id)
        ->where('status', 1)
        ->with('feesCategory')
        ->get();
    
    if ($configuredFees->isEmpty()) {
        echo "  ⚠️  No fees configured for this semester\n\n";
        continue;
    }
    
    foreach ($configuredFees as $feeConfig) {
        // Check if fee already exists
        $existingFee = Fee::whereIn('student_enroll_id', $studentEnrollments)
            ->where('category_id', $feeConfig->fees_category_id)
            ->whereHas('studentEnroll.semester', function($query) use ($yearSemester) {
                $query->where('year', $yearSemester->year);
            })
            ->first();
        
        if ($existingFee) {
            echo "  ⏭️  {$feeConfig->feesCategory->title}: {$feeConfig->amount}\n";
            echo "      Status: ALREADY ASSIGNED (Fee ID: {$existingFee->id})\n";
            echo "      Assigned to: Enrollment #{$existingFee->student_enroll_id}\n";
            echo "      Current status: " . ($existingFee->status == 0 ? 'Unpaid' : ($existingFee->status == 1 ? 'Paid' : 'Partial')) . "\n\n";
            $totalFeesSkipped++;
        } else {
            $daysToAdd = ($yearSemester->semester_type == 1) ? 30 : 60;
            $dueDate = now()->addDays($daysToAdd)->format('Y-m-d');
            
            echo "  ✅ {$feeConfig->feesCategory->title}: {$feeConfig->amount}\n";
            echo "      Status: WILL BE ASSIGNED\n";
            echo "      Due date: {$dueDate} ({$daysToAdd} days)\n";
            echo "      Note: Auto-assigned for {$yearSemester->title} (Year {$academicYear})\n\n";
            
            $totalFeesToAssign++;
            $assignments[] = [
                'semester' => $yearSemester->title,
                'category' => $feeConfig->feesCategory->title,
                'amount' => $feeConfig->amount,
                'due_date' => $dueDate,
            ];
        }
    }
}

echo "========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Fees to be assigned: {$totalFeesToAssign}\n";
echo "Fees already exist: {$totalFeesSkipped}\n";
echo "Total configured: " . ($totalFeesToAssign + $totalFeesSkipped) . "\n\n";

if ($totalFeesToAssign > 0) {
    echo "✅ These fees will be auto-assigned on next enrollment/progression:\n\n";
    foreach ($assignments as $assignment) {
        echo "  • {$assignment['category']}: {$assignment['amount']}\n";
        echo "    Semester: {$assignment['semester']}\n";
        echo "    Due: {$assignment['due_date']}\n\n";
    }
} else {
    echo "ℹ️  All fees for Year {$academicYear} are already assigned\n";
}

echo "========================================\n";
echo "To view actual fees:\n";
echo "Admin: /admin/fees-student\n";
echo "Student: /student/fees\n";
echo "========================================\n";
