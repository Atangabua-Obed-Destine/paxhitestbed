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

echo "Current Enrollment:\n";
echo "  Semester: {$enrollment->semester->title} (ID: {$enrollment->semester_id})\n";
echo "  Year: {$enrollment->semester->year}\n";
echo "  Semester Type: {$enrollment->semester->semester_type}\n";
echo "  Is Resit: " . ($enrollment->semester->is_resit ? 'YES' : 'NO') . "\n\n";

$requests = App\Models\ResitRequest::where('student_enroll_id', $enrollment->id)->get();

echo "Resit Requests ({$requests->count()}):\n";
foreach($requests as $req) {
    echo "  Subject: {$req->subject->title}\n";
    echo "  Resit Semester ID: {$req->resit_semester_id}\n";
    
    $resitSem = App\Models\Semester::find($req->resit_semester_id);
    if ($resitSem) {
        echo "  Resit Semester: {$resitSem->title}\n";
        echo "  Resit Year: {$resitSem->year}\n";
        echo "  Resit Type: {$resitSem->semester_type}\n";
        echo "  Resit Is_Resit: " . ($resitSem->is_resit ? 'YES' : 'NO') . "\n";
    }
    echo "  Workflow State: {$req->workflow_state}\n\n";
}

echo "\nAll Resit Semesters in Database:\n";
$allResitSemesters = App\Models\Semester::where('is_resit', 1)->where('status', 1)->orderBy('year')->orderBy('semester_type')->get();
foreach($allResitSemesters as $rs) {
    echo "  ID {$rs->id}: {$rs->title} (Year: {$rs->year}, Type: {$rs->semester_type})\n";
}

echo "\nDone.\n";
