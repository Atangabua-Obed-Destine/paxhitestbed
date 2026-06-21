<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = DB::getSchemaBuilder()->getColumnListing('roles');
echo "Roles columns: " . implode(', ', $cols) . "\n\n";

$roles = DB::table('roles')->get();
foreach($roles as $r) {
    echo json_encode($r) . "\n";
}

// Check designations table
echo "\n=== DESIGNATIONS ===\n";
$dcols = DB::getSchemaBuilder()->getColumnListing('designations');
echo "Designation columns: " . implode(', ', $dcols) . "\n";
$designations = DB::table('designations')->get();
foreach($designations as $d) {
    echo json_encode($d) . "\n";
}

// Show which users have which designation_id
echo "\n=== USERS WITH DESIGNATIONS ===\n";
$users = DB::table('users')
    ->leftJoin('designations', 'designations.id', '=', 'users.designation_id')
    ->select('users.id', 'users.first_name', 'users.last_name', 'users.designation_id', 'designations.title as designation_title')
    ->where('users.status', 1)
    ->orderBy('users.id')
    ->get();
foreach($users as $u) {
    echo "ID={$u->id} | {$u->first_name} {$u->last_name} | designation_id={$u->designation_id} | designation={$u->designation_title}\n";
}
