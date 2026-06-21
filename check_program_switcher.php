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
    
    // Get ALL enrollments (active and inactive)
    $tempEnrollments = \App\Models\StudentEnroll::where('student_id', $student->id)
        ->with(['program.degreeType', 'program.faculty', 'semester', 'session'])
        ->orderBy('id', 'desc')
        ->get();
    
    echo "Total enrollments (all statuses): " . $tempEnrollments->count() . "\n";
    
    // Group by unique matricule
    $allEnrollments = $tempEnrollments->groupBy('matricule')->map(function($group) {
        return $group->sortByDesc('id')->first();
    })->values();
    
    echo "Unique matricules (all statuses): " . $allEnrollments->count() . "\n\n";
    
    if ($allEnrollments->count() > 0) {
        echo "List of unique enrollments (all statuses):\n";
        foreach ($allEnrollments as $enrollment) {
            echo "  - Enrollment ID: {$enrollment->id}\n";
            echo "    Matricule: {$enrollment->matricule}\n";
            echo "    Program: {$enrollment->program->title}\n";
            echo "    Status: " . ($enrollment->status == '1' ? 'ACTIVE' : 'INACTIVE') . " ({$enrollment->status})\n";
            echo "    Session: {$enrollment->session->title}\n";
            echo "    Semester: {$enrollment->semester->title}\n";
            echo "    ---\n";
        }
    }
    
    echo "\nShould show program switcher? " . ($allEnrollments->count() > 1 ? "YES" : "NO") . "\n";
    echo "===================================\n\n";
}
