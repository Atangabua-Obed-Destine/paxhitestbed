<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Grade;

echo "<h1>CGPA Verification - After Fix</h1>";
echo "<p>Student ID: 12133</p>";

$student = Student::where('student_id', '12133')
    ->with([
        'studentEnrolls.subjectMarks.subject',
        'studentEnrolls.session',
        'studentEnrolls.semester'
    ])
    ->first();

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

$total_credits = 0;
$total_quality_points = 0;
$published_courses = [];

foreach($student->studentEnrolls as $enroll) {
    foreach($enroll->subjectMarks as $mark) {
        // Check if published and time is valid
        $publishDate = date('Y-m-d', strtotime($mark->publish_date));
        $publishTime = date('H:i:s', strtotime($mark->publish_time));
        $today = date('Y-m-d');
        $now = date('H:i:s');
        
        $isPublished = $mark->workflow_state === 'published';
        $isTimeOk = (($publishDate == $today && $publishTime <= $now) || $publishDate < $today);
        
        if ($isPublished && $isTimeOk && $mark->subject) {
            $marks_per = round($mark->total_marks);
            
            foreach($grades as $grade) {
                if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                    $creditHours = $mark->subject->credit_hour;
                    $gradePoint = $grade->point;
                    $qualityPoints = $gradePoint * $creditHours;
                    
                    $total_credits += $creditHours;
                    $total_quality_points += $qualityPoints;
                    
                    $published_courses[] = [
                        'code' => $mark->subject->code,
                        'title' => $mark->subject->title,
                        'marks' => $marks_per,
                        'grade' => $grade->title,
                        'point' => $gradePoint,
                        'credits' => $creditHours,
                        'quality' => $qualityPoints
                    ];
                    break;
                }
            }
        }
    }
}

echo "<h2>Published Courses</h2>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>Course Code</th>";
echo "<th>Course Title</th>";
echo "<th>Marks %</th>";
echo "<th>Grade</th>";
echo "<th>Grade Point</th>";
echo "<th>Credits</th>";
echo "<th>Quality Points</th>";
echo "</tr>";

foreach($published_courses as $course) {
    $bgColor = $course['point'] == 0 ? '#ffebee' : '#e8f5e9';
    echo "<tr style='background: {$bgColor};'>";
    echo "<td>{$course['code']}</td>";
    echo "<td>{$course['title']}</td>";
    echo "<td>{$course['marks']}%</td>";
    echo "<td><strong>{$course['grade']}</strong></td>";
    echo "<td>" . number_format($course['point'], 2) . "</td>";
    echo "<td>" . number_format($course['credits'], 2) . "</td>";
    echo "<td>" . number_format($course['quality'], 2) . "</td>";
    echo "</tr>";
}

echo "<tr style='background: #fff3cd; font-weight: bold;'>";
echo "<td colspan='5' style='text-align: right;'>TOTALS:</td>";
echo "<td>" . number_format($total_credits, 2) . "</td>";
echo "<td>" . number_format($total_quality_points, 2) . "</td>";
echo "</tr>";
echo "</table>";

$cgpa = $total_credits > 0 ? $total_quality_points / $total_credits : 0;

echo "<h2>CGPA Calculation</h2>";
echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<p style='font-size: 1.1em;'><strong>Formula:</strong> CGPA = Total Quality Points ÷ Total Credits</p>";
echo "<p style='font-size: 1.1em;'><strong>Calculation:</strong> " . number_format($total_quality_points, 2) . " ÷ " . number_format($total_credits, 2) . " = <span style='color: #1976d2; font-size: 1.5em; font-weight: bold;'>" . number_format($cgpa, 2) . "</span></p>";
echo "</div>";

echo "<h2>Explanation</h2>";
echo "<div style='background: #f5f5f5; padding: 15px; border-left: 4px solid #2196f3;'>";
echo "<p><strong>The Fix:</strong></p>";
echo "<ul>";
echo "<li>✅ <strong>NOW:</strong> All courses with published grades (including F grades with 0.00 points) are counted in total Credits</li>";
echo "<li>✅ This correctly reflects that the student <em>attempted</em> these courses</li>";
echo "<li>✅ F grade courses contribute 0 quality points (0.00 × Credits = 0)</li>";
echo "<li>✅ But they still count towards the denominator (total credits attempted)</li>";
echo "<li>✅ This gives an accurate CGPA that reflects all coursework</li>";
echo "</ul>";
echo "<p style='margin-top: 15px;'><strong>Previous Bug:</strong></p>";
echo "<ul>";
echo "<li>❌ <strong>BEFORE:</strong> Only courses with point > 0 were counted in total Credits</li>";
echo "<li>❌ F grade courses were excluded from the denominator</li>";
echo "<li>❌ This artificially inflated the CGPA by reducing the credits attempted</li>";
echo "<li>❌ Example: If student has 1 passing course (3 credits, 2.0 GPA) and 1 F course (3 credits, 0.0 GPA)</li>";
echo "<li style='margin-left: 20px;'>• Old calculation: 6.00 quality points ÷ 3 credits = 2.00 CGPA (wrong!)</li>";
echo "<li style='margin-left: 20px;'>• New calculation: 6.00 quality points ÷ 6 credits = 1.00 CGPA (correct!)</li>";
echo "</ul>";
echo "</div>";

echo "<p style='margin-top: 20px;'><a href='/paxhitest/student/transcript' style='display: inline-block; padding: 10px 20px; background: #2196f3; color: white; text-decoration: none; border-radius: 4px;'>View Student Transcript →</a></p>";
