<?php

/**
 * Script to add Student Form A2 View permission
 * Run: php assign_student_form_a2_permission.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "=== Adding Student Form A2 Permission ===\n\n";

// Create the permission
$permissionName = 'student-form-a2-view';
$permission = Permission::where('name', $permissionName)->first();

if (!$permission) {
    $permission = Permission::create([
        'name' => $permissionName,
        'guard_name' => 'web',
        'group' => 'Student',
        'title' => 'View Student Form A2',
    ]);
    echo "✓ Created permission: {$permissionName}\n";
} else {
    // Update group and title if exists
    $permission->group = 'Student';
    $permission->title = 'View Student Form A2';
    $permission->save();
    echo "✓ Permission already exists: {$permissionName} (updated group/title)\n";
}

// Assign to roles: Super Admin, Admin
$rolesToAssign = ['Super Admin', 'Admin'];

foreach ($rolesToAssign as $roleName) {
    $role = Role::where('name', $roleName)->first();
    if ($role) {
        if (!$role->hasPermissionTo($permissionName)) {
            $role->givePermissionTo($permissionName);
            echo "✓ Assigned '{$permissionName}' to '{$roleName}' role\n";
        } else {
            echo "- '{$roleName}' role already has '{$permissionName}'\n";
        }
    } else {
        echo "✗ Role '{$roleName}' not found\n";
    }
}

echo "\n=== Done! ===\n";
echo "You can now access: /admin/admission/student-form-a2\n";
