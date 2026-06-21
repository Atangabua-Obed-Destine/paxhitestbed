<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Subject;
use App\Models\EnrollSubject;
use App\Models\ClassRoutine;
use App\User;

$program = 9;
$semester = 1;
$section = 1;
$session = 2;

// Get subjects available via enroll_subject (what the dropdown shows)
$enrollSubjectIds = DB::table('enroll_subject_subject')
    ->join('enroll_subjects', 'enroll_subjects.id', '=', 'enroll_subject_subject.enroll_subject_id')
    ->where('enroll_subjects.program_id', $program)
    ->where('enroll_subjects.semester_id', $semester)
    ->where('enroll_subjects.section_id', $section)
    ->pluck('enroll_subject_subject.subject_id')
    ->unique()
    ->toArray();

echo "=== Subjects available in dropdown (via enroll_subject) ===\n";
echo "Count: " . count($enrollSubjectIds) . "\n";
$availableSubjects = Subject::whereIn('id', $enrollSubjectIds)->where('status', 1)->orderBy('code')->get();
foreach ($availableSubjects as $s) {
    echo "  [{$s->id}] {$s->code} - {$s->title}\n";
}

// Get all teachers available in dropdown
$teachers = User::where('status', '1')
    ->whereHas('roles', function($q) { $q->where('slug', 'teacher'); })
    ->orderBy('staff_id')
    ->get();

echo "\n=== Teachers available in dropdown ===\n";
echo "Count: " . $teachers->count() . "\n";
foreach ($teachers as $t) {
    echo "  [{$t->id}] {$t->staff_id} - {$t->first_name} {$t->last_name}\n";
}

// Now check routines that would appear empty
echo "\n=== Checking routines for MISSING matches ===\n";
$routines = ClassRoutine::where('program_id', $program)
    ->where('session_id', $session)
    ->where('semester_id', $semester)
    ->where('section_id', $section)
    ->get();

$teacherIds = $teachers->pluck('id')->toArray();
$missingSubjects = [];
$missingTeachers = [];

foreach ($routines as $r) {
    $subjectInDropdown = in_array($r->subject_id, $enrollSubjectIds);
    $teacherInDropdown = in_array($r->teacher_id, $teacherIds);
    
    $subject = Subject::find($r->subject_id);
    $teacher = User::find($r->teacher_id);
    
    $status = '';
    if (!$subjectInDropdown) {
        $status .= ' ** SUBJECT NOT IN DROPDOWN **';
        $missingSubjects[$r->subject_id] = $subject ? "{$subject->code} - {$subject->title}" : "ID:{$r->subject_id}";
    }
    if (!$teacherInDropdown) {
        $status .= ' ** TEACHER NOT IN DROPDOWN **';
        $missingTeachers[$r->teacher_id] = $teacher ? "{$teacher->first_name} {$teacher->last_name}" : "ID:{$r->teacher_id}";
    }
    
    if (!$subjectInDropdown || !$teacherInDropdown) {
        echo "  Routine #{$r->id} Day:{$r->day} {$r->start_time}-{$r->end_time}";
        echo " | Subject: " . ($subject ? "{$subject->code}" : "???");
        echo " | Teacher: " . ($teacher ? "{$teacher->first_name} {$teacher->last_name}" : "???");
        echo $status . "\n";
    }
}

echo "\n=== SUMMARY ===\n";
if (count($missingSubjects) > 0) {
    echo "Subjects in routines but NOT in dropdown:\n";
    foreach ($missingSubjects as $id => $name) {
        echo "  Subject #{$id}: {$name}\n";
        // Check why - is it in enroll_subject?
        $inEnroll = DB::table('enroll_subject_subject')
            ->join('enroll_subjects', 'enroll_subjects.id', '=', 'enroll_subject_subject.enroll_subject_id')
            ->where('enroll_subjects.program_id', $program)
            ->where('enroll_subjects.semester_id', $semester)
            ->where('enroll_subject_subject.subject_id', $id)
            ->first();
        if ($inEnroll) {
            echo "    -> IS in enroll_subject but section mismatch (section_id: {$inEnroll->section_id})\n";
        } else {
            echo "    -> NOT in enroll_subject for program {$program}, semester {$semester} at all\n";
        }
    }
} else {
    echo "All routine subjects are in the dropdown.\n";
}

if (count($missingTeachers) > 0) {
    echo "\nTeachers in routines but NOT in dropdown:\n";
    foreach ($missingTeachers as $id => $name) {
        echo "  User #{$id}: {$name}\n";
        $user = User::find($id);
        if ($user) {
            $roles = $user->roles->pluck('slug')->toArray();
            echo "    -> Roles: " . implode(', ', $roles) . " | Status: {$user->status}\n";
        }
    }
} else {
    echo "All routine teachers are in the dropdown.\n";
}
