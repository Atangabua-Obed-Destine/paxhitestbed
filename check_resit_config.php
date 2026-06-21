<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Resit Configuration ===\n\n";

// Check resit semesters
$resitSemesters = \App\Models\Semester::where('is_resit', 1)->get();
echo "Total Resit Semesters: " . $resitSemesters->count() . "\n";

if ($resitSemesters->count() > 0) {
    echo "\nResit Semesters:\n";
    foreach ($resitSemesters as $semester) {
        echo "  - ID: {$semester->id}, Title: {$semester->title}, Status: " . ($semester->status ? 'Active' : 'Inactive') . "\n";
        
        // Check programs linked to this semester
        $programs = $semester->programs()->count();
        echo "    Programs linked: {$programs}\n";
    }
} else {
    echo "\n⚠️  WARNING: No resit semesters configured!\n";
    echo "   You need to create a resit semester in the admin panel.\n";
}

echo "\n=== Checking Resit Fee Category ===\n\n";

$resitCategory = \App\Models\FeesCategory::where('is_resit', 1)->where('status', 1)->first();
if ($resitCategory) {
    echo "✓ Resit Fee Category: {$resitCategory->title} (ID: {$resitCategory->id})\n";
} else {
    echo "⚠️  WARNING: No resit fee category configured!\n";
}

echo "\n=== Checking Recent Resit Requests ===\n\n";

$recentRequests = \App\Models\ResitRequest::orderBy('id', 'desc')->take(5)->get();
echo "Recent Resit Requests:\n";
foreach ($recentRequests as $req) {
    echo "  - Request #{$req->id}: State={$req->workflow_state}, Payment={$req->payment_status}\n";
    echo "    Resit Session: " . ($req->resit_session_id ?? 'Not set') . ", Resit Semester: " . ($req->resit_semester_id ?? 'Not set') . "\n";
}

echo "\n=== Done ===\n";
