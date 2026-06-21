<?php 
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check columns available on users table
$columns = DB::getSchemaBuilder()->getColumnListing('users');
echo "Users columns: " . implode(', ', $columns) . "\n\n";

// Get all users with role info
$users = DB::table('users')
    ->where('status', 1)
    ->orderBy('id')
    ->get();

echo "=== ALL ACTIVE USERS ===\n";
foreach($users as $u) {
    $roles = DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $u->id)
        ->where('model_has_roles.model_type', 'LIKE', '%User%')
        ->pluck('roles.name')
        ->toArray();
    
    $roleStr = empty($roles) ? 'NO ROLES' : implode(', ', $roles);
    
    // Check if they teach any class routines
    $routineCount = DB::table('class_routines')
        ->where('teacher_id', $u->id)
        ->count();
    
    echo "ID={$u->id} | {$u->first_name} {$u->last_name} | email={$u->email}";
    if (isset($u->staff_type)) echo " | staff_type={$u->staff_type}";
    if (isset($u->designation)) echo " | designation={$u->designation}";
    if (isset($u->staff_id)) echo " | staff_id={$u->staff_id}";
    echo " | Roles: [{$roleStr}]";
    echo " | ClassRoutines: {$routineCount}";
    echo "\n";
}

echo "\n=== ROLES TABLE ===\n";
$roles = DB::table('roles')->get();
foreach($roles as $r) {
    echo "ID={$r->id} | {$r->name} | guard={$r->guard_name}\n";
}

echo "\n=== CURRENT model_has_roles ===\n";
$entries = DB::table('model_has_roles')->get();
foreach($entries as $e) {
    echo "role_id={$e->role_id} | model_type={$e->model_type} | model_id={$e->model_id}\n";
}
