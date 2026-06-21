<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Grade;

echo "<pre>";
echo "=== SIMULATING TRANSCRIPT VIEW LOGIC ===\n\n";

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

// Focus on Enrollment ID 27
$enrollment = $student->studentEnrolls->where('id', 27)->first();

if($enrollment) {
    echo "Enrollment ID: {$enrollment->id}\n";
    echo "Session: {$enrollment->session->title}\n";
    echo "Semester: {$enrollment->semester->title}\n\n";
    
    echo "Subjects in enrollment->subjects: {$enrollment->subjects->count()}\n";
    foreach($enrollment->subjects as $subject) {
        echo "\n--- Processing Subject: {$subject->code} - {$subject->title} ---\n";
        echo "Subject ID: {$subject->id}\n";
        echo "Credit Hour: {$subject->credit_hour}\n";
        
        echo "\nChecking subjectMarks collection...\n";
        echo "Total marks in enrollment->subjectMarks: {$enrollment->subjectMarks->count()}\n";
        
        if(isset($enrollment->subjectMarks)) {
            $found = false;
            foreach($enrollment->subjectMarks as $mark) {
                echo "  Mark - Subject ID: {$mark->subject_id}, Total: {$mark->total_marks}, State: {$mark->workflow_state}\n";
                
                if($mark->subject_id == $subject->id) {
                    echo "  ✓ MATCH FOUND!\n";
                    $found = true;
                    
                    $publish_check = ($mark->workflow_state === 'published' && 
                        ((date('Y-m-d', strtotime($mark->publish_date)) == date('Y-m-d') && 
                          date('H:i:s', strtotime($mark->publish_time)) <= date('H:i:s')) || 
                         date('Y-m-d', strtotime($mark->publish_date)) < date('Y-m-d')));
                    
                    echo "  Workflow State Check: " . ($mark->workflow_state === 'published' ? 'PASS' : 'FAIL') . "\n";
                    echo "  Publish Check: " . ($publish_check ? 'PASS' : 'FAIL') . "\n";
                    
                    if($publish_check) {
                        $marks_per = round($mark->total_marks);
                        echo "  Marks Percentage: {$marks_per}%\n";
                        
                        foreach($grades as $grade) {
                            if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                echo "  ✓ Grade Found: {$grade->title} ({$grade->point} points)\n";
                                echo "  Quality Points: " . ($grade->point * $subject->credit_hour) . "\n";
                                break;
                            }
                        }
                    }
                    break;
                }
            }
            
            if(!$found) {
                echo "  ✗ NO MATCHING MARK FOUND for subject_id {$subject->id}\n";
            }
        } else {
            echo "  ✗ subjectMarks not set!\n";
        }
    }
} else {
    echo "Enrollment 27 not found!\n";
}

echo "\n</pre>";
