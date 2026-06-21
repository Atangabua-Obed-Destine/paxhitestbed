<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$studentId = $argv[1] ?? 'PAX25TSC001H';
$student = App\Models\Student::where('student_id', $studentId)->first();

if (!$student) {
    echo "Student not found: $studentId\n";
    exit(1);
}

$enrollment = $student->currentEnroll;

echo "Student: {$student->first_name} {$student->last_name} ($studentId)\n";
echo "Current Semester: {$enrollment->semester->title} (Year: {$enrollment->semester->year}, Type: {$enrollment->semester->semester_type})\n\n";

// Find the correct resit semester (matching year and type)
$correctResitSemester = App\Models\Semester::where('is_resit', 1)
    ->where('status', 1)
    ->where('year', $enrollment->semester->year)
    ->where('semester_type', $enrollment->semester->semester_type)
    ->first();

if (!$correctResitSemester) {
    echo "ERROR: No resit semester found matching Year {$enrollment->semester->year}, Type {$enrollment->semester->semester_type}\n";
    exit(1);
}

echo "Correct Resit Semester: {$correctResitSemester->title} (ID: {$correctResitSemester->id})\n\n";

// Update resit requests
$requests = App\Models\ResitRequest::where('student_enroll_id', $enrollment->id)
    ->where('workflow_state', 'scheduled')
    ->get();

echo "Updating {$requests->count()} resit requests...\n";

foreach ($requests as $request) {
    $oldSemesterId = $request->resit_semester_id;
    $request->resit_semester_id = $correctResitSemester->id;
    $request->save();
    
    echo "  ✓ Updated request for {$request->subject->title}: Semester {$oldSemesterId} → {$correctResitSemester->id}\n";
}

echo "\nDone! All resit requests updated.\n";
