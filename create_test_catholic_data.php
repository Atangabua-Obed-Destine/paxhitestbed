<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentEnroll;

echo "Creating test Catholic student data...\n\n";

$enrollments = StudentEnroll::where('status', 1)->limit(5)->get();

$count = 0;
foreach ($enrollments as $enroll) {
    $enroll->religion = 1; // Catholic
    $enroll->is_catholic_baptised = $count < 3; // First 3 baptised
    $enroll->is_confirmed = $count < 2; // First 2 confirmed
    $enroll->has_first_communion = $count < 4; // First 4 have communion
    $enroll->save();
    
    $count++;
    echo "✓ Updated enrollment ID: {$enroll->id}\n";
}

echo "\nTest data created successfully!\n";
echo "Total Catholic students: $count\n";
