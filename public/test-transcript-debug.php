<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Grade;

// Get student
$student = Student::where('student_id', '12133')
    ->with([
        'batch',
        'program',
        'studentEnrolls.session',
        'studentEnrolls.semester',
        'studentEnrolls.section',
        'studentEnrolls.subjects',
        'studentEnrolls.subjectMarks.subject'
    ])
    ->first();

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

echo "<h1>Student: {$student->first_name} {$student->last_name} (ID: {$student->student_id})</h1>";

foreach ($student->studentEnrolls as $enroll) {
    echo "<h2>Enrollment ID: {$enroll->id}</h2>";
    echo "<p>Session: " . ($enroll->session->title ?? 'N/A') . "</p>";
    echo "<p>Semester: " . ($enroll->semester->title ?? 'N/A') . "</p>";
    
    echo "<h3>Subjects:</h3>";
    foreach ($enroll->subjects as $subject) {
        echo "<h4>Subject: {$subject->code} - {$subject->title}</h4>";
        
        // Find marks for this subject
        $subjectMark = null;
        foreach ($enroll->subjectMarks as $mark) {
            if ($mark->subject_id == $subject->id) {
                $subjectMark = $mark;
                break;
            }
        }
        
        if ($subjectMark) {
            echo "<pre>";
            echo "Mark ID: {$subjectMark->id}\n";
            echo "Total Marks: {$subjectMark->total_marks}\n";
            echo "Workflow State: {$subjectMark->workflow_state}\n";
            echo "Publish Date: {$subjectMark->publish_date}\n";
            echo "Publish Time: {$subjectMark->publish_time}\n";
            echo "Status: {$subjectMark->status}\n";
            
            // Check the condition from transcript view
            $publishDateTime = date('Y-m-d H:i:s', strtotime($subjectMark->publish_date . ' ' . $subjectMark->publish_time));
            $currentDateTime = date('Y-m-d H:i:s');
            
            echo "\nPublish DateTime: {$publishDateTime}\n";
            echo "Current DateTime: {$currentDateTime}\n";
            
            $condition1 = $subjectMark->workflow_state === 'published';
            echo "Condition 1 (workflow_state === 'published'): " . ($condition1 ? 'TRUE' : 'FALSE') . "\n";
            
            $publishDate = date('Y-m-d', strtotime($subjectMark->publish_date));
            $today = date('Y-m-d');
            $publishTime = date('H:i:s', strtotime($subjectMark->publish_time));
            $currentTime = date('H:i:s');
            
            echo "\nPublish Date: {$publishDate}\n";
            echo "Today: {$today}\n";
            echo "Publish Time: {$publishTime}\n";
            echo "Current Time: {$currentTime}\n";
            
            $sameDateTimeCheck = ($publishDate == $today && $publishTime <= $currentTime);
            $pastDateCheck = ($publishDate < $today);
            
            echo "Same date time check: " . ($sameDateTimeCheck ? 'TRUE' : 'FALSE') . "\n";
            echo "Past date check: " . ($pastDateCheck ? 'TRUE' : 'FALSE') . "\n";
            
            $condition2 = (($publishDate == $today && $publishTime <= $currentTime) || $publishDate < $today);
            echo "Condition 2 (date/time check): " . ($condition2 ? 'TRUE' : 'FALSE') . "\n";
            
            $overallCondition = $condition1 && $condition2;
            echo "\nOVERALL CONDITION: " . ($overallCondition ? 'TRUE - SHOULD DISPLAY' : 'FALSE - WILL NOT DISPLAY') . "\n";
            
            if ($overallCondition) {
                $marks_per = round($subjectMark->total_marks);
                echo "\nRounded marks: {$marks_per}%\n";
                
                foreach ($grades as $grade) {
                    if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                        echo "Grade: {$grade->title} (Point: {$grade->point})\n";
                        echo "Min Mark: {$grade->min_mark}%, Max Mark: {$grade->max_mark}%\n";
                        break;
                    }
                }
            }
            
            echo "</pre>";
        } else {
            echo "<p style='color: red;'>NO MARKS FOUND FOR THIS SUBJECT</p>";
        }
        
        echo "<hr>";
    }
    
    echo "<hr style='border: 2px solid black;'>";
}
