<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Student;
use App\Models\SubjectMarking;

echo "<h2>Transcript Display Logic Test</h2>";
echo "<hr>";

// Get a student (you can change the ID)
$studentId = "PAX25FBF001"; // Malia Anitoh Atangabua from your screenshot
$student = Student::where('student_id', $studentId)->first();

if (!$student) {
    echo "<p style='color: red;'>Student $studentId not found. Trying first available student...</p>";
    $student = Student::with('studentEnrolls.subjectMarks')->first();
}

if (!$student) {
    echo "<p style='color: red;'>No students found in database!</p>";
    exit;
}

echo "<h3>Student: {$student->first_name} {$student->last_name} (#{$student->student_id})</h3>";
echo "<hr>";

$student->load([
    'studentEnrolls.session',
    'studentEnrolls.semester',
    'studentEnrolls.section',
    'studentEnrolls.subjects',
    'studentEnrolls.subjectMarks.subject'
]);

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr>
    <th>Subject</th>
    <th>Total Marks</th>
    <th>Workflow State</th>
    <th>Publish Date</th>
    <th>Publish Time</th>
    <th>Will Display?</th>
    <th>Reason</th>
</tr>";

$displayCount = 0;
$hiddenCount = 0;

foreach ($student->studentEnrolls as $enroll) {
    if (!isset($enroll->subjectMarks)) {
        continue;
    }
    
    foreach ($enroll->subjectMarks as $mark) {
        $subject = $mark->subject;
        $subjectName = $subject ? $subject->title : 'Unknown';
        
        $willDisplay = false;
        $reason = '';
        
        // Replicate the exact logic from transcript view
        if ($mark->workflow_state === 'published') {
            if ($mark->publish_date && $mark->publish_time) {
                $markDate = date('Y-m-d', strtotime($mark->publish_date));
                $markTime = date('H:i:s', strtotime($mark->publish_time));
                $currentDate = date('Y-m-d');
                $currentTime = date('H:i:s');
                
                // The exact condition from the view
                if (($markDate == $currentDate && $markTime <= $currentTime) || $markDate < $currentDate) {
                    $willDisplay = true;
                    $reason = 'Published & Date/Time OK';
                } else {
                    $reason = 'Published but date/time in future';
                }
            } else {
                $reason = 'Published but no publish_date/time';
            }
        } else {
            $reason = 'Not published (state: ' . ($mark->workflow_state ?: 'NULL') . ')';
        }
        
        if ($willDisplay) {
            $displayCount++;
        } else {
            $hiddenCount++;
        }
        
        $color = $willDisplay ? 'green' : 'red';
        $displayText = $willDisplay ? 'YES ✓' : 'NO ✗';
        
        echo "<tr style='background-color: " . ($willDisplay ? '#e8f5e9' : '#ffebee') . ";'>
            <td>{$subjectName}</td>
            <td>{$mark->total_marks}</td>
            <td>{$mark->workflow_state}</td>
            <td>" . ($mark->publish_date ? date('Y-m-d', strtotime($mark->publish_date)) : 'NULL') . "</td>
            <td>" . ($mark->publish_time ? date('H:i:s', strtotime($mark->publish_time)) : 'NULL') . "</td>
            <td style='color: {$color}; font-weight: bold;'>{$displayText}</td>
            <td>{$reason}</td>
        </tr>";
    }
}

echo "</table>";

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p><strong>Marks that will display:</strong> <span style='color: green;'>{$displayCount}</span></p>";
echo "<p><strong>Marks hidden:</strong> <span style='color: red;'>{$hiddenCount}</span></p>";

echo "<hr>";
echo "<h3>Current Server DateTime</h3>";
echo "<p>Date: " . date('Y-m-d') . " | Time: " . date('H:i:s') . "</p>";
?>
