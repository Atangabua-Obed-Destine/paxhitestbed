<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Session;
use App\Models\StudentEnroll;
use App\Models\Semester;

echo "=== Session Check ===\n";
$sessions = Session::all();
foreach ($sessions as $s) {
    $active = ($s->current == 1 && $s->status == 1) ? ' <<< ACTIVE' : '';
    echo "  [{$s->id}] {$s->title} (current={$s->current}, status={$s->status}){$active}\n";
}

echo "\n=== Enrollment #77 Session Transition Validation ===\n";
$enrollment = StudentEnroll::with(['semester', 'session'])->find(77);
$nextSemester = Semester::find(5); // FIRST SEMESTER - Y2

echo "Current enrollment:\n";
echo "  Semester: {$enrollment->semester->title} (year={$enrollment->semester->year})\n";
echo "  Session: {$enrollment->session->title} (ID={$enrollment->session_id})\n";
echo "Target semester: {$nextSemester->title} (year={$nextSemester->year})\n";

$yearProgression = $nextSemester->year > $enrollment->semester->year;
echo "Year progression (Y{$enrollment->semester->year} -> Y{$nextSemester->year}): " . ($yearProgression ? 'YES' : 'NO') . "\n";

$activeSession = Session::where('current', 1)->where('status', 1)->first();
echo "System active session: " . ($activeSession ? "{$activeSession->title} (ID={$activeSession->id})" : 'NONE') . "\n";

if ($yearProgression && $activeSession) {
    $sameSession = ($activeSession->id == $enrollment->session_id);
    echo "Active session same as enrollment session: " . ($sameSession ? 'YES - SHOULD BLOCK!' : 'NO') . "\n";
    if ($sameSession) {
        echo ">>> STUDENT SHOULD NOT BE ELIGIBLE - waiting for new academic session <<<\n";
    } else {
        echo ">>> Session transition is valid - different sessions <<<\n";
    }
}

// Also check if student already enrolled in target
echo "\n=== Duplicate Enrollment Check ===\n";
$student = $enrollment->student;
$dupes = StudentEnroll::where('student_id', $student->id)
    ->where('program_id', $enrollment->program_id)
    ->where('semester_id', $nextSemester->id)
    ->get();
echo "Existing enrollments in target semester ({$nextSemester->title}): {$dupes->count()}\n";
foreach ($dupes as $d) {
    echo "  Enroll #{$d->id} (session_id={$d->session_id}, status={$d->status})\n";
}

// Also check: for older enrollment #52, is student already in the target (Second Sem Y1)?
echo "\n=== Enroll #52 Duplicate Check ===\n";
$enroll52 = StudentEnroll::with(['semester'])->find(52);
$sem2 = Semester::find(2); // SECOND SEMESTER - Y1
echo "Enroll #52 target: {$sem2->title}\n";
$dupes52 = StudentEnroll::where('student_id', $student->id)
    ->where('program_id', $enroll52->program_id)
    ->where('semester_id', $sem2->id)
    ->get();
echo "Already enrolled in Second Semester Y1: ";
foreach ($dupes52 as $d) {
    echo "YES - Enroll #{$d->id} ";
}
if ($dupes52->isEmpty()) echo "NO";
echo "\n";
