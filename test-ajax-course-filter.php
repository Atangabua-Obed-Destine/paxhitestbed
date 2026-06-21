<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\StaffAssignmentService;
use App\Models\Subject;
use App\User;

echo "=== Testing AJAX Course Filtering ===\n\n";

// Get staff with ID 0003
$staff = User::where('staff_id', 'LIKE', '%0003%')->first();

if (!$staff) {
    echo "❌ Staff with ID 0003 not found!\n";
    exit;
}

echo "✓ Staff: {$staff->first_name} {$staff->last_name} (ID: {$staff->id})\n";

// Simulate what the AJAX call does
echo "\n=== Simulating filterTecherSubject AJAX ===\n";

// This is what happens when session dropdown changes
$programId = 32; // HND ACCOUNTANCY
$sessionId = 1; // Example session

echo "Input Parameters:\n";
echo "  - Program ID: {$programId}\n";
echo "  - Session ID: {$sessionId}\n\n";

// Simulate the query that filterTecherSubject builds
$rows = Subject::where('status', '1');
$rows->with('programs')->whereHas('programs', function ($query) use ($programId){
    $query->where('program_id', $programId);
});

echo "Before Staff Filter:\n";
$beforeFilter = clone $rows;
$beforeCount = $beforeFilter->count();
echo "  - Total subjects: {$beforeCount}\n";

// Apply staff assignment filter (this is what we just added)
$rows = StaffAssignmentService::filterCourses($rows, $staff->id);

$subjects = $rows->orderBy('code', 'asc')->get();

echo "\nAfter Staff Filter:\n";
echo "  - Filtered subjects: " . $subjects->count() . "\n\n";

if ($subjects->count() > 0) {
    echo "✅ SUCCESS! Courses are being filtered.\n\n";
    echo "First 10 courses that should appear:\n";
    foreach ($subjects->take(10) as $subject) {
        echo "  - [{$subject->code}] {$subject->title}\n";
    }
} else {
    echo "❌ ERROR! No courses found after filtering.\n";
    echo "This might mean:\n";
    echo "  1. The staff has no access to this program\n";
    echo "  2. There are no subjects in the program\n";
    echo "  3. The staff needs class routines assigned\n";
}

echo "\n=== Testing filterSubject (direct program filter) ===\n";

// Test the direct program-to-subject filter
$directRows = Subject::where('status', 1);
$directRows->with('programs')->whereHas('programs', function ($query) use ($programId){
    $query->where('program_id', $programId);
});
$directRows = StaffAssignmentService::filterCourses($directRows, $staff->id);
$directSubjects = $directRows->orderBy('code', 'asc')->get();

echo "Direct filter (filterSubject route):\n";
echo "  - Total subjects: " . $directSubjects->count() . "\n";

if ($directSubjects->count() > 0) {
    echo "✅ This route would work!\n";
} else {
    echo "❌ This route has issues too!\n";
}

echo "\n=== Recommendation ===\n";
if ($subjects->count() > 0 || $directSubjects->count() > 0) {
    echo "✅ The filtering is working in code.\n";
    echo "If courses still don't show in browser:\n";
    echo "  1. Clear browser cache (Ctrl+Shift+R)\n";
    echo "  2. Check browser console for JavaScript errors\n";
    echo "  3. Verify you selected the Session dropdown\n";
    echo "  4. Make sure you're logged in as staff ID 0003\n";
} else {
    echo "⚠️  No courses found. Possible reasons:\n";
    echo "  1. Staff needs to be assigned to class routines first\n";
    echo "  2. No subjects exist for this program\n";
    echo "  3. Subjects are not linked to the program in program_subject table\n";
}

echo "\n";
