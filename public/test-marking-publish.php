<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SubjectMarking;
use App\Models\StudentEnroll;

echo "<h2>Subject Marking Status Check</h2>";
echo "<p>Subject ID: 15 (PHI1101 - The Human Person)</p>";
echo "<hr>";

$markings = SubjectMarking::where('subject_id', 15)
    ->with(['studentEnroll.student'])
    ->get();

if ($markings->count() == 0) {
    echo "<p style='color: red;'>No marking records found for this subject.</p>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr>
        <th>ID</th>
        <th>Student</th>
        <th>Total Marks</th>
        <th>Workflow State</th>
        <th>Publish Date</th>
        <th>Publish Time</th>
        <th>Is Published?</th>
        <th>Date Check</th>
    </tr>";
    
    foreach ($markings as $mark) {
        $student = $mark->studentEnroll->student ?? null;
        $studentName = $student ? $student->first_name . ' ' . $student->last_name : 'Unknown';
        
        $isPublished = $mark->workflow_state === 'published' ? 'YES' : 'NO';
        $publishDateStr = $mark->publish_date ? $mark->publish_date->format('Y-m-d') : 'NULL';
        $publishTimeStr = $mark->publish_time ? $mark->publish_time->format('H:i:s') : 'NULL';
        
        // Check date/time logic
        $dateCheck = 'N/A';
        if ($mark->workflow_state === 'published' && $mark->publish_date && $mark->publish_time) {
            $currentDate = date('Y-m-d');
            $currentTime = date('H:i:s');
            $markDate = $mark->publish_date->format('Y-m-d');
            $markTime = $mark->publish_time->format('H:i:s');
            
            if ($markDate < $currentDate) {
                $dateCheck = 'PAST DATE - SHOULD SHOW';
            } elseif ($markDate == $currentDate && $markTime <= $currentTime) {
                $dateCheck = 'TODAY & TIME PASSED - SHOULD SHOW';
            } else {
                $dateCheck = 'FUTURE - SHOULD NOT SHOW';
            }
        }
        
        echo "<tr>
            <td>{$mark->id}</td>
            <td>{$studentName}</td>
            <td>{$mark->total_marks}</td>
            <td style='color: " . ($mark->workflow_state === 'published' ? 'green' : 'orange') . ";'><strong>{$mark->workflow_state}</strong></td>
            <td>{$publishDateStr}</td>
            <td>{$publishTimeStr}</td>
            <td>{$isPublished}</td>
            <td style='color: " . (strpos($dateCheck, 'SHOULD SHOW') !== false ? 'green' : 'red') . ";'>{$dateCheck}</td>
        </tr>";
    }
    
    echo "</table>";
}

echo "<hr>";
echo "<h3>Current Server DateTime</h3>";
echo "<p>Date: " . date('Y-m-d') . "</p>";
echo "<p>Time: " . date('H:i:s') . "</p>";
?>
