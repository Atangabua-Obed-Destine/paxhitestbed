<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Program;
use App\Models\Faculty;
use App\Models\Batch;

echo "=== Testing Enrollment Matricule Generation ===\n\n";

try {
    // Get a test student
    $student = Student::with('batch', 'program.faculty')->first();
    
    if (!$student) {
        echo "No students found in database.\n";
        exit;
    }
    
    echo "Test Student:\n";
    echo "  - Name: {$student->first_name} {$student->last_name}\n";
    echo "  - Current Student ID: {$student->student_id}\n";
    echo "  - Batch: {$student->batch->title}\n";
    echo "  - Current Program: {$student->program->title}\n\n";
    
    // Test 1: Generate undergraduate matricule
    echo "Test 1: Generate Undergraduate Matricule\n";
    $undergradProgram = Program::where('academic_level', 'A')->first();
    if ($undergradProgram) {
        $matricule = Student::generateEnrollmentMatricule(
            $student->id,
            $undergradProgram->id,
            $student->batch_id
        );
        echo "  - Program: {$undergradProgram->title} (Level: {$undergradProgram->academic_level})\n";
        echo "  - Generated Matricule: {$matricule}\n";
        echo "  - Format: PAX + Year + Faculty + Number + A\n\n";
    } else {
        echo "  - No undergraduate programs found\n\n";
    }
    
    // Test 2: Simulate Masters program (by temporarily changing academic_level)
    echo "Test 2: Simulate Masters Matricule Generation\n";
    $mastersProgram = Program::first();
    
    if ($mastersProgram) {
        // Temporarily set to Masters level
        DB::table('programs')
          ->where('id', $mastersProgram->id)
          ->update(['academic_level' => 'M']);
        
        // Refresh the model
        $mastersProgram = Program::find($mastersProgram->id);
        
        $matricule = Student::generateEnrollmentMatricule(
            $student->id,
            $mastersProgram->id,
            $student->batch_id
        );
        
        echo "  - Program: {$mastersProgram->title} (Level: {$mastersProgram->academic_level})\n";
        echo "  - Generated Matricule: {$matricule}\n";
        echo "  - Format: PAX + Year + M + Faculty + Number\n\n";
        
        // Reset to original level
        DB::table('programs')
          ->where('id', $mastersProgram->id)
          ->update(['academic_level' => 'A']);
    }
    
    // Test 3: Show what the accessor returns
    echo "Test 3: StudentEnroll Matricule Accessor\n";
    $enrollment = \App\Models\StudentEnroll::with('student', 'program')->first();
    if ($enrollment) {
        echo "  - Enrollment ID: {$enrollment->id}\n";
        echo "  - Student: {$enrollment->student->first_name} {$enrollment->student->last_name}\n";
        echo "  - Database matricule field: " . ($enrollment->getRawOriginal('matricule') ?? 'NULL') . "\n";
        echo "  - Accessor returns: {$enrollment->matricule}\n";
        echo "  - Fallback logic: " . ($enrollment->getRawOriginal('matricule') ? 'Uses enrollment matricule' : 'Falls back to student_id') . "\n\n";
    }
    
    // Test 4: Count matricules by pattern
    echo "Test 4: Existing Matricule Patterns\n";
    $matricules = DB::table('student_enrolls')
                    ->whereNotNull('matricule')
                    ->select('matricule')
                    ->distinct()
                    ->get();
    
    echo "  - Total unique matricules: " . $matricules->count() . "\n";
    echo "  - Sample matricules:\n";
    foreach ($matricules->take(10) as $m) {
        echo "    * {$m->matricule}\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
