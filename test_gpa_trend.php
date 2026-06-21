<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\Grade;

echo "=== GPA TREND VISUALIZATION TEST ===\n\n";

// Test with student 12133 (Deandra Enjoyeh)
$student_id = 12133;

$student = Student::where('student_id', $student_id)
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
    echo "Student not found!\n";
    exit;
}

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

echo "Student: {$student->first_name} {$student->last_name}\n";
echo "Student ID: {$student->student_id}\n";
echo "Program: {$student->program->title}\n\n";

// Prepare GPA trend data
$trend_data = [];
$cumulative_quality_points = 0;
$cumulative_credits = 0;

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

echo "=== SEMESTER-BY-SEMESTER BREAKDOWN ===\n\n";

// Calculate GPA for each semester
foreach ($semester_items as $index => $semester_item) {
    $semester_credits = 0;
    $semester_quality_points = 0;

    echo "Semester " . ($index + 1) . ": {$semester_item['session']} - {$semester_item['semester']}\n";
    echo str_repeat("-", 60) . "\n";

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

                        foreach ($grades as $grade) {
                            if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                $grade_point = (float) $grade->point;
                                $quality_points = $grade_point * $credit_hour;

                                echo "  - {$mark->subject->title}: {$marks_per}% (Grade: {$grade->title}, {$grade_point} × {$credit_hour} credits = {$quality_points} QP)\n";

                                $semester_credits += $credit_hour;
                                $semester_quality_points += $quality_points;
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    // Calculate semester GPA
    $semester_gpa = $semester_credits > 0 ? $semester_quality_points / $semester_credits : 0;

    // Update cumulative values
    $cumulative_credits += $semester_credits;
    $cumulative_quality_points += $semester_quality_points;
    $cumulative_gpa = $cumulative_credits > 0 ? $cumulative_quality_points / $cumulative_credits : 0;

    echo "\n  Semester Credits: " . number_format($semester_credits, 2) . "\n";
    echo "  Semester Quality Points: " . number_format($semester_quality_points, 2) . "\n";
    echo "  Semester GPA: " . number_format($semester_gpa, 2) . "\n";
    echo "  Cumulative Credits: " . number_format($cumulative_credits, 2) . "\n";
    echo "  Cumulative Quality Points: " . number_format($cumulative_quality_points, 2) . "\n";
    echo "  Cumulative GPA: " . number_format($cumulative_gpa, 2) . "\n\n";

    // Store data point
    $trend_data[] = [
        'label' => $semester_item['session'] . ' - ' . $semester_item['semester'],
        'semester_gpa' => round($semester_gpa, 2),
        'cumulative_gpa' => round($cumulative_gpa, 2),
        'credits' => round($semester_credits, 2),
        'cumulative_credits' => round($cumulative_credits, 2)
    ];
}

echo "\n=== CHART DATA (JSON Format) ===\n\n";
echo json_encode($trend_data, JSON_PRETTY_PRINT) . "\n\n";

echo "=== PERFORMANCE SUMMARY ===\n\n";
if (count($trend_data) > 0) {
    $total_semesters = count($trend_data);
    $highest_semester_gpa = max(array_column($trend_data, 'semester_gpa'));
    $lowest_semester_gpa = min(array_column($trend_data, 'semester_gpa'));
    $final_cgpa = end($trend_data)['cumulative_gpa'];
    $total_credits_earned = end($trend_data)['cumulative_credits'];
    
    echo "Total Semesters: {$total_semesters}\n";
    echo "Highest Semester GPA: " . number_format($highest_semester_gpa, 2) . "\n";
    echo "Lowest Semester GPA: " . number_format($lowest_semester_gpa, 2) . "\n";
    echo "Final CGPA: " . number_format($final_cgpa, 2) . "\n";
    echo "Total Credits Earned: " . number_format($total_credits_earned, 2) . "\n";
    
    // Calculate trend
    if ($total_semesters >= 2) {
        $recent_gpa = $trend_data[$total_semesters - 1]['semester_gpa'];
        $previous_gpa = $trend_data[$total_semesters - 2]['semester_gpa'];
        
        echo "\nPerformance Trend: ";
        if ($recent_gpa > $previous_gpa) {
            echo "IMPROVING ↑ (+" . number_format($recent_gpa - $previous_gpa, 2) . ")\n";
        } elseif ($recent_gpa < $previous_gpa) {
            echo "DECLINING ↓ (" . number_format($recent_gpa - $previous_gpa, 2) . ")\n";
        } else {
            echo "STABLE →\n";
        }
    }
}

echo "\n✓ GPA Trend Data Generated Successfully!\n";
