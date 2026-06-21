<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ClassRoutine;
use App\Models\Subject;
use App\User;

$subjectIds = Subject::pluck('id')->toArray();
$userIds = User::pluck('id')->toArray();

$allRoutines = ClassRoutine::all();
$total = $allRoutines->count();

$orphanedSubject = [];
$orphanedTeacher = [];

foreach ($allRoutines as $r) {
    $missingSubject = !in_array($r->subject_id, $subjectIds);
    $missingTeacher = !in_array($r->teacher_id, $userIds);
    
    if ($missingSubject || $missingTeacher) {
        echo "Routine ID: {$r->id}";
        echo " | Program: {$r->program_id}";
        echo " | Session: {$r->session_id}";
        echo " | Semester: {$r->semester_id}";
        echo " | Section: {$r->section_id}";
        echo " | Day: {$r->day}";
        echo " | Time: {$r->start_time} to {$r->end_time}";
        echo " | Subject ID: {$r->subject_id}";
        echo " | Teacher ID: {$r->teacher_id}";
        
        $issues = [];
        if ($missingSubject) $issues[] = "MISSING SUBJECT";
        if ($missingTeacher) $issues[] = "MISSING TEACHER";
        echo " | Issues: " . implode(', ', $issues);
        echo "\n";
    }
}

$orphanSubjCount = ClassRoutine::whereNotIn('subject_id', $subjectIds)->count();
$orphanTeachCount = ClassRoutine::whereNotIn('teacher_id', $userIds)->count();

echo "\n=== SUMMARY ===\n";
echo "Total class routines: {$total}\n";
echo "Routines with missing subject: {$orphanSubjCount}\n";
echo "Routines with missing teacher: {$orphanTeachCount}\n";

// Also check for NULL values
$nullSubj = ClassRoutine::whereNull('subject_id')->count();
$nullTeach = ClassRoutine::whereNull('teacher_id')->count();
echo "Routines with NULL subject_id: {$nullSubj}\n";
echo "Routines with NULL teacher_id: {$nullTeach}\n";

// Show which subject IDs are missing
$missingSubjIds = ClassRoutine::whereNotIn('subject_id', $subjectIds)->pluck('subject_id')->unique()->toArray();
$missingTeachIds = ClassRoutine::whereNotIn('teacher_id', $userIds)->pluck('teacher_id')->unique()->toArray();
echo "\nMissing subject IDs referenced: " . implode(', ', $missingSubjIds) . "\n";
echo "Missing teacher/user IDs referenced: " . implode(', ', $missingTeachIds) . "\n";

// Check specifically for the user's page: faculty=4, program=9, session=2, semester=1, section=1
echo "\n=== For Program 9, Session 2, Semester 1, Section 1 ===\n";
$filtered = ClassRoutine::where('program_id', 9)
    ->where('session_id', 2)
    ->where('semester_id', 1)
    ->where('section_id', 1)
    ->get();

echo "Total routines for this selection: " . $filtered->count() . "\n";
foreach ($filtered as $r) {
    $subjectExists = in_array($r->subject_id, $subjectIds);
    $teacherExists = in_array($r->teacher_id, $userIds);
    $subjectName = $subjectExists ? Subject::find($r->subject_id)->title : '** MISSING **';
    $teacherName = $teacherExists ? User::find($r->teacher_id)->first_name . ' ' . User::find($r->teacher_id)->last_name : '** MISSING **';
    
    echo "  ID:{$r->id} Day:{$r->day} {$r->start_time}-{$r->end_time} | Subject({$r->subject_id}): {$subjectName} | Teacher({$r->teacher_id}): {$teacherName}\n";
}
