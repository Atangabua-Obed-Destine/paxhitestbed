<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$students = \App\Models\Student::where('first_name', 'LIKE', "%mansa%")
    ->get();

foreach ($students as $student) {
    echo "=== Student: {$student->first_name} {$student->last_name} (ID: {$student->id}) ===\n";
    
    // Get ALL enrollments regardless of status
    $enrollments = \App\Models\StudentEnroll::where('student_id', $student->id)
        ->orderBy('id', 'desc')
        ->get();
    
    echo "Total enrollments (all statuses): " . $enrollments->count() . "\n\n";
    
    foreach ($enrollments as $enroll) {
        echo "Enrollment ID: {$enroll->id} | Status: {$enroll->status}\n";
        echo "Matricule: {$enroll->matricule}\n";
        echo "Program ID: {$enroll->program_id}";
        if ($enroll->program) {
            echo " - {$enroll->program->title}";
        }
        echo "\n---\n";
    }
    
    $uniqueMatricules = $enrollments->pluck('matricule')->unique();
    echo "\n*** Total unique matricules (all statuses): " . $uniqueMatricules->count() . " ***\n";
    foreach ($uniqueMatricules as $mat) {
        echo "  - {$mat}\n";
    }
    
    // Check active only
    $activeEnrollments = $enrollments->where('status', '1');
    $activeMatricules = $activeEnrollments->pluck('matricule')->unique();
    echo "\n*** Active (status=1) unique matricules: " . $activeMatricules->count() . " ***\n";
    foreach ($activeMatricules as $mat) {
        echo "  - {$mat}\n";
    }
}
