<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StaffAssignment;
use App\Services\StaffAssignmentService;
use App\User;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Subject;

echo "=== Staff Assignment Restriction Test ===\n\n";

// Get staff with ID 0003
$staff = User::where('staff_id', 'LIKE', '%0003%')->first();

if (!$staff) {
    echo "❌ Staff with ID 0003 not found!\n";
    exit;
}

echo "✓ Staff Found: {$staff->first_name} {$staff->last_name} (ID: {$staff->id})\n";
echo "  Roles: " . $staff->roles->pluck('name')->implode(', ') . "\n\n";

// Check assignments
$assignments = StaffAssignment::where('user_id', $staff->id)->get();
echo "Total Assignments: " . $assignments->count() . "\n";

if ($assignments->count() > 0) {
    echo "\nAssignments Details:\n";
    foreach ($assignments as $assignment) {
        echo "  - Type: {$assignment->assignable_type}\n";
        echo "    ID: {$assignment->assignable_id}\n";
        echo "    Name: {$assignment->assignable->title}\n\n";
    }
}

// Test the service
$service = new StaffAssignmentService();

echo "\n=== Testing StaffAssignmentService ===\n\n";

// Check if Super Admin
$isSuperAdmin = $service->isSuperAdmin($staff->id);
echo "Is Super Admin: " . ($isSuperAdmin ? 'YES ⚠️' : 'NO') . "\n";

// Check if staff has assignments
$hasAssignments = StaffAssignment::hasAnyAssignments($staff->id);
echo "Has Assignments: " . ($hasAssignments ? 'YES' : 'NO') . "\n";

// Get accessible faculty IDs
$facultyIds = $service->getAccessibleFacultyIds($staff->id);
echo "Accessible Faculty IDs: " . json_encode($facultyIds) . "\n";

// Get accessible program IDs
$programIds = $service->getAccessibleProgramIds($staff->id);
echo "Accessible Program IDs: " . json_encode($programIds) . "\n";

// Test filtering
echo "\n=== Testing Filter Methods ===\n\n";

// All faculties (unfiltered)
$allFaculties = Faculty::where('status', 1)->count();
echo "Total Active Faculties: {$allFaculties}\n";

// Filtered faculties
$filteredFaculties = $service->filterFaculties(Faculty::where('status', 1), $staff->id)->get();
echo "Filtered Faculties for Staff: {$filteredFaculties->count()}\n";
foreach ($filteredFaculties as $faculty) {
    echo "  - {$faculty->title}\n";
}

// All programs (unfiltered)
$allPrograms = Program::where('status', 1)->count();
echo "\nTotal Active Programs: {$allPrograms}\n";

// Filtered programs
$filteredPrograms = $service->filterPrograms(Program::where('status', 1), $staff->id)->get();
echo "Filtered Programs for Staff: {$filteredPrograms->count()}\n";
foreach ($filteredPrograms as $program) {
    echo "  - {$program->title} (Faculty: {$program->faculty->title})\n";
}

// All courses (unfiltered)
$allCourses = Subject::where('status', 1)->count();
echo "\nTotal Active Courses: {$allCourses}\n";

// Filtered courses
$filteredCourses = $service->filterCourses(Subject::where('status', 1), $staff->id)->get();
echo "Filtered Courses for Staff: {$filteredCourses->count()}\n";
foreach ($filteredCourses as $course) {
    echo "  - {$course->title} ({$course->code})\n";
}

echo "\n=== End of Test ===\n";
