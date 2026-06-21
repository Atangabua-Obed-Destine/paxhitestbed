<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\User::where('status', 1)->get();
echo "=== All Active Users and Their Roles ===\n";
echo str_pad('ID', 5) . str_pad('Staff ID', 12) . str_pad('Name', 35) . "Roles\n";
echo str_repeat('-', 90) . "\n";

foreach ($users as $u) {
    $roles = $u->roles->pluck('slug')->implode(', ');
    echo str_pad($u->id, 5) 
        . str_pad($u->staff_id ?? 'N/A', 12) 
        . str_pad($u->first_name . ' ' . $u->last_name, 35) 
        . ($roles ?: '** NO ROLES **') 
        . "\n";
}

// Check model_has_roles table
echo "\n=== model_has_roles Table ===\n";
$roleAssignments = \Illuminate\Support\Facades\DB::table('model_has_roles')->get();
echo "Total entries: " . $roleAssignments->count() . "\n";
foreach ($roleAssignments as $ra) {
    $role = \Illuminate\Support\Facades\DB::table('roles')->find($ra->role_id);
    $slug = $role ? $role->slug : '?';
    echo "  role_id: {$ra->role_id} ({$slug}) => model_id: {$ra->model_id} ({$ra->model_type})\n";
}

// Check available roles
echo "\n=== Available Roles ===\n";
$roles = \Illuminate\Support\Facades\DB::table('roles')->get();
foreach ($roles as $r) {
    echo "  [{$r->id}] {$r->slug} - {$r->name}\n";
}
