<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$request = Illuminate\Http\Request::create('/');
$kernel->handle($request);

use App\Models\Faculty;
use App\Models\Program;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;

$sessionId = 2;
$semesterId = 1;

echo "=== TESTING STUDENT-LEVEL PASS/FAIL LOGIC ===" . PHP_EOL . PHP_EOL;

$activeFaculties = Faculty::where('status', '1')->orderBy('title', 'asc')->get();

foreach ($activeFaculties as $fac) {
    echo "--- Faculty: {$fac->shortcode} ---" . PHP_EOL;
    
    $facProgramIds = Program::where('faculty_id', $fac->id)
        ->where('status', '1')
        ->pluck('id')
        ->toArray();
    
    if (empty($facProgramIds)) continue;
    
    $enrollments = StudentEnroll::where('session_id', $sessionId)
        ->where('semester_id', $semesterId)
        ->whereIn('status', [1, 2])
        ->whereIn('program_id', $facProgramIds)
        ->with(['subjectMarks'])
        ->get();
        
    $studentsRegistered = $enrollments->count();
    $studentsExamined = 0;
    $studentsPassed = 0;
    $studentsFailed = 0;

    foreach ($enrollments as $enroll) {
        $marks = $enroll->subjectMarks;
        if ($marks->isEmpty()) {
            continue; // Not examined
        }
        
        $studentsExamined++;
        $failedCount = $marks->where('total_marks', '<', 50)->count();
        if ($failedCount > 0) {
            $studentsFailed++;
        } else {
            $studentsPassed++;
        }
    }
    
    $passRate = $studentsExamined > 0 ? round(($studentsPassed / $studentsExamined) * 100, 1) : 0;
    
    echo "  No Registered: {$studentsRegistered}" . PHP_EOL;
    echo "  No Examined: {$studentsExamined}" . PHP_EOL;
    echo "  No Passed: {$studentsPassed}" . PHP_EOL;
    echo "  No Failed: {$studentsFailed}" . PHP_EOL;
    echo "  % Passed: {$passRate}%" . PHP_EOL;
    echo PHP_EOL;
}
