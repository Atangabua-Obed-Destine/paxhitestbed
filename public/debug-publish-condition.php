<?php
$host = 'localhost';
$dbname = 'paxhitest';
$username = 'root';
$password = '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

$stmt = $pdo->prepare("SELECT publish_date, publish_time FROM subject_markings WHERE student_enroll_id = 27 AND subject_id = 27");
$stmt->execute();
$mark = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<pre>";
echo "Publish Date Raw: " . var_export($mark['publish_date'], true) . "\n";
echo "Publish Time Raw: " . var_export($mark['publish_time'], true) . "\n";
echo "Publish Date Type: " . gettype($mark['publish_date']) . "\n";
echo "Publish Time Type: " . gettype($mark['publish_time']) . "\n\n";

// The condition from transcript view
$publish_date = $mark['publish_date'];
$publish_time = $mark['publish_time'];

echo "Testing the condition:\n";
echo "date('Y-m-d', strtotime('$publish_date')): " . date('Y-m-d', strtotime($publish_date)) . "\n";
echo "date('Y-m-d'): " . date('Y-m-d') . "\n";
echo "Are they equal? " . (date('Y-m-d', strtotime($publish_date)) == date('Y-m-d') ? 'YES' : 'NO') . "\n\n";

echo "date('H:i:s', strtotime('$publish_time')): " . date('H:i:s', strtotime($publish_time)) . "\n";
echo "date('H:i:s'): " . date('H:i:s') . "\n";
echo "Is publish_time <= current_time? " . (date('H:i:s', strtotime($publish_time)) <= date('H:i:s') ? 'YES' : 'NO') . "\n\n";

$condition_same_day = (date('Y-m-d', strtotime($publish_date)) == date('Y-m-d') && date('H:i:s', strtotime($publish_time)) <= date('H:i:s'));
$condition_past_day = (date('Y-m-d', strtotime($publish_date)) < date('Y-m-d'));
$final_condition = $condition_same_day || $condition_past_day;

echo "Condition (same day && time passed): " . ($condition_same_day ? 'TRUE' : 'FALSE') . "\n";
echo "Condition (past day): " . ($condition_past_day ? 'TRUE' : 'FALSE') . "\n";
echo "FINAL CONDITION: " . ($final_condition ? 'TRUE (should show)' : 'FALSE (should NOT show)') . "\n";

echo "</pre>";
