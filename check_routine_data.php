<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check the class routine create page for faculty=4, program=9, session=2, semester_year=1, semester=1, section=1
// The view needs subjects (via enroll_subjects) and teachers (users with teacher role)

// 1. Check enroll_subject_subject pivot to see which subjects are available
echo "=== Checking Enroll Subjects for Program 9, Semester 1 ===\n";
$enrollSubjects = DB::table('enroll_subjects')
    ->where('program_id', 9)
    ->where('semester_id', 1)
    ->get();

echo "Enroll subject groups: " . $enrollSubjects->count() . "\n";
foreach ($enrollSubjects as $es) {
    $subjects = DB::table('enroll_subject_subject')
        ->join('subjects', 'subjects.id', '=', 'enroll_subject_subject.subject_id')
        ->where('enroll_subject_subject.enroll_subject_id', $es->id)
        ->select('subjects.id', 'subjects.code', 'subjects.title')
        ->get();
    echo "  EnrollSubject #{$es->id}: " . $subjects->count() . " subjects\n";
    foreach ($subjects as $s) {
        echo "    - [{$s->id}] {$s->code} - {$s->title}\n";
    }
}

// 2. Check what the controller actually queries
echo "\n=== Looking at ClassRoutineController logic ===\n";

// Check existing routines for this selection to see what's displayed
$routines = DB::table('class_routines')
    ->where('program_id', 9)
    ->where('session_id', 2) 
    ->where('semester_id', 1)
    ->where('section_id', 1)
    ->get();

echo "Existing routines: " . $routines->count() . "\n\n";
foreach ($routines as $r) {
    $subject = DB::table('subjects')->find($r->subject_id);
    $teacher = DB::table('users')->find($r->teacher_id);
    
    echo "Day {$r->day}: ";
    echo ($subject ? "{$subject->code} - {$subject->title}" : "MISSING SUBJECT (ID:{$r->subject_id})");
    echo " | ";
    echo ($teacher ? "{$teacher->first_name} {$teacher->last_name}" : "MISSING TEACHER (ID:{$r->teacher_id})");
    echo " | {$r->start_time}-{$r->end_time}\n";
}

// 3. Check if there are soft-deleted or status=0 subjects that might cause blank display
echo "\n=== Checking subject status ===\n";
$routineSubjectIds = DB::table('class_routines')
    ->where('program_id', 9)
    ->where('session_id', 2)
    ->where('semester_id', 1)
    ->where('section_id', 1)
    ->pluck('subject_id')
    ->unique();

foreach ($routineSubjectIds as $sid) {
    $subject = DB::table('subjects')->find($sid);
    if ($subject) {
        echo "  Subject #{$sid}: {$subject->code} - {$subject->title} (status: {$subject->status})\n";
    } else {
        echo "  Subject #{$sid}: *** DOES NOT EXIST ***\n";
    }
}

// 4. Check user/teacher status
echo "\n=== Checking teacher status ===\n";
$routineTeacherIds = DB::table('class_routines')
    ->where('program_id', 9)
    ->where('session_id', 2)
    ->where('semester_id', 1)
    ->where('section_id', 1)
    ->pluck('teacher_id')
    ->unique();

foreach ($routineTeacherIds as $tid) {
    $teacher = DB::table('users')->find($tid);
    if ($teacher) {
        echo "  User #{$tid}: {$teacher->first_name} {$teacher->last_name} (status: {$teacher->status})\n";
    } else {
        echo "  User #{$tid}: *** DOES NOT EXIST ***\n";
    }
}

// 5. Check the user_program pivot - teachers must be assigned to program
echo "\n=== Teachers assigned to Program 9 ===\n";
$teacherPrograms = DB::table('user_program')
    ->where('program_id', 9)
    ->join('users', 'users.id', '=', 'user_program.user_id')
    ->select('users.id', 'users.first_name', 'users.last_name', 'users.status')
    ->get();

echo "Teachers in program 9: " . $teacherPrograms->count() . "\n";
foreach ($teacherPrograms as $t) {
    echo "  [{$t->id}] {$t->first_name} {$t->last_name} (status: {$t->status})\n";
}
