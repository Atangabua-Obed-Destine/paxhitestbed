<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$students = \App\Models\Student::where('first_name', 'LIKE', "%mansa%")
    ->where('status', '1')
    ->get();

foreach ($students as $student) {
    echo "=== Student: {$student->first_name} {$student->last_name} (ID: {$student->id}) ===\n";
    
    $enrollments = \App\Models\StudentEnroll::where('student_id', $student->id)
        ->where('status', '1')
        ->orderBy('id', 'desc')
        ->get();
    
    echo "Total enrollments with status=1: " . $enrollments->count() . "\n\n";
    
    foreach ($enrollments as $enroll) {
        echo "Enrollment ID: {$enroll->id}\n";
        echo "Matricule: {$enroll->matricule}\n";
        echo "Program ID: {$enroll->program_id}\n";
        if ($enroll->program) {
            echo "Program: {$enroll->program->title}\n";
        }
        echo "Session: {$enroll->session}, Semester: {$enroll->semester}\n";
        echo "---\n";
    }
    
    $uniqueMatricules = $enrollments->pluck('matricule')->unique();
    echo "\n*** Unique matricules count: " . $uniqueMatricules->count() . " ***\n";
    foreach ($uniqueMatricules as $mat) {
        echo "  - {$mat}\n";
    }
    
    // Now test the grouping logic
    echo "\n*** Testing groupBy logic ***\n";
    $grouped = $enrollments->groupBy('matricule');
    echo "Groups created: " . $grouped->count() . "\n";
    foreach ($grouped as $matricule => $group) {
        echo "Matricule '{$matricule}': {$group->count()} enrollments\n";
    }
    
    $result = $enrollments->groupBy('matricule')->map(function($group) {
        return $group->sortByDesc('id')->first();
    })->values();
    
    echo "\nAfter groupBy->map->values: " . $result->count() . " records\n";
    foreach ($result as $r) {
        echo "  - {$r->matricule} (Enroll ID: {$r->id})\n";
    }
}
