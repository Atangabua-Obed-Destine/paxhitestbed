<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== STUDENT PORTAL CONTROLLERS ANALYSIS ===\n\n";

$controllersDir = __DIR__ . '/app/Http/Controllers/Student';
$files = glob($controllersDir . '/*Controller.php');

$needsFix = [];
$alreadyFixed = [];
$noEnrollmentUsed = [];

foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);
    
    // Check if it uses selected_enrollment_id
    $usesSelectedEnrollment = strpos($content, 'selected_enrollment_id') !== false;
    
    // Check if it queries StudentEnroll
    $queriesEnrollment = strpos($content, 'StudentEnroll::') !== false || 
                         strpos($content, 'studentEnrolls') !== false;
    
    // Check if it has the problematic pattern
    $hasProblematicPattern = preg_match('/where\([\'"]status[\'"],\s*[\'"]1[\'"]/', $content) &&
                            preg_match('/Session::where\([\'"]status[\'"],\s*[\'"]1[\'"]/', $content);
    
    if ($queriesEnrollment) {
        if ($usesSelectedEnrollment) {
            $alreadyFixed[] = $filename;
        } elseif ($hasProblematicPattern) {
            $needsFix[] = $filename;
        } else {
            // Might not use enrollment at all
            $noEnrollmentUsed[] = $filename;
        }
    }
}

echo "CONTROLLERS THAT NEED FIXING (using current session instead of selected enrollment):\n";
foreach ($needsFix as $controller) {
    echo "  ✗ $controller\n";
}

echo "\nCONTROLLERS ALREADY FIXED (using selected_enrollment_id):\n";
foreach ($alreadyFixed as $controller) {
    echo "  ✓ $controller\n";
}

echo "\nCONTROLLERS THAT DON'T USE ENROLLMENT:\n";
foreach ($noEnrollmentUsed as $controller) {
    echo "  - $controller\n";
}

echo "\n\nTOTAL:\n";
echo "  Need Fix: " . count($needsFix) . "\n";
echo "  Already Fixed: " . count($alreadyFixed) . "\n";
echo "  No Enrollment: " . count($noEnrollmentUsed) . "\n";
