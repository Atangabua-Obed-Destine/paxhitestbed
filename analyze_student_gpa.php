<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Grade;

echo "=== Student GPA Analysis ===\n\n";

$student = Student::where('student_id', '12133')
    ->with([
        'studentEnrolls.subjectMarks.subject',
        'studentEnrolls.session',
        'studentEnrolls.semester'
    ])
    ->first();

if (!$student) {
    echo "Student with ID 12133 not found.\n";
    exit;
}

echo "Student: {$student->first_name} {$student->last_name}\n";
echo "Student ID: {$student->student_id}\n";
echo "Program: " . ($student->program->title ?? 'N/A') . "\n";
echo "Batch: " . ($student->batch->title ?? 'N/A') . "\n\n";

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

echo "=== Grade Scale ===\n";
foreach ($grades as $grade) {
    echo "{$grade->title}: {$grade->min_mark}% - {$grade->max_mark}% = {$grade->point} points\n";
}
echo "\n";

$total_credits = 0;
$total_quality_points = 0;
$courseDetails = [];

echo "=== Course-by-Course Breakdown ===\n\n";

foreach ($student->studentEnrolls as $enroll) {
    $session = $enroll->session->title ?? 'Unknown';
    $semester = $enroll->semester->title ?? 'Unknown';
    
    if (isset($enroll->subjectMarks)) {
        foreach ($enroll->subjectMarks as $mark) {
            // Check if mark is published and visible
            if ($mark->workflow_state === 'published') {
                $publishDate = date('Y-m-d', strtotime($mark->publish_date));
                $publishTime = date('H:i:s', strtotime($mark->publish_time));
                $currentDate = date('Y-m-d');
                $currentTime = date('H:i:s');
                
                $isVisible = ($publishDate == $currentDate && $publishTime <= $currentTime) || $publishDate < $currentDate;
                
                if ($isVisible) {
                    $marks_per = round($mark->total_marks);
                    $subject = $mark->subject;
                    $creditHours = $subject->credit_hour;
                    
                    // Find matching grade
                    $gradeFound = null;
                    $gradePoint = 0;
                    
                    foreach ($grades as $grade) {
                        if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                            $gradeFound = $grade->title;
                            $gradePoint = $grade->point;
                            break;
                        }
                    }
                    
                    $qualityPoints = $gradePoint * $creditHours;
                    $total_credits += $creditHours;
                    $total_quality_points += $qualityPoints;
                    
                    $courseDetails[] = [
                        'session' => $session,
                        'semester' => $semester,
                        'code' => $subject->code,
                        'title' => $subject->title,
                        'credits' => $creditHours,
                        'score' => $marks_per,
                        'grade' => $gradeFound ?? 'N/A',
                        'points' => $gradePoint,
                        'quality_points' => $qualityPoints
                    ];
                    
                    echo "Session: {$session} | Semester: {$semester}\n";
                    echo "Course: {$subject->code} - {$subject->title}\n";
                    echo "Credits: {$creditHours}\n";
                    echo "Score: {$marks_per}%\n";
                    echo "Grade: " . ($gradeFound ?? 'N/A') . " (Points: {$gradePoint})\n";
                    echo "Quality Points: {$gradePoint} × {$creditHours} = {$qualityPoints}\n";
                    echo str_repeat('-', 60) . "\n";
                }
            }
        }
    }
}

echo "\n=== Summary ===\n";
echo "Total Courses: " . count($courseDetails) . "\n";
echo "Total Credits Attempted: {$total_credits}\n";
echo "Total Quality Points: {$total_quality_points}\n";

if ($total_credits > 0) {
    $cgpa = $total_quality_points / $total_credits;
    echo "\nCumulative GPA = {$total_quality_points} ÷ {$total_credits} = " . number_format($cgpa, 2) . "\n";
} else {
    echo "\nNo credits found - GPA cannot be calculated\n";
}

echo "\n=== Detailed Course Table ===\n";
echo str_pad("Session/Semester", 20) . " | " . str_pad("Course", 30) . " | " . str_pad("Credits", 8) . " | " . str_pad("Score", 6) . " | " . str_pad("Grade", 6) . " | " . str_pad("Points", 6) . " | " . str_pad("Quality Pts", 11) . "\n";
echo str_repeat("=", 120) . "\n";

foreach ($courseDetails as $course) {
    echo str_pad("{$course['session']}/{$course['semester']}", 20) . " | ";
    echo str_pad("{$course['code']}", 30) . " | ";
    echo str_pad($course['credits'], 8) . " | ";
    echo str_pad($course['score'] . '%', 6) . " | ";
    echo str_pad($course['grade'], 6) . " | ";
    echo str_pad($course['points'], 6) . " | ";
    echo str_pad($course['quality_points'], 11) . "\n";
}

echo "\n";
