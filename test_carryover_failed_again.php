<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use App\Models\Subject;

echo "=== Testing Carry-Over Failed Again Scenario ===\n\n";

// Find the student
$enrollment68 = StudentEnroll::find(68); // Y1 enrollment
$enrollment75 = StudentEnroll::find(75); // Y2 enrollment

if (!$enrollment68 || !$enrollment75) {
    echo "❌ Enrollments not found\n";
    exit(1);
}

echo "Student: {$enrollment68->student->name}\n";
echo "Matricule: {$enrollment68->matricule}\n\n";

// Find the subject
$subject = Subject::where('title', 'like', '%Financial Accounting for Banks%')->first();
echo "Subject: {$subject->title}\n\n";

// Check marks in Y1
$markY1 = SubjectMarking::where('student_enroll_id', 68)
    ->where('subject_id', $subject->id)
    ->first();

echo "Y1 (Enrollment 68) Performance:\n";
if ($markY1) {
    echo "   - Semester: {$enrollment68->semester->title}\n";
    echo "   - Total Marks: {$markY1->total_marks}%\n";
    echo "   - Status: " . ($markY1->total_marks >= 50 ? "PASS" : "FAIL") . "\n";
    echo "   - Published: " . ($markY1->is_visible_to_student ? "YES" : "NO") . "\n";
} else {
    echo "   - No marks found\n";
}

echo "\n";

// Check marks in Y2
$markY2 = SubjectMarking::where('student_enroll_id', 75)
    ->where('subject_id', $subject->id)
    ->first();

echo "Y2 (Enrollment 75) Performance:\n";
if ($markY2) {
    echo "   - Semester: {$enrollment75->semester->title}\n";
    echo "   - Total Marks: {$markY2->total_marks}%\n";
    echo "   - Status: " . ($markY2->total_marks >= 50 ? "PASS" : "FAIL") . "\n";
    echo "   - Published: " . ($markY2->is_visible_to_student ? "YES" : "NO") . "\n";
    echo "   - Workflow State: {$markY2->workflow_state}\n";
} else {
    echo "   - No marks found (course in progress)\n";
}

echo "\n";

// Simulate the resit controller logic
echo "Testing Resit Controller Logic:\n\n";

echo "1. Checking enrollment 68 (Y1) for resit eligibility:\n";

// Check if currently registered elsewhere (the problematic check)
$isCurrentlyRegistered = StudentEnroll::where('student_id', $enrollment68->student_id)
    ->where('status', 1)
    ->where('id', '!=', 68)
    ->whereHas('subjects', function($query) use ($subject) {
        $query->where('subjects.id', $subject->id);
    })
    ->exists();

echo "   - Currently registered in another enrollment? " . ($isCurrentlyRegistered ? "YES" : "NO") . "\n";

if ($isCurrentlyRegistered) {
    echo "   - ❌ BLOCKED: Won't appear in resit list for enrollment 68\n";
    echo "   - Reason: Student is currently taking this course elsewhere\n";
} else {
    echo "   - ✅ ALLOWED: Would appear in resit list\n";
}

echo "\n2. Checking enrollment 75 (Y2) for resit eligibility:\n";

if (!$markY2 || !$markY2->is_visible_to_student) {
    echo "   - ⏳ Marks not yet published for Y2 enrollment\n";
    echo "   - Cannot request resit until marks are published\n";
} else {
    $failedY2 = $markY2->total_marks < 50;
    echo "   - Failed in Y2? " . ($failedY2 ? "YES ({$markY2->total_marks}%)" : "NO ({$markY2->total_marks}%)") . "\n";
    
    if ($failedY2) {
        // Check if registered in yet another enrollment
        $isCurrentlyRegistered75 = StudentEnroll::where('student_id', $enrollment75->student_id)
            ->where('status', 1)
            ->where('id', '!=', 75)
            ->whereHas('subjects', function($query) use ($subject) {
                $query->where('subjects.id', $subject->id);
            })
            ->exists();
        
        echo "   - Currently registered in another enrollment? " . ($isCurrentlyRegistered75 ? "YES" : "NO") . "\n";
        
        if ($isCurrentlyRegistered75) {
            echo "   - ❌ BLOCKED: Won't appear in resit list for enrollment 75\n";
        } else {
            echo "   - ✅ ALLOWED: Would appear in resit list for enrollment 75\n";
        }
    }
}

echo "\n=== PROBLEM ANALYSIS ===\n";
echo "The current logic prevents resit requests for enrollment 68 because the student\n";
echo "is currently registered for the same course in enrollment 75.\n\n";
echo "However, if the student FAILS AGAIN in enrollment 75, they should be able to resit.\n\n";
echo "PROPOSED FIX:\n";
echo "- Only block resit requests if student is currently registered in an ACTIVE semester\n";
echo "- OR check if the newer enrollment's marks are already published and also failed\n";
echo "- In that case, allow resit request from the MOST RECENT failed enrollment\n";

echo "\n=== END ===\n";
