<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Grade;

echo "<h1>CGPA Calculation Debug for Student 12133</h1>";

$student = Student::where('student_id', '12133')
    ->with([
        'studentEnrolls.subjectMarks.subject',
        'studentEnrolls.session',
        'studentEnrolls.semester'
    ])
    ->first();

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

echo "<h2>Student: {$student->first_name} {$student->last_name}</h2>";
echo "<p>Student ID: {$student->student_id} (DB ID: {$student->id})</p>";

$total_credits = 0;
$total_cgpa = 0;
$all_subjects = [];

foreach($student->studentEnrolls as $enroll) {
    echo "<h3>Enrollment ID: {$enroll->id}</h3>";
    echo "<p>Session: " . ($enroll->session->title ?? 'N/A') . " | Semester: " . ($enroll->semester->title ?? 'N/A') . "</p>";
    
    if($enroll->subjectMarks->count() > 0) {
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>Subject ID</th><th>Subject Code</th><th>Credits</th><th>Total Marks %</th><th>Workflow State</th><th>Publish Date</th><th>Publish Time</th><th>Grade</th><th>Point</th><th>Quality Points</th><th>Included?</th></tr>";
        
        foreach($enroll->subjectMarks as $mark) {
            $subject = $mark->subject;
            $creditHours = $subject ? $subject->credit_hour : 0;
            
            // Check publish date/time condition from transcript
            $publishDate = date('Y-m-d', strtotime($mark->publish_date));
            $publishTime = date('H:i:s', strtotime($mark->publish_time));
            $today = date('Y-m-d');
            $now = date('H:i:s');
            
            $isPublished = $mark->workflow_state === 'published';
            $isTimeOk = (($publishDate == $today && $publishTime <= $now) || $publishDate < $today);
            $shouldInclude = $isPublished && $isTimeOk;
            
            echo "<tr>";
            echo "<td>{$mark->subject_id}</td>";
            echo "<td>" . ($subject->code ?? 'N/A') . "</td>";
            echo "<td>{$creditHours}</td>";
            echo "<td>" . round($mark->total_marks) . "%</td>";
            echo "<td>{$mark->workflow_state}</td>";
            echo "<td>{$mark->publish_date}</td>";
            echo "<td>{$mark->publish_time}</td>";
            
            if ($shouldInclude) {
                $marks_per = round($mark->total_marks);
                $foundGrade = null;
                $gradePoint = 0;
                
                foreach($grades as $grade) {
                    if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                        $foundGrade = $grade;
                        $gradePoint = $grade->point;
                        break;
                    }
                }
                
                echo "<td>" . ($foundGrade ? $foundGrade->title : 'N/A') . "</td>";
                echo "<td>" . number_format($gradePoint, 2) . "</td>";
                
                $qualityPoints = $gradePoint * $creditHours;
                echo "<td>" . number_format($qualityPoints, 2) . "</td>";
                
                if($gradePoint > 0) {
                    echo "<td style='background: #d4edda;'>✓ YES (Point > 0)</td>";
                    $total_cgpa += $qualityPoints;
                    $total_credits += $creditHours;
                } else {
                    echo "<td style='background: #fff3cd;'>⚠ NO (Point = 0, F grade)</td>";
                    $total_cgpa += $qualityPoints; // Still add to CGPA (0 * credits = 0)
                    $total_credits += $creditHours; // Still count credits attempted
                }
                
                $all_subjects[] = [
                    'subject_id' => $mark->subject_id,
                    'code' => $subject->code ?? 'N/A',
                    'marks' => $marks_per,
                    'credit' => $creditHours,
                    'point' => $gradePoint,
                    'quality' => $qualityPoints,
                    'grade' => $foundGrade ? $foundGrade->title : 'N/A'
                ];
            } else {
                echo "<td colspan='3'>Not published or not yet available</td>";
                echo "<td style='background: #f8d7da;'>✗ NO (Not published/available)</td>";
            }
            
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No subject marks for this enrollment</p>";
    }
    
    echo "<hr>";
}

echo "<h2>CGPA Calculation Summary</h2>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Total Quality Points</th><td>" . number_format($total_cgpa, 2) . "</td></tr>";
echo "<tr><th>Total Credits</th><td>" . number_format($total_credits, 2) . "</td></tr>";

if($total_credits <= 0){
    $total_credits = 1;
}
$com_gpa = $total_cgpa / $total_credits;

echo "<tr><th>Cumulative GPA</th><td><strong>" . number_format($com_gpa, 2) . "</strong></td></tr>";
echo "</table>";

echo "<h3>Grading Scale</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Grade</th><th>Point</th><th>Min Mark</th><th>Max Mark</th></tr>";
foreach($grades as $grade) {
    echo "<tr>";
    echo "<td>{$grade->title}</td>";
    echo "<td>" . number_format($grade->point, 2) . "</td>";
    echo "<td>" . number_format($grade->min_mark, 2) . "%</td>";
    echo "<td>" . number_format($grade->max_mark, 2) . "%</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Issue Analysis</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>The issue is that the transcript view excludes courses with point > 0 from credit_earned, but still includes them in the CGPA calculation!</strong></p>";
echo "<p>The logic has a bug:</p>";
echo "<pre>";
echo "if(\$grade->point > 0){\n";
echo "    \$total_cgpa = \$total_cgpa + (\$grade->point * \$mark->subject->credit_hour);\n";
echo "    \$total_credits = \$total_credits + \$mark->subject->credit_hour;\n";
echo "}\n";
echo "</pre>";
echo "<p>This means subjects with F grade (0.00 points) are NOT counted in the credits, but the division still happens, skewing the GPA.</p>";
echo "<p><strong>Correct behavior should be:</strong> ALL attempted courses should count towards credits attempted, even F grades.</p>";
echo "</div>";

echo "<h3>All Included Subjects:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>#</th><th>Code</th><th>Marks %</th><th>Grade</th><th>Point</th><th>Credits</th><th>Quality Points</th></tr>";
foreach($all_subjects as $i => $subj) {
    $bgColor = $subj['point'] == 0 ? '#fff3cd' : '#d4edda';
    echo "<tr style='background: {$bgColor};'>";
    echo "<td>" . ($i+1) . "</td>";
    echo "<td>{$subj['code']}</td>";
    echo "<td>{$subj['marks']}%</td>";
    echo "<td>{$subj['grade']}</td>";
    echo "<td>" . number_format($subj['point'], 2) . "</td>";
    echo "<td>" . number_format($subj['credit'], 2) . "</td>";
    echo "<td>" . number_format($subj['quality'], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";
