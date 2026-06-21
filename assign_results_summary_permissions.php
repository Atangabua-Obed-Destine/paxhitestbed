<?php

/**
 * Assign Results Summary permissions to users
 * 
 * Run this script from the command line:
 * php assign_results_summary_permissions.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "Assigning Results Summary Permissions...\n";
echo "==========================================\n\n";

try {
    // Clear permission cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Define permissions
    $permissions = [
        'results-summary-view',
        'results-summary-export',
    ];

    // Create permissions if they don't exist
    foreach ($permissions as $permission) {
        $perm = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        echo "✓ Permission '{$permission}' " . ($perm->wasRecentlyCreated ? 'created' : 'already exists') . "\n";
    }

    echo "\n";

    // Assign to super-admin role
    $superAdminRole = Role::where('slug', 'super-admin')->first();
    if ($superAdminRole) {
        foreach ($permissions as $permission) {
            if (!$superAdminRole->hasPermissionTo($permission)) {
                $superAdminRole->givePermissionTo($permission);
                echo "✓ Assigned '{$permission}' to Super Admin role\n";
            } else {
                echo "• '{$permission}' already assigned to Super Admin role\n";
            }
        }
    } else {
        echo "⚠ Super Admin role not found\n";
    }

    // Also assign view permission to admin role
    $adminRole = Role::where('slug', 'admin')->first();
    if ($adminRole) {
        $viewPerm = 'results-summary-view';
        if (!$adminRole->hasPermissionTo($viewPerm)) {
            $adminRole->givePermissionTo($viewPerm);
            echo "✓ Assigned '{$viewPerm}' to Admin role\n";
        } else {
            echo "• '{$viewPerm}' already assigned to Admin role\n";
        }
    }

    // Also assign to registrar role if exists
    $registrarRole = Role::where('slug', 'registrar')->first();
    if ($registrarRole) {
        foreach ($permissions as $permission) {
            if (!$registrarRole->hasPermissionTo($permission)) {
                $registrarRole->givePermissionTo($permission);
                echo "✓ Assigned '{$permission}' to Registrar role\n";
            }
        }
    }

    // Clear cache again
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    echo "\n==========================================\n";
    echo "Results Summary permissions assigned successfully!\n";
    echo "\nYou can now access the Results Summary feature at:\n";
    echo "http://localhost/paxhitestbed/admin/exam/results-summary\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
