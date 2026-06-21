<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\EnrollSubject;
use App\Models\SubjectMarking;
use App\Models\StudentEnroll;
use App\Models\Student;

$programId = 4; // HND NURSING

// 1. Show the enrollment structure: which subjects belong to which semester for this program
echo "=== Enrollment Structure for Program 4 (HND NURSING) ===\n";
$enrollSubjects = EnrollSubject::where('program_id', $programId)
    ->with(['semester', 'subjects'])
    ->orderBy('semester_id')
    ->get();

foreach ($enrollSubjects as $es) {
    $semTitle = $es->semester->title ?? 'N/A';
    $semId = $es->semester_id;
    echo "\n--- EnrollSubject #{$es->id}: {$semTitle} (semester_id={$semId}, section_id={$es->section_id}) ---\n";
    foreach ($es->subjects as $sub) {
        echo "  [{$sub->id}] {$sub->code} - {$sub->title} (credit={$sub->credit_hour})\n";
    }
}

// 2. For student PAX25MBH006 (ID=52), check which target semester courses are already validated
echo "\n\n=== PAX25MBH006 Course Validation Check ===\n";
$student = Student::find(52);

// Get all subjects the student has passed across ALL enrollments
$passedSubjects = [];
$allMarks = SubjectMarking::whereHas('studentEnroll', function($q) use ($student, $programId) {
    $q->where('student_id', $student->id)->where('program_id', $programId);
})->with(['subject'])->get();

foreach ($allMarks as $mark) {
    if ($mark->workflow_state === SubjectMarking::STATE_PUBLISHED && round($mark->total_marks) >= 50) {
        $passedSubjects[$mark->subject_id] = [
            'code' => $mark->subject->code ?? '',
            'title' => $mark->subject->title ?? '',
            'marks' => round($mark->total_marks, 2),
        ];
    }
}
echo "Passed subjects (" . count($passedSubjects) . "):\n";
foreach ($passedSubjects as $id => $info) {
    echo "  [{$id}] {$info['code']} - {$info['title']} ({$info['marks']}%)\n";
}

// 3. For each semester, check if there are unvalidated courses
echo "\n=== Unvalidated Courses Per Semester ===\n";
foreach ($enrollSubjects as $es) {
    $semTitle = $es->semester->title ?? 'N/A';
    $semId = $es->semester_id;
    $totalCourses = $es->subjects->count();
    $unvalidated = [];
    
    foreach ($es->subjects as $sub) {
        if (!isset($passedSubjects[$sub->id])) {
            $unvalidated[] = "[{$sub->id}] {$sub->code} - {$sub->title}";
        }
    }
    
    $validated = $totalCourses - count($unvalidated);
    echo "\n{$semTitle} (ID={$semId}): {$validated}/{$totalCourses} validated";
    if (empty($unvalidated)) {
        echo " ✓ ALL VALIDATED - NO NEED TO PROGRESS HERE\n";
    } else {
        echo "\n  Unvalidated:\n";
        foreach ($unvalidated as $u) {
            echo "    - {$u}\n";
        }
    }
}
