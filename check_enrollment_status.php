<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ENROLLMENT STATUS VALUES IN SYSTEM ===\n\n";

// Get distinct status values
$statuses = DB::table('student_enrolls')
    ->select('status')
    ->distinct()
    ->orderBy('status')
    ->get();

echo "Distinct status values found:\n";
foreach($statuses as $s) {
    echo "  - " . $s->status . "\n";
}

// Get counts per status
$counts = DB::table('student_enrolls')
    ->select('status', DB::raw('count(*) as total'))
    ->groupBy('status')
    ->orderBy('status')
    ->get();

echo "\nEnrollment counts by status:\n";
foreach($counts as $c) {
    $statusLabel = $c->status == '1' ? 'ACTIVE' : 'INACTIVE';
    echo "  Status {$c->status} ({$statusLabel}): {$c->total} enrollments\n";
}

// Check if there are any other status-related fields
echo "\n=== CHECKING FOR STATUS-RELATED COLUMNS ===\n";
$columns = DB::select("DESCRIBE student_enrolls");
echo "\nAll columns in student_enrolls table:\n";
foreach($columns as $col) {
    if (stripos($col->Field, 'status') !== false || stripos($col->Field, 'active') !== false) {
        echo "  - {$col->Field} ({$col->Type}) - Nullable: {$col->Null} - Default: {$col->Default}\n";
    }
}

// Sample some enrollments to see the data
echo "\n=== SAMPLE ENROLLMENTS ===\n";
$samples = DB::table('student_enrolls')
    ->select('id', 'matricule', 'status', 'session_id', 'semester_id')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

foreach($samples as $sample) {
    echo "ID: {$sample->id}, Matricule: {$sample->matricule}, Status: {$sample->status}\n";
}
