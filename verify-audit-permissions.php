<?php
// Verify Audit Trail Permissions
// Run with: php verify-audit-permissions.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "\n=== Audit Trail Permissions Verification ===\n\n";

// Check permissions
$permissions = Permission::where('name', 'like', 'audit-log%')->get();

if ($permissions->count() > 0) {
    echo "✓ Found " . $permissions->count() . " audit trail permission(s):\n\n";
    foreach ($permissions as $perm) {
        echo "  • {$perm->name}\n";
        echo "    Group: {$perm->group}\n";
        echo "    Title: {$perm->title}\n";
        echo "    Guard: {$perm->guard_name}\n\n";
    }
} else {
    echo "✗ No audit trail permissions found!\n";
    echo "  Run: php artisan db:seed --class=AuditPermissionSeeder\n\n";
    exit(1);
}

// Check which roles have these permissions
echo "=== Roles with Audit Trail Permissions ===\n\n";
$roles = Role::whereHas('permissions', function($q) {
    $q->where('name', 'like', 'audit-log%');
})->get();

if ($roles->count() > 0) {
    foreach ($roles as $role) {
        $rolePerms = $role->permissions()->where('name', 'like', 'audit-log%')->pluck('name')->toArray();
        echo "  • {$role->name}:\n";
        foreach ($rolePerms as $perm) {
            echo "    - {$perm}\n";
        }
        echo "\n";
    }
} else {
    echo "  No roles have audit trail permissions yet.\n\n";
}

echo "=== Summary ===\n";
echo "✓ Permissions created: " . $permissions->count() . "\n";
echo "✓ Roles with access: " . $roles->count() . "\n";
echo "\nYou can now:\n";
echo "1. Login as Admin or Super Admin\n";
echo "2. Visit: http://localhost/paxhi/admin/audit-log\n";
echo "3. View and filter audit logs\n";
echo "4. Export logs to CSV\n\n";
