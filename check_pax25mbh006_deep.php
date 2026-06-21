<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Models\Semester;

$enroll = StudentEnroll::where('matricule', 'PAX25MBH006')->first();
$student = $enroll->student;
$programId = 4; // HND NURSING

echo "=== Student PAX25MBH006: Mumfua Patience ===\n\n";

// 1. Check if student already has enrollment in the target semester (First Semester Y2)
$targetSem = Semester::where('year', 2)->where('semester_type', 1)->where('is_resit', 0)->where('status', 1)->first();
echo "Target semester (First Semester Y2): ";
if ($targetSem) {
    echo "{$targetSem->title} (ID={$targetSem->id})\n";
    $existingEnroll = StudentEnroll::where('student_id', $student->id)
        ->where('semester_id', $targetSem->id)
        ->where('program_id', $programId)
        ->first();
    echo "Already enrolled in target semester: " . ($existingEnroll ? "YES (Enroll #{$existingEnroll->id})" : "NO") . "\n";
} else {
    echo "NOT FOUND\n";
}

// 2. Check program subjects vs enrolled subjects for SECOND SEMESTER Y1
$secondSem = Semester::where('id', 2)->first();
echo "\n=== Second Semester Y1 (current enrollment #77) ===\n";

// Get all subjects assigned to this program for this semester
$programSubjects = Subject::whereHas('programs', function($q) use ($programId) {
    $q->where('program_id', $programId);
})->where('status', 1)->get();

echo "Total program subjects: {$programSubjects->count()}\n";

// Get what's assigned to Second Semester specifically (via subject.semester or pivot)
// Check the program_subject pivot table for semester info
$pivot = DB::table('program_subject')
    ->where('program_id', $programId)
    ->get();
echo "\nProgram-Subject assignments:\n";
foreach ($pivot as $p) {
    $sub = Subject::find($p->subject_id);
    if ($sub) {
        $semId = $p->semester_id ?? 'null';
        echo "  [{$sub->id}] {$sub->code} - {$sub->title} (semester_id={$semId}";
        if (isset($p->year)) echo ", year={$p->year}";
        echo ")\n";
    }
}

// 3. How many subjects does each semester enrollment have?
echo "\n=== Enrollment Comparison ===\n";
$enrolls = $student->enrolls()->with(['semester', 'subjects'])->orderBy('id')->get();
foreach ($enrolls as $e) {
    $subjectCodes = $e->subjects->pluck('code')->join(', ');
    echo "Enroll #{$e->id} ({$e->semester->title}): {$e->subjects->count()} subjects [{$subjectCodes}]\n";
}

// 4. Check other students in the same semester/program for comparison
echo "\n=== Other students in Second Sem Y1, Program 4, Session 2 ===\n";
$otherEnrolls = StudentEnroll::where('semester_id', 2)
    ->where('program_id', $programId)
    ->where('session_id', 2)
    ->with(['student', 'subjects'])
    ->take(5)
    ->get();
foreach ($otherEnrolls as $oe) {
    $name = ($oe->student->first_name ?? '') . ' ' . ($oe->student->last_name ?? '');
    $subjectCodes = $oe->subjects->pluck('code')->join(', ');
    echo "  Enroll #{$oe->id} ({$oe->matricule}): {$oe->subjects->count()} subjects [{$subjectCodes}]\n";
}

// 5. Check what semesters exist
echo "\n=== All active semesters ===\n";
$semesters = Semester::where('status', 1)->orderBy('year')->orderBy('semester_type')->get();
foreach ($semesters as $s) {
    echo "  [{$s->id}] {$s->title} (year={$s->year}, type={$s->semester_type}, is_resit={$s->is_resit})\n";
}
