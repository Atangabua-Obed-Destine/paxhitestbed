<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;

echo "<h1>Transcript Debug for Student 12133</h1>";

$row = Student::where('student_id', '12133')
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

echo "<h2>Student Info:</h2>";
echo "<p>ID: {$row->id} (Student ID: {$row->student_id})</p>";
echo "<p>Name: {$row->first_name} {$row->last_name}</p>";

echo "<h2>Student Enrolls:</h2>";
foreach ($row->studentEnrolls as $enroll) {
    echo "<div style='border: 2px solid blue; padding: 10px; margin: 10px 0;'>";
    echo "<h3>Enrollment ID: {$enroll->id}</h3>";
    echo "<p>Session: " . ($enroll->session->title ?? 'NULL') . " (ID: {$enroll->session_id})</p>";
    echo "<p>Semester: " . ($enroll->semester->title ?? 'NULL') . " (ID: {$enroll->semester_id})</p>";
    
    echo "<h4>Subject Marks Count: " . $enroll->subjectMarks->count() . "</h4>";
    
    if ($enroll->subjectMarks->count() > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Subject ID</th><th>Subject Code</th><th>Total Marks</th><th>Workflow State</th><th>Publish Date</th><th>Publish Time</th><th>Status</th></tr>";
        
        foreach ($enroll->subjectMarks as $mark) {
            echo "<tr>";
            echo "<td>{$mark->id}</td>";
            echo "<td>{$mark->subject_id}</td>";
            echo "<td>" . ($mark->subject->code ?? 'N/A') . "</td>";
            echo "<td>{$mark->total_marks}</td>";
            echo "<td>{$mark->workflow_state}</td>";
            echo "<td>{$mark->publish_date}</td>";
            echo "<td>{$mark->publish_time}</td>";
            echo "<td>{$mark->status}</td>";
            echo "</tr>";
            
            // Check the publish date/time logic
            $publishDate = date('Y-m-d', strtotime($mark->publish_date));
            $publishTime = date('H:i:s', strtotime($mark->publish_time));
            $today = date('Y-m-d');
            $now = date('H:i:s');
            
            echo "<tr><td colspan='8'>";
            echo "Publish Check: ";
            $isPublished = $mark->workflow_state === 'published';
            $isTimeOk = (($publishDate == $today && $publishTime <= $now) || $publishDate < $today);
            
            echo "workflow_state='published': " . ($isPublished ? 'YES' : 'NO') . " | ";
            echo "Time OK: " . ($isTimeOk ? 'YES' : 'NO') . " | ";
            echo "OVERALL: " . ($isPublished && $isTimeOk ? '<strong style="color:green;">SHOULD DISPLAY</strong>' : '<strong style="color:red;">WILL NOT DISPLAY</strong>');
            echo "</td></tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color: red;'>NO SUBJECT MARKS LOADED FOR THIS ENROLLMENT!</p>";
    }
    
    echo "<h4>Checking ACC11O2H specifically:</h4>";
    $acc_subject = $enroll->subjects->where('code', 'ACC11O2H')->first();
    if ($acc_subject) {
        echo "<p>Subject Found: {$acc_subject->code} - {$acc_subject->title} (ID: {$acc_subject->id})</p>";
        
        // Find mark for this subject
        $acc_mark = $enroll->subjectMarks->where('subject_id', $acc_subject->id)->first();
        if ($acc_mark) {
            echo "<p style='color: green;'>MARK FOUND!</p>";
            echo "<pre>";
            print_r($acc_mark->toArray());
            echo "</pre>";
            
            // Simulate the grade calculation
            $marks_per = round($acc_mark->total_marks);
            echo "<p>Rounded Marks: {$marks_per}%</p>";
            
            foreach ($grades as $grade) {
                if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                    echo "<p style='color: green;'>GRADE MATCHED: {$grade->title} (Point: {$grade->point})</p>";
                    echo "<p>Range: {$grade->min_mark}% - {$grade->max_mark}%</p>";
                    break;
                }
            }
        } else {
            echo "<p style='color: red;'>NO MARK FOUND FOR THIS SUBJECT IN subjectMarks COLLECTION!</p>";
            
            // Try direct query
            $direct_mark = DB::table('subject_markings')
                ->where('student_enroll_id', $enroll->id)
                ->where('subject_id', $acc_subject->id)
                ->first();
            
            if ($direct_mark) {
                echo "<p style='color: orange;'>BUT DIRECT QUERY FOUND IT:</p>";
                echo "<pre>";
                print_r($direct_mark);
                echo "</pre>";
            }
        }
    } else {
        echo "<p style='color: red;'>ACC11O2H NOT FOUND IN ENROLLED SUBJECTS!</p>";
    }
    
    echo "</div>";
}
