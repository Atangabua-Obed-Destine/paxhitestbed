<?php
echo "<pre>";
echo "Current Date/Time: " . date('Y-m-d H:i:s') . "\n";
echo "Current Date: " . date('Y-m-d') . "\n";
echo "Current Time: " . date('H:i:s') . "\n\n";

// Simulate the mark data
$publish_date = '2025-10-30 00:00:00';
$publish_time = '2025-10-30 05:18:00';

echo "Publish Date: $publish_date\n";
echo "Publish Time: $publish_time\n\n";

$date_only = date('Y-m-d', strtotime($publish_date));
$time_only = date('H:i:s', strtotime($publish_time));

echo "Extracted Date: $date_only\n";
echo "Extracted Time: $time_only\n\n";

$current_date = date('Y-m-d');
$current_time = date('H:i:s');

echo "Date Match: " . ($date_only == $current_date ? 'YES' : 'NO') . "\n";
echo "Time Check: " . ($time_only <= $current_time ? 'YES (visible)' : 'NO (not yet visible)') . "\n";

$condition = ($date_only == $current_date && $time_only <= $current_time) || $date_only < $current_date;
echo "\nFinal Condition Result: " . ($condition ? 'TRUE (should show)' : 'FALSE (should NOT show)') . "\n";

echo "</pre>";
