<?php
/**
 * Create Missing Seeder Permissions
 * Creates permissions that are defined in PermissionSeeder but missing from database
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "<pre>";
echo "=== CREATE MISSING SEEDER PERMISSIONS ===\n\n";

// Permissions from seeder that are missing in database
$missingPermissions = [
    ['name' => 'form-a2-setting-edit', 'group' => 'Student', 'title' => 'Edit Form A2 Settings'],
    ['name' => 'form-a2-access-edit', 'group' => 'Student', 'title' => 'Edit Form A2 Access'],
    ['name' => 'bank-reconciliation-create', 'group' => 'Accounting', 'title' => 'Create Bank Reconciliation'],
    ['name' => 'fixed-asset-edit', 'group' => 'Accounting', 'title' => 'Edit Fixed Assets'],
    ['name' => 'fixed-asset-delete', 'group' => 'Accounting', 'title' => 'Delete Fixed Assets'],
    ['name' => 'fixed-asset-category-create', 'group' => 'Accounting', 'title' => 'Create Fixed Asset Categories'],
    ['name' => 'fixed-asset-category-edit', 'group' => 'Accounting', 'title' => 'Edit Fixed Asset Categories'],
    ['name' => 'fixed-asset-category-delete', 'group' => 'Accounting', 'title' => 'Delete Fixed Asset Categories'],
    ['name' => 'recurring-entry-create', 'group' => 'Accounting', 'title' => 'Create Recurring Entries'],
    ['name' => 'recurring-entry-edit', 'group' => 'Accounting', 'title' => 'Edit Recurring Entries'],
    ['name' => 'recurring-entry-delete', 'group' => 'Accounting', 'title' => 'Delete Recurring Entries'],
    ['name' => 'year-end-closing-create', 'group' => 'Accounting', 'title' => 'Create Year End Closing'],
    ['name' => 'security-users-manage', 'group' => 'Security', 'title' => 'Manage Security Users'],
    ['name' => 'security-logs-export', 'group' => 'Security', 'title' => 'Export Security Logs'],
    ['name' => 'security-whitelist-manage', 'group' => 'Security', 'title' => 'Manage IP Whitelist'],
    ['name' => 'security-settings-edit', 'group' => 'Security', 'title' => 'Edit Security Settings'],
];

$created = 0;

foreach ($missingPermissions as $perm) {
    $existing = Permission::where('name', $perm['name'])->first();
    
    if (!$existing) {
        Permission::create([
            'name' => $perm['name'],
            'group' => $perm['group'],
            'title' => $perm['title'],
            'guard_name' => 'web',
        ]);
        echo "✓ Created: {$perm['name']}\n";
        $created++;
    } else {
        echo "→ Exists: {$perm['name']}\n";
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Created: $created permissions\n";
echo str_repeat("=", 60) . "\n";

// Assign to Super Admin and Admin roles
echo "\nAssigning to admin roles...\n";

$adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();

foreach ($adminRoles as $role) {
    $assigned = 0;
    foreach ($missingPermissions as $perm) {
        if (!$role->hasPermissionTo($perm['name'])) {
            $role->givePermissionTo($perm['name']);
            $assigned++;
        }
    }
    echo "  ✓ {$role->name}: assigned $assigned new permissions\n";
}

// Clear permission cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

// Final count
$totalPermissions = Permission::count();
echo "\n";
echo str_repeat("=", 60) . "\n";
echo "TOTAL PERMISSIONS NOW: $totalPermissions\n";
echo "Target: 686\n";
echo "Gap: " . (686 - $totalPermissions) . "\n";
echo str_repeat("=", 60) . "\n";

echo "\n✓ Permission cache cleared\n";
echo "\n</pre>";
