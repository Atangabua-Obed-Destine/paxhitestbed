<?php

/**
 * Assign Senate Deliberation Permissions
 * Run: php assign_senate_deliberation_permissions.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$permissions = [
    ['name' => 'senate-deliberation-view',   'title' => 'View',   'group' => 'Senate Deliberation', 'guard_name' => 'web'],
    ['name' => 'senate-deliberation-create', 'title' => 'Create', 'group' => 'Senate Deliberation', 'guard_name' => 'web'],
    ['name' => 'senate-deliberation-export', 'title' => 'Export', 'group' => 'Senate Deliberation', 'guard_name' => 'web'],
];

echo "Creating Senate Deliberation permissions...\n";

$permNames = [];
foreach ($permissions as $perm) {
    Permission::firstOrCreate(
        ['name' => $perm['name'], 'guard_name' => $perm['guard_name']],
        ['title' => $perm['title'], 'group' => $perm['group']]
    );
    // Also update title/group in case permission already existed without them
    Permission::where('name', $perm['name'])->update([
        'title' => $perm['title'],
        'group' => $perm['group'],
    ]);
    $permNames[] = $perm['name'];
    echo "  ✓ {$perm['name']} (title: {$perm['title']}, group: {$perm['group']})\n";
}

// Assign to Super Admin
$superAdmin = Role::where('name', 'Super Admin')->first();
if ($superAdmin) {
    $superAdmin->givePermissionTo($permNames);
    echo "\n✓ All permissions assigned to Super Admin role.\n";
} else {
    echo "\n⚠ Super Admin role not found. Assign permissions manually.\n";
}

echo "\nDone!\n";
