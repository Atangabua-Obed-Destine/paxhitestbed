<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\StaffAssignmentService;
use App\Models\Subject;
use App\User;

// Login as staff ID 0003
$staff = User::where('staff_id', 'LIKE', '%0003%')->first();
if ($staff) {
    Auth::login($staff);
}

echo "<!DOCTYPE html>";
echo "<html><head><title>Course Filter Debug</title>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";
echo "</head><body>";

echo "<h1>🔍 Course Filter Debug Tool</h1>";

if (!$staff) {
    echo "<p class='error'>❌ Staff with ID 0003 not found!</p>";
    exit;
}

echo "<p class='success'>✅ Logged in as: {$staff->first_name} {$staff->last_name} (ID: {$staff->id})</p>";

// Get the parameters (simulate AJAX request)
$programId = 32; // HND ACCOUNTANCY
$sessionId = 1;

echo "<h2>📋 Test Parameters</h2>";
echo "<ul>";
echo "<li><strong>Program ID:</strong> {$programId} (HND ACCOUNTANCY)</li>";
echo "<li><strong>Session ID:</strong> {$sessionId}</li>";
echo "<li><strong>Teacher ID:</strong> {$staff->id}</li>";
echo "</ul>";

// Check staff assignments
$hasAssignments = \App\Models\StaffAssignment::where('user_id', $staff->id)->exists();
echo "<h2>🔐 Staff Assignments</h2>";
echo "<p>Has Staff Assignments: <strong>" . ($hasAssignments ? 'YES' : 'NO') . "</strong></p>";

if ($hasAssignments) {
    $assignments = \App\Models\StaffAssignment::where('user_id', $staff->id)->with('assignable')->get();
    echo "<ul>";
    foreach ($assignments as $assignment) {
        $type = class_basename($assignment->assignable_type);
        echo "<li>{$type}: {$assignment->assignable->title}</li>";
    }
    echo "</ul>";
}

// Check if user is super admin
$user = User::where('id', $staff->id)->where('status', '1');
$user->with('roles')->whereHas('roles', function ($query){
    $query->where('slug', 'super-admin');
});
$superAdmin = $user->first();

echo "<p>Is Super Admin: <strong>" . ($superAdmin ? 'YES' : 'NO') . "</strong></p>";

// Simulate the exact query from filterTecherSubject
echo "<h2>🔍 Query Simulation</h2>";

try {
    $rows = Subject::where('status', '1');
    
    echo "<p class='info'>Step 1: Base query (status = 1)</p>";
    $step1Count = Subject::where('status', '1')->count();
    echo "<p>Total active subjects: <strong>{$step1Count}</strong></p>";
    
    // Only filter by class routines if user is NOT restricted by staff assignments
    if (!$hasAssignments) {
        echo "<p class='info'>Step 2: Filtering by class routines (NO staff assignments)</p>";
        $rows->with('classes')->whereHas('classes', function ($query) use ($staff, $sessionId, $superAdmin){
            if(isset($sessionId)){
                $query->where('session_id', $sessionId);
            }
            if(!isset($superAdmin)){
                $query->where('teacher_id', $staff->id);
            }
        });
        $step2Count = $rows->count();
        echo "<p>After class routine filter: <strong>{$step2Count}</strong></p>";
    } else {
        echo "<p class='success'>Step 2: SKIPPED class routine filter (staff has assignments)</p>";
    }
    
    echo "<p class='info'>Step 3: Filtering by program</p>";
    $rows->with('programs')->whereHas('programs', function ($query) use ($programId){
        $query->where('program_id', $programId);
    });
    $step3Count = $rows->count();
    echo "<p>After program filter: <strong>{$step3Count}</strong></p>";
    
    echo "<p class='info'>Step 4: Applying staff assignment filter</p>";
    $rows = StaffAssignmentService::filterCourses($rows, $staff->id);
    
    $subjects = $rows->orderBy('code', 'asc')->get();
    $finalCount = $subjects->count();
    echo "<p>After staff assignment filter: <strong>{$finalCount}</strong></p>";
    
    if ($finalCount > 0) {
        echo "<h2 class='success'>✅ SUCCESS! Found {$finalCount} courses</h2>";
        echo "<h3>Courses List:</h3>";
        echo "<ol>";
        foreach ($subjects as $subject) {
            echo "<li>[{$subject->code}] {$subject->title}</li>";
        }
        echo "</ol>";
        
        echo "<h3>JSON Response (what AJAX receives):</h3>";
        echo "<pre>" . json_encode($subjects->map(function($s) {
            return [
                'id' => $s->id,
                'code' => $s->code,
                'title' => $s->title
            ];
        })->toArray(), JSON_PRETTY_PRINT) . "</pre>";
        
    } else {
        echo "<h2 class='error'>❌ NO COURSES FOUND</h2>";
        echo "<p>Possible issues:</p>";
        echo "<ul>";
        echo "<li>No subjects are linked to program ID {$programId} in program_subject table</li>";
        echo "<li>All subjects for this program are inactive (status = 0)</li>";
        echo "<li>Staff assignment filter is too restrictive</li>";
        echo "</ul>";
    }
    
} catch (\Exception $e) {
    echo "<h2 class='error'>❌ ERROR</h2>";
    echo "<p><strong>Error Message:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<h2>🔗 Test The Real AJAX Endpoint</h2>";
echo "<p>Open browser console and paste this:</p>";
echo "<pre>";
echo "$.ajax({\n";
echo "  type: 'POST',\n";
echo "  url: '" . url('/filter-techer-subject') . "',\n";
echo "  data: {\n";
echo "    _token: '" . csrf_token() . "',\n";
echo "    session: 1,\n";
echo "    program: 32\n";
echo "  },\n";
echo "  success: function(response) {\n";
echo "    console.log('Courses found:', response.length);\n";
echo "    console.log(response);\n";
echo "  },\n";
echo "  error: function(xhr) {\n";
echo "    console.error('Error:', xhr.responseText);\n";
echo "  }\n";
echo "});\n";
echo "</pre>";

echo "<p><a href='http://localhost/paxhitest/admin/subject-marking' style='display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin-top:20px;'>← Back to Course Mark Ledger</a></p>";

echo "</body></html>";
