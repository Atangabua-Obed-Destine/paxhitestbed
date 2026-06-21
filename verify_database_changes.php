<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Database Structure ===\n\n";

// Check student_enrolls table
echo "1. Student Enrolls with Matricule:\n";
$enrolls = DB::table('student_enrolls')
             ->join('students', 'student_enrolls.student_id', '=', 'students.id')
             ->select('student_enrolls.*', 'students.student_id as original_student_id')
             ->limit(5)
             ->get();

foreach ($enrolls as $enroll) {
    echo "  - Enroll ID: {$enroll->id}, Student: {$enroll->original_student_id}, Matricule: " . ($enroll->matricule ?? 'NULL') . "\n";
}

// Check programs table
echo "\n2. Programs with Academic Levels:\n";
$programs = DB::table('programs')
              ->join('degree_types', 'programs.degree_type_id', '=', 'degree_types.id')
              ->select('programs.id', 'programs.title', 'programs.academic_level', 'degree_types.title as degree_title', 'degree_types.is_postgraduate')
              ->limit(10)
              ->get();

foreach ($programs as $program) {
    $levelName = match($program->academic_level) {
        'A' => 'Undergraduate',
        'M' => 'Masters',
        'D' => 'Doctoral',
        default => 'Unknown'
    };
    echo "  - {$program->title} | Level: {$program->academic_level} ({$levelName}) | Degree: {$program->degree_title} | Postgrad: " . ($program->is_postgraduate ? 'Yes' : 'No') . "\n";
}

// Count enrollments by matricule status
echo "\n3. Enrollment Statistics:\n";
$withMatricule = DB::table('student_enrolls')->whereNotNull('matricule')->count();
$withoutMatricule = DB::table('student_enrolls')->whereNull('matricule')->count();
$total = DB::table('student_enrolls')->count();

echo "  - Total Enrollments: {$total}\n";
echo "  - With Matricule: {$withMatricule}\n";
echo "  - Without Matricule: {$withoutMatricule}\n";

// Count programs by level
echo "\n4. Programs by Academic Level:\n";
$undergrad = DB::table('programs')->where('academic_level', 'A')->count();
$masters = DB::table('programs')->where('academic_level', 'M')->count();
$doctoral = DB::table('programs')->where('academic_level', 'D')->count();

echo "  - Undergraduate (A): {$undergrad}\n";
echo "  - Masters (M): {$masters}\n";
echo "  - Doctoral (D): {$doctoral}\n";

echo "\n=== Verification Complete ===\n";
