<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get a student ID to check - replace with the student you're seeing
echo "Enter student first name: ";
$name = trim(fgets(STDIN));

$students = \App\Models\Student::where('first_name', 'LIKE', "%{$name}%")
    ->where('status', '1')
    ->get();

foreach ($students as $student) {
    echo "\n=== Student: {$student->first_name} {$student->last_name} (ID: {$student->id}, Student ID: {$student->student_id}) ===\n";
    
    $enrollments = \App\Models\StudentEnroll::where('student_id', $student->id)
        ->where('status', '1')
        ->orderBy('id', 'desc')
        ->get();
    
    echo "Total enrollments: " . $enrollments->count() . "\n\n";
    
    foreach ($enrollments as $enroll) {
        echo "Enrollment ID: {$enroll->id}\n";
        echo "Matricule: {$enroll->matricule}\n";
        echo "Program ID: {$enroll->program_id}\n";
        if ($enroll->program) {
            echo "Program: {$enroll->program->title} ({$enroll->program->shortcode})\n";
        }
        echo "Session: {$enroll->session}\n";
        echo "Semester: {$enroll->semester}\n";
        echo "Status: {$enroll->status}\n";
        echo "---\n";
    }
    
    // Check unique matricules
    $uniqueMatricules = $enrollments->pluck('matricule')->unique();
    echo "\nUnique matricules: " . $uniqueMatricules->count() . "\n";
    foreach ($uniqueMatricules as $mat) {
        echo "  - {$mat}\n";
    }
}
