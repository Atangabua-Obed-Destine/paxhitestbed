<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StudentEnroll;
use App\Models\ClassSession;

$matricule = 'PAX25CEH003';

echo "=== STUDENT: {$matricule} ===\n\n";

$enrolls = StudentEnroll::where('matricule', $matricule)->get();

foreach ($enrolls as $enroll) {
    echo "Enrollment ID: {$enroll->id}\n";
    echo "Status: {$enroll->status}\n";
    echo "Program ID: {$enroll->program_id}\n";
    echo "Session ID (academic): {$enroll->session_id}\n";
    echo "Semester ID: {$enroll->semester_id}\n";
    echo "Section ID: " . ($enroll->section_id ?: 'NULL') . "\n";
    
    $subjects = $enroll->subjects()->pluck('subject_id')->toArray();
    echo "Registered Subjects: " . implode(', ', $subjects) . "\n";
    echo "Has Subject 12: " . (in_array(12, $subjects) ? 'YES' : 'NO') . "\n";
    echo "\n";
}

echo "=== CLASS SESSION 3 REQUIREMENTS ===\n";
$session = ClassSession::find(3);
echo "Program ID: {$session->program_id}\n";
echo "Session ID: {$session->session_id}\n";
echo "Semester ID: {$session->semester_id}\n";
echo "Section ID: " . ($session->section_id ?: 'NULL') . "\n";
echo "Subject ID: {$session->subject_id}\n\n";

echo "=== COMPARISON ===\n";
foreach ($enrolls as $enroll) {
    echo "Enrollment {$enroll->id}:\n";
    echo "  Program match: " . ($enroll->program_id == $session->program_id ? 'YES' : "NO ({$enroll->program_id} vs {$session->program_id})") . "\n";
    echo "  Session match: " . ($enroll->session_id == $session->session_id ? 'YES' : "NO ({$enroll->session_id} vs {$session->session_id})") . "\n";
    echo "  Semester match: " . ($enroll->semester_id == $session->semester_id ? 'YES' : "NO ({$enroll->semester_id} vs {$session->semester_id})") . "\n";
    echo "  Section match: " . ($enroll->section_id == $session->section_id || !$session->section_id ? 'YES' : "NO ({$enroll->section_id} vs {$session->section_id})") . "\n";
    
    $subjects = $enroll->subjects()->pluck('subject_id')->toArray();
    echo "  Subject match: " . (in_array($session->subject_id, $subjects) ? 'YES' : 'NO') . "\n";
    echo "  Status active: " . ($enroll->status == '1' ? 'YES' : "NO ({$enroll->status})") . "\n";
}
