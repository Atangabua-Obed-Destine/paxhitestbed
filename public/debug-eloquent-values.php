<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SubjectMarking;

echo "<pre>";
$mark = SubjectMarking::where('student_enroll_id', 27)
    ->where('subject_id', 27)
    ->first();

echo "=== ELOQUENT MODEL VALUES ===\n";
echo "publish_date: " . var_export($mark->publish_date, true) . "\n";
echo "publish_time: " . var_export($mark->publish_time, true) . "\n";
echo "Type of publish_date: " . gettype($mark->publish_date) . "\n";
echo "Type of publish_time: " . gettype($mark->publish_time) . "\n\n";

if(is_object($mark->publish_date)) {
    echo "publish_date is Carbon instance\n";
    echo "publish_date->format('Y-m-d'): " . $mark->publish_date->format('Y-m-d') . "\n";
}

if(is_object($mark->publish_time)) {
    echo "publish_time is Carbon instance\n";
    echo "publish_time->format('H:i:s'): " . $mark->publish_time->format('H:i:s') . "\n";
}

echo "\n=== TESTING TRANSCRIPT CONDITION ===\n";
echo "strtotime(publish_date): " . strtotime($mark->publish_date) . "\n";
echo "strtotime(publish_time): " . strtotime($mark->publish_time) . "\n";
echo "date('Y-m-d', strtotime(publish_date)): " . date('Y-m-d', strtotime($mark->publish_date)) . "\n";
echo "date('H:i:s', strtotime(publish_time)): " . date('H:i:s', strtotime($mark->publish_time)) . "\n";

echo "</pre>";
