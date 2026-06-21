<?php

/*
 * Test Semester Type Configuration
 * Demonstrates how fees can be configured by semester type
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Semester;
use App\Models\Program;
use App\Models\EnrollSubject;

echo "========================================\n";
echo "Semester Type Configuration Demo\n";
echo "========================================\n\n";

$programId = $argv[1] ?? 5;

echo "Testing with Program ID: {$programId}\n\n";

// Get all semesters with enrolled courses for this program
$semesters = Semester::where('is_resit', 0)
    ->where('status', 1)
    ->whereHas('enrollSubjects', function($query) use ($programId) {
        $query->where('program_id', $programId);
    })
    ->orderBy('semester_type', 'asc')
    ->orderBy('year', 'asc')
    ->get();

if ($semesters->isEmpty()) {
    echo "❌ No semesters found with enrolled courses for this program\n";
    exit;
}

echo "Found {$semesters->count()} semester(s) with enrolled courses:\n\n";

// Group by semester type
$grouped = $semesters->groupBy('semester_type');

foreach ($grouped as $type => $typeSemesters) {
    echo str_repeat("=", 60) . "\n";
    echo "SEMESTER TYPE {$type}\n";
    echo str_repeat("=", 60) . "\n";
    echo "Total semesters: {$typeSemesters->count()}\n\n";
    
    echo "Semesters:\n";
    foreach ($typeSemesters as $sem) {
        echo "  • {$sem->title} (Year {$sem->year}, ID: {$sem->id})\n";
    }
    
    echo "\n";
    echo "CONFIGURATION IMPACT:\n";
    echo "When you configure fees for Semester Type {$type}:\n";
    echo "  → Fees will be set for ALL {$typeSemesters->count()} semester(s) listed above\n";
    echo "  → One configuration applies to multiple semesters\n";
    echo "  → Saves time and ensures consistency\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "HOW TO USE\n";
echo str_repeat("=", 60) . "\n\n";

echo "1. Go to: http://localhost/paxhitest/admin/program-semester-fee/create\n\n";

echo "2. Select:\n";
echo "   - Faculty\n";
echo "   - Program\n";
echo "   - Semester Type (e.g., Type 1 for all First Semesters)\n\n";

echo "3. The system will show you which semesters will be configured\n\n";

echo "4. Configure fees (amount, due days, fines) once\n\n";

echo "5. Click Save\n\n";

echo "6. Result: Fees configured for ALL semesters of that type! ✓\n\n";

echo str_repeat("=", 60) . "\n";
echo "EXAMPLES\n";
echo str_repeat("=", 60) . "\n\n";

echo "Example 1: Configure Type 1 (First Semesters)\n";
echo "  Input:\n";
echo "    - Semester Type: 1\n";
echo "    - First Installment: 325,000 (30 days, 2% fine)\n";
echo "  Result:\n";
foreach ($grouped->get(1, collect()) as $sem) {
    echo "    ✓ {$sem->title} gets: 325,000 (30 days, 2% fine)\n";
}
echo "\n";

echo "Example 2: Configure Type 2 (Second Semesters)\n";
echo "  Input:\n";
echo "    - Semester Type: 2\n";
echo "    - Second Installment: 40,000 (60 days, 5,000 fixed fine)\n";
echo "  Result:\n";
foreach ($grouped->get(2, collect()) as $sem) {
    echo "    ✓ {$sem->title} gets: 40,000 (60 days, 5,000 fixed fine)\n";
}
echo "\n";

echo str_repeat("=", 60) . "\n";
echo "BENEFITS\n";
echo str_repeat("=", 60) . "\n";
echo "✓ Configure once, apply to multiple semesters\n";
echo "✓ Ensures consistency across all years\n";
echo "✓ Saves time (no need to configure each semester individually)\n";
echo "✓ Easy to update (change one type, updates all)\n";
echo "✓ Less chance of errors or missed semesters\n";
echo str_repeat("=", 60) . "\n";
