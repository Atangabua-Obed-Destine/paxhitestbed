<?php

/**
 * Script to migrate Catholic sacrament data from applications to student_enrolls
 * Run this once to populate existing student data
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentEnroll;
use App\Models\Application;

echo "Starting Catholic Students Data Migration...\n\n";

// Get all active student enrollments
$enrollments = StudentEnroll::with(['student'])->where('status', 1)->get();

$updated = 0;
$skipped = 0;

foreach ($enrollments as $enrollment) {
    if (!$enrollment->student) {
        $skipped++;
        continue;
    }

    // Find the corresponding application
    $application = Application::where('email', $enrollment->student->email)->first();
    
    if (!$application) {
        $skipped++;
        continue;
    }

    // Copy religion and sacrament data
    $enrollment->religion = $application->religion;
    $enrollment->is_catholic_baptised = $application->is_catholic_baptised ?? false;
    $enrollment->is_confirmed = $application->is_confirmed ?? false;
    $enrollment->has_first_communion = $application->has_first_communion ?? false;
    $enrollment->save();

    $updated++;
    
    if ($updated % 10 == 0) {
        echo "Updated $updated records...\n";
    }
}

echo "\n=== Migration Complete ===\n";
echo "Total Updated: $updated\n";
echo "Total Skipped: $skipped\n";
echo "==========================\n";
