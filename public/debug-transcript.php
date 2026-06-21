<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentEnroll;
use App\Models\Student;

echo "<pre>";
echo "=== DEBUG: Transcript Data for Student 12133 ===\n\n";

$student = Student::where('student_id', '12133')
    ->with([
        'studentEnrolls.session',
        'studentEnrolls.semester',
        'studentEnrolls.section',
        'studentEnrolls.subjects',
        'studentEnrolls.subjectMarks'
    ])
    ->first();

echo "Student: {$student->first_name} {$student->last_name}\n\n";

foreach($student->studentEnrolls as $enroll) {
    echo "Enrollment ID: {$enroll->id}\n";
    echo "Session: " . ($enroll->session ? $enroll->session->title : 'N/A') . "\n";
    echo "Semester: " . ($enroll->semester ? $enroll->semester->title : 'N/A') . "\n";
    echo "Section: " . ($enroll->section ? $enroll->section->title : 'N/A') . "\n";
    echo "Subjects Count: " . $enroll->subjects->count() . "\n";
    
    foreach($enroll->subjects as $subject) {
        echo "  - Subject: {$subject->code} - {$subject->title} (Credit: {$subject->credit_hour})\n";
        
        // Check for marks
        $mark = $enroll->subjectMarks->where('subject_id', $subject->id)->first();
        if($mark) {
            echo "    Mark Found: Total={$mark->total_marks}, State={$mark->workflow_state}, Publish={$mark->publish_date} {$mark->publish_time}\n";
        } else {
            echo "    Mark: NOT FOUND\n";
        }
    }
    echo "\n";
}

echo "</pre>";
