<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Start session
session_start();

echo "=== SESSION DEBUG ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Selected Enrollment ID from Laravel session: " . session('selected_enrollment_id') . "\n";
echo "Selected Enrollment ID from PHP session: " . ($_SESSION['selected_enrollment_id'] ?? 'not set') . "\n\n";

echo "All session data:\n";
print_r($_SESSION);
