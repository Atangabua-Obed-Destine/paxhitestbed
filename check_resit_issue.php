<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use App\Models\Subject;
use App\Models\ResitRequest;
use App\Models\Grade;

echo "=== Investigating Resit Issue for PAX25TSC002H ===\n\n";

// Find student enrollment
$enrollment = StudentEnroll::where('matricule', 'PAX25TSC002H')
    ->with('student', 'semester', 'session', 'subjects')
    ->first();

if (!$enrollment) {
    echo "❌ Student enrollment not found\n";
    exit(1);
}

echo "Student: {$enrollment->student->name}\n";
echo "Matricule: {$enrollment->matricule}\n";
echo "Enrollment ID: {$enrollment->id}\n";
echo "Semester: {$enrollment->semester->title}\n";
echo "Session: {$enrollment->session->title}\n";
echo "Program: {$enrollment->program->title}\n\n";

// Find "Financial Accounting for Banks & MFIs II"
echo "Looking for 'Financial Accounting for Banks & MFIs II'...\n";

$subject = Subject::where('title', 'like', '%Financial Accounting for Banks%')->first();

if (!$subject) {
    echo "❌ Subject not found\n";
    exit(1);
}

echo "✅ Subject found: {$subject->title} (ID: {$subject->id})\n\n";

// Check if enrolled in this subject
$isEnrolled = $enrollment->subjects()->where('subjects.id', $subject->id)->exists();
echo "Enrolled in this subject? " . ($isEnrolled ? "✅ YES" : "❌ NO") . "\n";

if (!$isEnrolled) {
    echo "\n⚠️ ISSUE: Student not enrolled in this subject for this enrollment\n";
    echo "This might be why it doesn't appear in resit page.\n";
}

// Check subject marking
$marking = SubjectMarking::where('student_enroll_id', $enrollment->id)
    ->where('subject_id', $subject->id)
    ->first();

if ($marking) {
    echo "\n📊 Subject Marking Found:\n";
    echo "   - Workflow State: {$marking->workflow_state}\n";
    echo "   - Total Marks: {$marking->total_marks}\n";
    echo "   - Exam Marks: {$marking->exam_marks}\n";
    echo "   - Attendance: {$marking->attendances}\n";
    echo "   - Assignments: {$marking->assignments}\n";
    echo "   - Activities: {$marking->activities}\n";
    echo "   - Status: {$marking->status}\n";
    echo "   - Publish Date: {$marking->publish_date}\n";
    echo "   - Publish Time: {$marking->publish_time}\n";
    echo "   - is_published_override: " . ($marking->is_published_override === null ? 'NULL' : ($marking->is_published_override ? 'TRUE' : 'FALSE')) . "\n";
    
    // Check visibility
    $isVisible = $marking->is_visible_to_student;
    echo "   - Visible to student? " . ($isVisible ? "✅ YES" : "❌ NO") . "\n";
    
    // Check publish date/time logic
    $publishDate = $marking->publish_date instanceof \Carbon\Carbon ? 
                  $marking->publish_date->format('Y-m-d') : 
                  date('Y-m-d', strtotime($marking->publish_date));
    $publishTime = $marking->publish_time instanceof \Carbon\Carbon ? 
                  $marking->publish_time->format('H:i:s') : 
                  date('H:i:s', strtotime($marking->publish_time));
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    $isPublishTimeReached = ($publishDate == $currentDate && $publishTime <= $currentTime) || 
                            $publishDate < $currentDate;
    
    echo "   - Publish time reached? " . ($isPublishTimeReached ? "✅ YES" : "❌ NO") . "\n";
    echo "     (Current: {$currentDate} {$currentTime}, Publish: {$publishDate} {$publishTime})\n";
    
    // Check if failed
    $totalMarks = round($marking->total_marks);
    $failed = $totalMarks < 50;
    echo "   - Failed (< 50)? " . ($failed ? "✅ YES ({$totalMarks}%)" : "❌ NO ({$totalMarks}%)") . "\n";
    
} else {
    echo "\n❌ No SubjectMarking found for this subject\n";
}

// Check if currently registered in another enrollment (carry-over check)
echo "\n🔍 Checking if currently registered in another enrollment...\n";
$otherEnrollments = StudentEnroll::where('student_id', $enrollment->student_id)
    ->where('status', 1)
    ->where('id', '!=', $enrollment->id)
    ->whereHas('subjects', function($query) use ($subject) {
        $query->where('subjects.id', $subject->id);
    })
    ->with('semester', 'session')
    ->get();

if ($otherEnrollments->count() > 0) {
    echo "✅ YES - Currently registered in:\n";
    foreach ($otherEnrollments as $other) {
        echo "   - Enrollment {$other->id}: {$other->semester->title} ({$other->session->title})\n";
    }
    echo "\n⚠️ REASON FOUND: Student is currently retaking this course (carry-over)\n";
    echo "This is why it doesn't appear in resit requests - they're already registered!\n";
} else {
    echo "❌ NO - Not currently registered elsewhere\n";
}

// Check existing resit requests
echo "\n📋 Checking existing resit requests...\n";
$resitRequests = ResitRequest::where('student_enroll_id', $enrollment->id)
    ->where('subject_id', $subject->id)
    ->with('session', 'resitSession')
    ->get();

if ($resitRequests->count() > 0) {
    echo "✅ Found {$resitRequests->count()} resit request(s):\n";
    foreach ($resitRequests as $req) {
        echo "   - Request ID {$req->id}: State = {$req->workflow_state}, Payment = {$req->payment_status}\n";
    }
} else {
    echo "❌ No resit requests found\n";
}

// Check all failed courses for this enrollment
echo "\n📚 All failed courses for this enrollment:\n";
$grades = Grade::where('status', 1)->orderBy('min_mark', 'desc')->get();

foreach ($enrollment->subjects as $subj) {
    $mark = SubjectMarking::where('student_enroll_id', $enrollment->id)
        ->where('subject_id', $subj->id)
        ->first();
    
    if ($mark && round($mark->total_marks) < 50) {
        $isCurrentlyReg = StudentEnroll::where('student_id', $enrollment->student_id)
            ->where('status', 1)
            ->where('id', '!=', $enrollment->id)
            ->whereHas('subjects', function($query) use ($subj) {
                $query->where('subjects.id', $subj->id);
            })
            ->exists();
        
        echo "   - {$subj->title}: {$mark->total_marks}% (Currently registered elsewhere: " . ($isCurrentlyReg ? "YES" : "NO") . ")\n";
    }
}

echo "\n=== End of Investigation ===\n";
