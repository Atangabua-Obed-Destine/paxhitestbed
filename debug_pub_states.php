<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ExamPublishingState;
use App\Models\SubjectMarkingExamState;
use App\Models\SubjectMarking;
use App\Models\StudentEnroll;
use App\Models\Subject;

echo "=== ADMIN-LEVEL: ExamPublishingState for program=4, session=2, semester=3 ===\n";
$adminStates = ExamPublishingState::where('program_id', 4)
    ->where('session_id', 2)
    ->where('semester_id', 3)
    ->with(['examType:id,title,is_final'])
    ->get();

if ($adminStates->isEmpty()) {
    echo "  No ExamPublishingState records found!\n";
} else {
    foreach ($adminStates as $as) {
        $subj = Subject::find($as->subject_id);
        echo "  Subject: " . ($subj ? $subj->code : $as->subject_id);
        echo " | exam_type: " . ($as->examType ? $as->examType->title : $as->exam_type_id);
        echo " (is_final=" . ($as->examType ? ($as->examType->is_final ? 'YES' : 'no') : '?') . ")";
        echo " | STATE: {$as->workflow_state}";
        echo " | section: {$as->section_id}";
        echo "\n";
    }
}

echo "\n=== STUDENT-LEVEL: SubjectMarkingExamState for enroll 75 (semester=3) ===\n";
$markings = SubjectMarking::where('student_enroll_id', 75)->with(['subject:id,code,title', 'examStates'])->get();
foreach ($markings as $m) {
    echo "\n  Subject: {$m->subject->code} | SubjectMarking.workflow_state: {$m->workflow_state}\n";
    foreach ($m->examStates as $es) {
        echo "    exam_type_id: {$es->exam_type_id} | STATE: {$es->workflow_state} | publish_date: {$es->publish_date} | publish_time: {$es->publish_time}\n";
    }
}

// Also check enrollment details
echo "\n=== Enrollment details ===\n";
$enroll = StudentEnroll::with(['program', 'semester', 'section'])->find(75);
echo "  Program: {$enroll->program_id} | Semester: {$enroll->semester_id} | Section: " . ($enroll->section_id ?? 'NULL') . "\n";
