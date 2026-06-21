<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "Assigning Catholic Students permissions...\n\n";

$role = Role::where('slug', 'super-admin')->orWhere('slug', 'admin')->first();

if ($role) {
    $permissions = Permission::whereIn('name', ['catholic-student-view', 'catholic-student-edit'])->get();
    
    foreach ($permissions as $permission) {
        if (!$role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
            echo "✓ Assigned: {$permission->name}\n";
        } else {
            echo "- Already has: {$permission->name}\n";
        }
    }
    
    echo "\nPermissions assigned to role: {$role->title}\n";
} else {
    echo "Role not found!\n";
}
