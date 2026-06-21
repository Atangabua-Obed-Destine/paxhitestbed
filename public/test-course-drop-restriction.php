<?php
/**
 * Test script for Course Drop Restriction
 * Verifies that compulsory and university requirement courses cannot be dropped
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Subject;

echo "<h1>Course Drop Restriction Test</h1>";
echo "<p>Testing that compulsory and university requirement courses cannot be dropped</p>";
echo "<hr>";

// Test with student ID 6
$studentId = 6;
$student = Student::with(['studentEnrolls' => function($query) {
    $query->where('status', '1')->orderByDesc('id');
}])->find($studentId);

if (!$student) {
    die("Student not found!");
}

$currentEnroll = $student->studentEnrolls->first();

if (!$currentEnroll) {
    die("Student is not currently enrolled in any semester!");
}

echo "<h2>Student Information</h2>";
echo "<p><strong>Student ID:</strong> {$student->id}</p>";
echo "<p><strong>Name:</strong> {$student->first_name} {$student->last_name}</p>";
echo "<p><strong>Current Enrollment:</strong> Semester {$currentEnroll->semester->title ?? 'N/A'}</p>";
echo "<hr>";

echo "<h2>Currently Registered Courses</h2>";

$currentEnroll->load('subjects');

if ($currentEnroll->subjects->isEmpty()) {
    echo "<p>No courses registered.</p>";
} else {
    // Get subjects enrolled for the current semester (from enroll_subject table)
    $enrollSubject = \App\Models\EnrollSubject::where('program_id', $currentEnroll->program_id)
        ->where('semester_id', $currentEnroll->semester_id)
        ->where('section_id', $currentEnroll->section_id)
        ->first();
    
    $currentSemesterEnrolledSubjectIds = [];
    if ($enrollSubject) {
        $currentSemesterEnrolledSubjectIds = $enrollSubject->subjects()->pluck('subject_id')->toArray();
    }
    
    echo "<div style='background: #e7f3ff; padding: 15px; margin-bottom: 15px; border-left: 4px solid #2196F3;'>";
    echo "<strong>Current Semester Info:</strong><br>";
    echo "Program: {$currentEnroll->program->title ?? 'N/A'}<br>";
    echo "Semester: {$currentEnroll->semester->title ?? 'N/A'}<br>";
    echo "Section: {$currentEnroll->section->title ?? 'N/A'}<br>";
    echo "Subjects assigned to this semester: " . count($currentSemesterEnrolledSubjectIds);
    echo "</div>";
    
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>Code</th>";
    echo "<th>Title</th>";
    echo "<th>Credits</th>";
    echo "<th>Type</th>";
    echo "<th>Assigned to<br>Current Semester?</th>";
    echo "<th>Can Drop?</th>";
    echo "<th>Reason</th>";
    echo "</tr>";
    
    $subjectTypes = [
        0 => 'Optional',
        1 => 'Compulsory',
        2 => 'University Requirement'
    ];
    
    foreach ($currentEnroll->subjects as $subject) {
        $subjectType = $subject->subject_type;
        $subjectTypeName = $subjectTypes[$subjectType] ?? 'Unknown';
        
        // Check if marks have been submitted
        $hasMarks = \App\Models\SubjectMarking::where('student_enroll_id', $currentEnroll->id)
            ->where('subject_id', $subject->id)
            ->exists();
        
        // Check if subject is assigned to current semester
        $isAssignedToCurrentSemester = in_array($subject->id, $currentSemesterEnrolledSubjectIds);
        
        // Determine if can drop
        $canDrop = true;
        $reason = 'Can be dropped (Optional or from other semester)';
        
        if ($hasMarks) {
            $canDrop = false;
            $reason = 'Marks have been submitted';
        } elseif (($subjectType == 1 || $subjectType == 2) && $isAssignedToCurrentSemester) {
            $canDrop = false;
            $reason = $subjectTypeName . ' course assigned to current semester - Required';
        } elseif ($subjectType == 1 || $subjectType == 2) {
            $reason = $subjectTypeName . ' but from another semester - Can drop if no marks';
        }
        
        $canDropText = $canDrop ? '<span style="color: green; font-weight: bold;">✓ YES</span>' : '<span style="color: red; font-weight: bold;">✗ NO</span>';
        $assignedText = $isAssignedToCurrentSemester ? '<span style="color: blue; font-weight: bold;">✓ YES</span>' : '<span style="color: gray;">✗ NO</span>';
        
        echo "<tr>";
        echo "<td>{$subject->code}</td>";
        echo "<td>{$subject->title}</td>";
        echo "<td>{$subject->credit_hour}</td>";
        echo "<td><strong>{$subjectTypeName}</strong></td>";
        echo "<td>{$assignedText}</td>";
        echo "<td>{$canDropText}</td>";
        echo "<td>{$reason}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<hr>";

echo "<h2>Business Rules Summary</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff;'>";
echo "<h3>Students CANNOT drop courses if:</h3>";
echo "<ol>";
echo "<li><strong>Course Type = Compulsory (subject_type = 1) AND assigned to current semester</strong><br>";
echo "   <em>Reason: Compulsory courses assigned to the current semester are mandatory</em></li>";
echo "<li><strong>Course Type = University Requirement (subject_type = 2) AND assigned to current semester</strong><br>";
echo "   <em>Reason: University requirements assigned to the current semester are mandatory</em></li>";
echo "<li><strong>Marks have been submitted</strong><br>";
echo "   <em>Reason: Cannot modify enrollment after assessment has begun (applies to all courses)</em></li>";
echo "</ol>";
echo "<h3>Students CAN drop courses if:</h3>";
echo "<ul>";
echo "<li><strong>Course Type = Optional (subject_type = 0)</strong> - regardless of semester assignment</li>";
echo "<li><strong>Course Type = Compulsory/University Requirement BUT from other semesters</strong> (not assigned to current semester via enroll-subject)</li>";
echo "<li><strong>AND no marks have been submitted</strong> (this condition applies to all droppable courses)</li>";
echo "</ul>";
echo "<p><strong>Key Point:</strong> The restriction only applies to compulsory/university requirement courses that are specifically assigned to the student's current semester in the enroll-subject table (admin/academic/enroll-subject).</p>";
echo "</div>";

echo "<hr>";

echo "<h2>Implementation Details</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>";
echo "<h3>Changes Made:</h3>";
echo "<ol>";
echo "<li><strong>Controller Validation:</strong> Added check in CourseRegistrationController::drop() method to:";
echo "   <ul>";
echo "   <li>Validate subject_type (compulsory or university requirement)</li>";
echo "   <li>Check if subject is assigned to current semester via EnrollSubject table</li>";
echo "   <li>Only restrict drop if BOTH conditions are true</li>";
echo "   </ul>";
echo "</li>";
echo "<li><strong>View Update:</strong> Modified course-registration/index.blade.php to show 'Required' button only for compulsory/university requirement courses assigned to current semester</li>";
echo "<li><strong>Data Passing:</strong> Added 'currentSemesterEnrolledSubjectIds' to view data to identify which subjects are assigned to the current semester</li>";
echo "<li><strong>User Feedback:</strong> Clear error messages explaining why certain courses cannot be dropped</li>";
echo "</ol>";
echo "<h3>Database Tables Used:</h3>";
echo "<ul>";
echo "<li><strong>enroll_subject:</strong> Stores which subjects are assigned to which program/semester/section combinations</li>";
echo "<li><strong>subject_marking:</strong> Tracks if marks have been submitted for a course</li>";
echo "<li><strong>subjects:</strong> Contains subject_type field (0=Optional, 1=Compulsory, 2=University Requirement)</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<h2>Test Complete</h2>";
echo "<p>Navigate to <a href='http://localhost/paxhitest/student/course-registration'>Course Registration</a> to see the restrictions in action.</p>";
