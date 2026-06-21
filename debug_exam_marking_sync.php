<?php

/**
 * Debug Script: Exam Marking to Subject Marking Sync
 * 
 * This script helps identify why exam marks aren't showing in subject-marking
 * Run: php debug_exam_marking_sync.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;

echo "\n=== Exam Marking Sync Diagnostic ===\n\n";

// Get recent exam entries
echo "Checking recent exam entries...\n\n";

$recentExams = Exam::with(['type', 'studentEnroll.student', 'subject'])
    ->whereNotNull('achieve_marks')
    ->orderBy('updated_at', 'desc')
    ->limit(10)
    ->get();

if ($recentExams->isEmpty()) {
    echo "❌ No exam marks found in the database!\n";
    echo "   Please submit some marks in /admin/exam/exam-marking first.\n\n";
    exit;
}

echo "✓ Found {$recentExams->count()} recent exam entries with marks:\n\n";

foreach ($recentExams as $exam) {
    $student = $exam->studentEnroll->student ?? null;
    $subject = $exam->subject ?? null;
    $examType = $exam->type ?? null;
    
    echo "Student: " . ($student->student_id ?? 'N/A') . " - " . ($student->first_name ?? '') . " " . ($student->last_name ?? '') . "\n";
    echo "Subject: " . ($subject->code ?? 'N/A') . " - " . ($subject->title ?? 'N/A') . "\n";
    echo "Exam Type: " . ($examType->title ?? 'N/A') . "\n";
    echo "Marks: {$exam->achieve_marks} / {$exam->marks}\n";
    
    // Check critical conditions
    $issues = [];
    
    // Check 1: Attendance
    if ($exam->attendance != 1) {
        $issues[] = "❌ Attendance = {$exam->attendance} (must be 1 for marks to count)";
    } else {
        echo "✓ Attendance marked\n";
    }
    
    // Check 2: Contribution
    if ($exam->contribution <= 0) {
        $issues[] = "❌ Contribution = {$exam->contribution}% (must be > 0)";
    } else {
        echo "✓ Contribution: {$exam->contribution}%\n";
    }
    
    // Check 3: Exam Type contribution
    if ($examType && $examType->contribution <= 0) {
        $issues[] = "⚠ Exam Type '{$examType->title}' has contribution = {$examType->contribution}% (should be > 0)";
    } elseif ($examType) {
        echo "✓ Exam Type contribution: {$examType->contribution}%\n";
    }
    
    if (!empty($issues)) {
        echo "\n🔴 ISSUES FOUND:\n";
        foreach ($issues as $issue) {
            echo "   {$issue}\n";
        }
    }
    
    // Calculate what would appear in subject-marking
    if ($exam->attendance == 1 && $exam->contribution > 0) {
        $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
        $contributedMarks = ($percentOfMarks / 100) * $exam->contribution;
        echo "✓ Would contribute: " . round($contributedMarks, 2) . " marks\n";
    }
    
    echo "\n" . str_repeat('-', 80) . "\n\n";
}

// Check exam types configuration
echo "\n=== Exam Types Configuration ===\n\n";
$examTypes = ExamType::where('status', 1)->orderBy('contribution', 'desc')->get();

if ($examTypes->isEmpty()) {
    echo "❌ No active exam types found!\n\n";
} else {
    echo "Active exam types:\n";
    foreach ($examTypes as $type) {
        echo "  - {$type->title}: {$type->contribution}%";
        if ($type->contribution <= 0) {
            echo " ⚠ WARNING: Contribution is 0!";
        }
        echo "\n";
    }
    echo "\n";
}

// Check if SubjectMarking records exist
echo "\n=== Subject Marking Records ===\n\n";
$subjectMarkings = SubjectMarking::with(['studentEnroll.student', 'subject'])
    ->orderBy('updated_at', 'desc')
    ->limit(5)
    ->get();

if ($subjectMarkings->isEmpty()) {
    echo "ℹ No SubjectMarking records found yet.\n";
    echo "  This is normal - you need to go to subject-marking page and submit the form.\n\n";
} else {
    echo "✓ Found {$subjectMarkings->count()} recent subject marking records:\n\n";
    foreach ($subjectMarkings as $marking) {
        $student = $marking->studentEnroll->student ?? null;
        $subject = $marking->subject ?? null;
        echo "Student: " . ($student->student_id ?? 'N/A') . "\n";
        echo "Subject: " . ($subject->code ?? 'N/A') . "\n";
        echo "Exam Marks: {$marking->exam_marks}\n";
        echo "Total Marks: {$marking->total_marks}\n";
        echo "Workflow: {$marking->workflow_state}\n\n";
    }
}

// Summary and recommendations
echo "\n=== SUMMARY & RECOMMENDATIONS ===\n\n";

$totalIssues = 0;
$recommendations = [];

// Check for exams with no attendance
$noAttendance = Exam::whereNotNull('achieve_marks')
    ->where('attendance', '!=', 1)
    ->count();

if ($noAttendance > 0) {
    $totalIssues++;
    $recommendations[] = "❌ {$noAttendance} exam record(s) have marks but attendance != 1";
    $recommendations[] = "   FIX: In exam-marking page, ensure attendance is marked for each student";
}

// Check for exams with no contribution
$noContribution = Exam::whereNotNull('achieve_marks')
    ->where('contribution', '<=', 0)
    ->count();

if ($noContribution > 0) {
    $totalIssues++;
    $recommendations[] = "❌ {$noContribution} exam record(s) have marks but contribution = 0";
    $recommendations[] = "   FIX: Set contribution percentages in exam types or individual exams";
}

// Check exam types
$zeroContribTypes = ExamType::where('status', 1)
    ->where('contribution', '<=', 0)
    ->count();

if ($zeroContribTypes > 0) {
    $totalIssues++;
    $recommendations[] = "⚠ {$zeroContribTypes} active exam type(s) have contribution = 0";
    $recommendations[] = "   FIX: Go to /admin/exam/exam-type and set contribution percentages";
}

if ($totalIssues > 0) {
    echo "🔴 FOUND {$totalIssues} ISSUE(S):\n\n";
    foreach ($recommendations as $rec) {
        echo "{$rec}\n";
    }
} else {
    echo "✅ No issues found!\n";
    echo "   Your exam marks should appear in subject-marking page.\n";
    echo "   If they still don't show:\n";
    echo "   1. Make sure you're using the same filters (program, session, semester, subject)\n";
    echo "   2. Check that the students appear in the list\n";
    echo "   3. Look at the exam_marks column - it should auto-calculate\n";
}

echo "\n\n=== NEXT STEPS ===\n\n";
echo "1. Fix any issues identified above\n";
echo "2. Go to: http://localhost/paxhitest/admin/exam/subject-marking\n";
echo "3. Select filters to match your exam-marking selections\n";
echo "4. Students should appear with auto-calculated exam_marks\n";
echo "5. Review and click 'Save' to create SubjectMarking records\n\n";
