<?php
/**
 * Assign New Permissions to Super Admin
 * 
 * This script assigns all newly created permissions to the Super Admin role.
 * 
 * Run: php assign_new_permissions.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "=== Assigning Permissions to Super Admin ===\n\n";

// Find Super Admin role
$superAdmin = Role::where('name', 'Super Admin')->first();

if (!$superAdmin) {
    echo "ERROR: Super Admin role not found!\n";
    echo "Available roles:\n";
    Role::all()->each(function($role) {
        echo "  - {$role->name}\n";
    });
    exit(1);
}

echo "Found Super Admin role (ID: {$superAdmin->id})\n\n";

// List of permission groups to assign
$permissionGroups = [
    'Security',
    'Class Session',
    'Class Hub',
    'Attendance Setting',
    'Form A3',
];

// Get all permissions from these groups
$permissions = Permission::whereIn('group', $permissionGroups)->get();

echo "Found " . $permissions->count() . " permissions in target groups:\n";

foreach ($permissionGroups as $group) {
    $groupPerms = $permissions->where('group', $group);
    echo "\n{$group} ({$groupPerms->count()}):\n";
    foreach ($groupPerms as $perm) {
        echo "  - {$perm->name}\n";
    }
}

// Assign all permissions
echo "\n=== Assigning Permissions ===\n";

$assigned = 0;
$alreadyHas = 0;

foreach ($permissions as $permission) {
    if ($superAdmin->hasPermissionTo($permission->name)) {
        echo "[HAS]      {$permission->name}\n";
        $alreadyHas++;
    } else {
        $superAdmin->givePermissionTo($permission->name);
        echo "[ASSIGNED] {$permission->name}\n";
        $assigned++;
    }
}

echo "\n=== Summary ===\n";
echo "Newly Assigned: {$assigned}\n";
echo "Already Had: {$alreadyHas}\n";
echo "Total: " . $permissions->count() . "\n";

// Also assign any permissions that might have been missed
echo "\n=== Checking for Unassigned Permissions ===\n";

$allPermissions = Permission::all();
$superAdminPermissions = $superAdmin->permissions->pluck('name')->toArray();

$unassigned = $allPermissions->filter(function($p) use ($superAdminPermissions) {
    return !in_array($p->name, $superAdminPermissions);
});

if ($unassigned->count() > 0) {
    echo "Found {$unassigned->count()} unassigned permissions:\n";
    foreach ($unassigned->take(20) as $perm) {
        echo "  - {$perm->name} ({$perm->group})\n";
    }
    if ($unassigned->count() > 20) {
        echo "  ... and " . ($unassigned->count() - 20) . " more\n";
    }
    
    echo "\nWould you like to assign all permissions to Super Admin? (Run with --all flag)\n";
    
    if (in_array('--all', $argv ?? [])) {
        echo "\nAssigning all permissions...\n";
        $superAdmin->syncPermissions($allPermissions);
        echo "Done! Super Admin now has all " . $allPermissions->count() . " permissions.\n";
    }
} else {
    echo "Super Admin has all permissions.\n";
}

echo "\n=== Done! ===\n";
