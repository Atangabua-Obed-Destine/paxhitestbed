<?php

/**
 * Assign Program Semester Fee permissions to Admin and Accountant roles
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "\n========================================\n";
echo "Assigning Permissions to Roles\n";
echo "========================================\n\n";

$permissionNames = [
    'program-semester-fee-view',
    'program-semester-fee-create',
    'program-semester-fee-edit',
    'program-semester-fee-delete',
];

// Get permissions
$permissions = Permission::whereIn('name', $permissionNames)->get();

if ($permissions->count() !== count($permissionNames)) {
    echo "✗ Not all permissions found. Run test_program_semester_fees.php first.\n\n";
    exit(1);
}

// Assign to Admin role
$adminRole = Role::where('name', 'Admin')->first();
if ($adminRole) {
    foreach ($permissions as $permission) {
        if (!$adminRole->hasPermissionTo($permission)) {
            $adminRole->givePermissionTo($permission);
            echo "✓ Assigned '{$permission->name}' to Admin\n";
        } else {
            echo "→ Admin already has '{$permission->name}'\n";
        }
    }
} else {
    echo "✗ Admin role not found\n";
}

echo "\n";

// Assign to Accountant role (if exists)
$accountantRole = Role::where('name', 'Accountant')->first();
if ($accountantRole) {
    foreach ($permissions as $permission) {
        if (!$accountantRole->hasPermissionTo($permission)) {
            $accountantRole->givePermissionTo($permission);
            echo "✓ Assigned '{$permission->name}' to Accountant\n";
        } else {
            echo "→ Accountant already has '{$permission->name}'\n";
        }
    }
} else {
    echo "→ Accountant role not found (optional)\n";
}

echo "\n✓ Permission assignment completed!\n\n";
