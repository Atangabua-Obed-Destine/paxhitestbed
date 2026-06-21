<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Grade;

// Force output buffering off
if (ob_get_level()) {
    ob_end_flush();
}

echo "=== DETAILED GPA ANALYSIS FOR STUDENT PAX25FBF005 ===\n\n";
flush();

// Find the student
$student = Student::where('student_id', 'PAX25FBF005')
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

if (!$student) {
    echo "ERROR: Student with ID PAX25FBF005 not found!\n";
    exit;
}

echo "Student Name: {$student->first_name} {$student->last_name}\n";
echo "Student ID: {$student->student_id}\n";
echo "Program: {$student->program->title}\n";
echo "Batch: {$student->batch->title}\n\n";

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

echo "=== GRADE SCALE ===\n";
foreach($grades as $grade) {
    echo "{$grade->title}: {$grade->point} ({$grade->min_mark}% - {$grade->max_mark}%)\n";
}
echo "\n";

// Calculate CGPA
$total_credits = 0;
$total_quality_points = 0;

echo "=== DETAILED COURSE BREAKDOWN ===\n\n";

// Get unique semester combinations
$semester_items = [];
$semester_keys = [];

foreach ($student->studentEnrolls as $enroll) {
    if (isset($enroll->session) && isset($enroll->semester)) {
        $semester_key = $enroll->session->title . '|' . $enroll->semester->title;
        if (!in_array($semester_key, $semester_keys)) {
            $semester_items[] = [
                'session' => $enroll->session->title,
                'semester' => $enroll->semester->title,
                'key' => $semester_key
            ];
            $semester_keys[] = $semester_key;
        }
    }
}

$semester_number = 1;
foreach ($semester_items as $semester_item) {
    echo "SEMESTER {$semester_number}: {$semester_item['session']} - {$semester_item['semester']}\n";
    echo str_repeat("-", 80) . "\n";
    
    $semester_credits = 0;
    $semester_quality_points = 0;
    $course_number = 1;

    foreach ($student->studentEnrolls as $enroll) {
        if (isset($enroll->semester) && isset($enroll->session) &&
            $semester_item['semester'] == $enroll->semester->title &&
            $semester_item['session'] == $enroll->session->title) {

            if (isset($enroll->subjectMarks)) {
                foreach ($enroll->subjectMarks as $mark) {
                    // Check if marks are published and visible
                    $publishDate = $mark->publish_date instanceof \Carbon\Carbon ? 
                                  $mark->publish_date->format('Y-m-d') : 
                                  date('Y-m-d', strtotime($mark->publish_date));
                    $publishTime = $mark->publish_time instanceof \Carbon\Carbon ? 
                                  $mark->publish_time->format('H:i:s') : 
                                  date('H:i:s', strtotime($mark->publish_time));
                    $currentDate = date('Y-m-d');
                    $currentTime = date('H:i:s');

                    $isVisible = $mark->workflow_state === 'published' && 
                                (($publishDate == $currentDate && $publishTime <= $currentTime) || 
                                 $publishDate < $currentDate);

                    if ($isVisible && isset($mark->subject)) {
                        $marks_per = round($mark->total_marks);
                        $credit_hour = (float) $mark->subject->credit_hour;

                        echo "Course {$course_number}: {$mark->subject->title}\n";
                        echo "  Subject Code: {$mark->subject->code}\n";
                        echo "  Total Marks: {$marks_per}%\n";
                        echo "  Credits: {$credit_hour}\n";

                        foreach ($grades as $grade) {
                            if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                $grade_point = (float) $grade->point;
                                $quality_points = $grade_point * $credit_hour;

                                echo "  Grade: {$grade->title} ({$grade->min_mark}%-{$grade->max_mark}%)\n";
                                echo "  Grade Point: {$grade_point}\n";
                                echo "  Quality Points: {$grade_point} × {$credit_hour} = {$quality_points}\n";

                                $semester_credits += $credit_hour;
                                $semester_quality_points += $quality_points;
                                $total_credits += $credit_hour;
                                $total_quality_points += $quality_points;
                                break;
                            }
                        }
                        echo "\n";
                        $course_number++;
                    }
                }
            }
        }
    }

    if ($semester_credits > 0) {
        $semester_gpa = $semester_quality_points / $semester_credits;
        echo "Semester {$semester_number} Summary:\n";
        echo "  Total Credits: {$semester_credits}\n";
        echo "  Total Quality Points: {$semester_quality_points}\n";
        echo "  Semester GPA: {$semester_quality_points} ÷ {$semester_credits} = " . number_format($semester_gpa, 2) . "\n\n";
    } else {
        echo "No published marks for this semester.\n\n";
    }

    echo str_repeat("=", 80) . "\n\n";
    $semester_number++;
}

echo "=== CUMULATIVE GPA CALCULATION ===\n\n";
echo "Total Credits Attempted: {$total_credits}\n";
echo "Total Quality Points: {$total_quality_points}\n";

if ($total_credits > 0) {
    $cgpa = $total_quality_points / $total_credits;
    echo "\nFormula: CGPA = Total Quality Points ÷ Total Credits\n";
    echo "CGPA = {$total_quality_points} ÷ {$total_credits}\n";
    echo "CGPA = " . number_format($cgpa, 2) . "\n\n";
    
    if (abs($cgpa - 3.5) < 0.01) {
        echo "✓ VERIFICATION: This matches the reported CGPA of 3.5!\n";
    } else {
        echo "⚠ WARNING: This does NOT match the reported CGPA of 3.5!\n";
        echo "Expected: 3.5\n";
        echo "Calculated: " . number_format($cgpa, 2) . "\n";
        echo "Difference: " . number_format(abs($cgpa - 3.5), 4) . "\n";
    }
} else {
    echo "\nNo credits found! Student has no published marks.\n";
}

echo "\n=== GRADE CLASSIFICATION ===\n";
if ($total_credits > 0) {
    $cgpa = $total_quality_points / $total_credits;
    if ($cgpa >= 3.5) {
        echo "Classification: OUTSTANDING (CGPA ≥ 3.5)\n";
    } elseif ($cgpa >= 3.0) {
        echo "Classification: GOOD (CGPA ≥ 3.0)\n";
    } elseif ($cgpa >= 2.5) {
        echo "Classification: SATISFACTORY (CGPA ≥ 2.5)\n";
    } else {
        echo "Classification: NEEDS IMPROVEMENT (CGPA < 2.5)\n";
    }
}

echo "\n=== ANALYSIS COMPLETE ===\n";
