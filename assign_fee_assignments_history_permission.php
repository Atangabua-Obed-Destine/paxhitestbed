<?php

/**
 * Seed the new permission required by /admin/fee-assignments-history
 * and assign it to all roles that already hold any of the broad
 * fees-collection view permissions (so existing finance staff get
 * access automatically). Run from repo root:
 *
 *   php assign_fee_assignments_history_permission.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$permName = 'fee-assignments-history-view';

// Create the permission if missing (web guard, matching the existing fees-* perms).
$permission = Permission::firstOrCreate(
    ['name' => $permName, 'guard_name' => 'web']
);
echo "Permission: {$permission->name} (id={$permission->id})" . PHP_EOL;

$deletePermName = 'fee-assignments-history-delete';
$deletePermission = Permission::firstOrCreate(
    ['name' => $deletePermName, 'guard_name' => 'web']
);
echo "Permission: {$deletePermission->name} (id={$deletePermission->id})" . PHP_EOL;

$transferPermName = 'fee-assignments-history-transfer';
$transferPermission = Permission::firstOrCreate(
    ['name' => $transferPermName, 'guard_name' => 'web']
);
echo "Permission: {$transferPermission->name} (id={$transferPermission->id})" . PHP_EOL;

// Reference perms — any role with one of these is presumed to need
// visibility into the universal assignment ledger.
$referencePerms = [
    'fees-student-report',
    'fees-master-view',
    'program-semester-fee-view',
];

$assigned = 0;
$roles = Role::where('guard_name', 'web')->get();
foreach ($roles as $role) {
    $existing = $role->permissions->pluck('name')->all();
    $hasReference = (bool) array_intersect($existing, $referencePerms);
    // Always grant Super Admin; otherwise only grant if role already has a
    // related fees permission.
    $isSuperAdmin = strcasecmp($role->name, 'Super Admin') === 0
        || strcasecmp($role->name, 'Administrator') === 0;
    if (! $hasReference && ! $isSuperAdmin) {
        continue;
    }
    if (! in_array($permName, $existing, true)) {
        $role->givePermissionTo($permission);
        echo "  + Granted view to role: {$role->name}" . PHP_EOL;
        $assigned++;
    } else {
        echo "  - View already on role:  {$role->name}" . PHP_EOL;
    }

    // Delete is restricted: only Super Admin / Administrator by default.
    if ($isSuperAdmin) {
        if (! in_array($deletePermName, $existing, true)) {
            $role->givePermissionTo($deletePermission);
            echo "  + Granted DELETE to role: {$role->name}" . PHP_EOL;
        } else {
            echo "  - DELETE already on role: {$role->name}" . PHP_EOL;
        }
    }

    // Transfer is granted to Super Admin / Administrator + Accountant
    // (since accountants do day-to-day reconciliation).
    $isAccountant = strcasecmp($role->name, 'Accountant') === 0;
    if ($isSuperAdmin || $isAccountant) {
        if (! in_array($transferPermName, $existing, true)) {
            $role->givePermissionTo($transferPermission);
            echo "  + Granted TRANSFER to role: {$role->name}" . PHP_EOL;
        } else {
            echo "  - TRANSFER already on role: {$role->name}" . PHP_EOL;
        }
    }
}

\Spatie\Permission\PermissionRegistrar::class;
app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

echo PHP_EOL . "Done. Newly granted to {$assigned} role(s)." . PHP_EOL;
