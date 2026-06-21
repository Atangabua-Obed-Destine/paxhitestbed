<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create a dummy request
$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$kernel->bootstrap();

use App\Models\Subject;
use App\Models\StaffAssignment;
use App\Services\StaffAssignmentService;
use Illuminate\Support\Facades\DB;

echo "=== Testing Exam Marking Controller Fix ===\n\n";

// Test parameters (from the console screenshot)
$teacher_id = 3; // Staff ID 0003
$session = 1;    // OCTOBER-2025
$program = 32;   // HND ACCOUNTANCY
$subject_id = 5; // ACC11O1H (correct ID)

echo "Test Parameters:\n";
echo "- Staff ID: $teacher_id\n";
echo "- Session ID: $session\n";
echo "- Program ID: $program\n";
echo "- Subject ID: $subject_id\n\n";

// Check if staff has assignments
$hasAssignments = StaffAssignment::where('user_id', $teacher_id)->exists();
echo "Staff has assignments: " . ($hasAssignments ? "✅ YES" : "❌ NO") . "\n\n";

// Check the subject query (what loads in dropdown)
echo "=== Testing Subject Dropdown Query ===\n";
$subjects = Subject::where('status', '1');
$subjects->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $hasAssignments){
    if(isset($session)){
        $query->where('session_id', $session);
    }
    // Only filter by teacher_id if user has NO staff assignments
    if(!$hasAssignments){
        $query->where('teacher_id', $teacher_id);
    }
});
$subjects->with('programs')->whereHas('programs', function ($query) use ($program){
    $query->where('program_id', $program);
});

// Apply staff assignment filter
$subjects = StaffAssignmentService::filterCourses($subjects);

$subjectsList = $subjects->orderBy('code', 'asc')->get();
echo "Courses found for dropdown: " . $subjectsList->count() . "\n";
foreach($subjectsList as $subj) {
    echo "  - {$subj->code}: {$subj->title}\n";
}

echo "\n=== Testing Subject Access Check (firstOrFail) ===\n";
// Check if the selected subject is accessible
$subject_check = Subject::where('id', $subject_id);
$subject_check->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $hasAssignments){
    if(isset($session)){
        $query->where('session_id', $session);
    }
    // Only filter by teacher_id if user has NO staff assignments
    if(!$hasAssignments){
        $query->where('teacher_id', $teacher_id);
    }
});

try {
    $subject = $subject_check->first();
    if($subject) {
        echo "✅ SUCCESS: Staff can access subject #{$subject_id} ({$subject->code})\n";
        echo "Subject: {$subject->title}\n";
        
        // Check if staff is assigned to this course
        $isAssigned = DB::table('staff_assignments')
            ->where('user_id', $teacher_id)
            ->where('assignable_type', 'App\\Models\\Subject')
            ->where('assignable_id', $subject_id)
            ->exists();
        
        if($isAssigned) {
            echo "Staff is directly assigned to this course ✅\n";
        } else {
            echo "Staff is not directly assigned but can access via faculty/program assignments ✅\n";
        }
    } else {
        echo "❌ FAIL: Subject not found or not accessible\n";
    }
} catch(\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Testing Class Routines ===\n";
$classRoutines = DB::table('class_routines')
    ->where('subject_id', $subject_id)
    ->where('session_id', $session)
    ->get();

echo "Class routines for subject #{$subject_id}: " . $classRoutines->count() . "\n";
foreach($classRoutines as $routine) {
    echo "  - Class ID: {$routine->id}, Teacher ID: {$routine->teacher_id}\n";
}

echo "\n=== Summary ===\n";
if($hasAssignments && $subjectsList->count() > 0 && $subject) {
    echo "✅ ALL CHECKS PASSED\n";
    echo "Staff with assignments can now access exam marking for courses with class schedules\n";
    echo "Even if the course is taught by another teacher\n";
} else {
    echo "❌ SOME CHECKS FAILED\n";
    if(!$hasAssignments) echo "- Staff has no assignments\n";
    if($subjectsList->count() == 0) echo "- No subjects found for dropdown\n";
    if(!$subject) echo "- Subject access check failed\n";
}
