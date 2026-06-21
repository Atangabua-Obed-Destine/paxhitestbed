<?php

/*
 * Diagnostic script to check why fees are not auto-assigning
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StudentEnroll;
use App\Models\ProgramSemesterFee;
use App\Models\Fee;

echo "========================================\n";
echo "Fee Assignment Diagnostic\n";
echo "========================================\n\n";

// Get the most recent enrollment
echo "1. Getting most recent enrollment...\n";
$enrollment = StudentEnroll::orderBy('id', 'desc')->first();

if (!$enrollment) {
    echo "❌ No enrollments found\n";
    exit;
}

echo "✓ Found enrollment ID: {$enrollment->id}\n";
echo "  - Student ID: {$enrollment->student_id}\n";
echo "  - Program ID: {$enrollment->program_id}\n";
echo "  - Semester ID: {$enrollment->semester_id}\n";
echo "  - Created: {$enrollment->created_at}\n\n";

// Check for configured fees
echo "2. Checking for configured program semester fees...\n";
$configuredFees = ProgramSemesterFee::where('program_id', $enrollment->program_id)
    ->where('semester_id', $enrollment->semester_id)
    ->where('status', 1)
    ->with('feesCategory')
    ->get();

if ($configuredFees->isEmpty()) {
    echo "❌ No program semester fees configured\n";
    echo "   Program ID: {$enrollment->program_id}\n";
    echo "   Semester ID: {$enrollment->semester_id}\n";
    echo "\n   ACTION NEEDED: Configure fees at /admin/program-semester-fee\n";
} else {
    echo "✓ Found {$configuredFees->count()} configured fee(s):\n";
    foreach ($configuredFees as $config) {
        echo "  - ID: {$config->id}\n";
        echo "    Category: {$config->feesCategory->title} (ID: {$config->fees_category_id})\n";
        echo "    Amount: {$config->amount}\n";
        echo "    Type: {$config->feesCategory->type}\n";
        echo "    Status: " . ($config->status ? 'Active' : 'Inactive') . "\n\n";
    }
}

// Check if fees were assigned to this enrollment
echo "3. Checking if fees were assigned to this enrollment...\n";
$assignedFees = Fee::where('student_enroll_id', $enrollment->id)
    ->with('category')
    ->get();

if ($assignedFees->isEmpty()) {
    echo "❌ No fees assigned to this enrollment\n";
    echo "\n   PROBLEM: Fees should have been auto-assigned!\n";
    
    if (!$configuredFees->isEmpty()) {
        echo "\n   DIAGNOSIS:\n";
        echo "   - Configuration exists: YES\n";
        echo "   - Fees assigned: NO\n";
        echo "   - Possible causes:\n";
        echo "     1. Error during enrollment creation (check logs)\n";
        echo "     2. autoAssignProgramSemesterFees() not executed\n";
        echo "     3. Exception thrown and caught silently\n";
        echo "\n   Check storage/logs/laravel.log for errors\n";
    }
} else {
    echo "✓ Found {$assignedFees->count()} assigned fee(s):\n";
    foreach ($assignedFees as $fee) {
        echo "  - Category: {$fee->category->title}\n";
        echo "    Amount: {$fee->fee_amount}\n";
        echo "    Assigned: {$fee->assign_date}\n";
        echo "    Due: {$fee->due_date}\n";
        echo "    Paid: {$fee->paid_amount}\n";
        echo "    Status: " . ($fee->status ? 'Active' : 'Inactive') . "\n";
        echo "    Note: {$fee->note}\n\n";
    }
}

// Check recent logs
echo "4. Checking recent log entries...\n";
$logFile = storage_path('logs/laravel.log');

if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -100); // Last 100 lines
    
    $relevantLogs = array_filter($recentLines, function($line) {
        return stripos($line, 'fee') !== false || 
               stripos($line, 'enrollment') !== false ||
               stripos($line, 'auto-assign') !== false;
    });
    
    if (!empty($relevantLogs)) {
        echo "✓ Found " . count($relevantLogs) . " relevant log entries (last 100 lines):\n";
        foreach (array_slice($relevantLogs, -10) as $log) {
            echo "  " . trim($log) . "\n";
        }
    } else {
        echo "⚠️  No relevant log entries found in last 100 lines\n";
    }
} else {
    echo "⚠️  Log file not found\n";
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Enrollment ID: {$enrollment->id}\n";
echo "Configured Fees: {$configuredFees->count()}\n";
echo "Assigned Fees: {$assignedFees->count()}\n";

if ($configuredFees->count() > 0 && $assignedFees->count() == 0) {
    echo "\n⚠️  WARNING: Fees should have been assigned but weren't!\n";
    echo "Check the log output above for clues.\n";
} elseif ($configuredFees->count() == 0) {
    echo "\nℹ️  INFO: No fees configured for this program/semester.\n";
    echo "Configure at: /admin/program-semester-fee\n";
} elseif ($assignedFees->count() > 0) {
    echo "\n✅ SUCCESS: Fees were assigned correctly!\n";
}

echo "\n========================================\n";
