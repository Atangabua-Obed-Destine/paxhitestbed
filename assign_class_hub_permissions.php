<?php
/**
 * Class Hub Permissions Setup Script
 * 
 * This script creates and assigns permissions for:
 * 1. Class Hub Reports (Admin/HOD)
 * 
 * Run: php assign_class_hub_permissions.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

echo "=== Class Hub Permissions Setup ===\n\n";

// Define permissions
$permissions = [
    'class-hub-reports-view' => 'View Class Hub engagement reports and statistics',
    'class-hub-reports-export' => 'Export Class Hub report data to CSV/Excel',
];

// Create permissions
echo "Creating permissions...\n";
foreach ($permissions as $name => $description) {
    $permission = Permission::firstOrCreate(
        ['name' => $name, 'guard_name' => 'web'],
        ['name' => $name, 'guard_name' => 'web']
    );
    echo "  ✓ {$name}\n";
}

// Assign to Super Admin
echo "\nAssigning to Super Admin...\n";
$superAdmin = Role::where('name', 'Super Admin')->first();
if ($superAdmin) {
    foreach (array_keys($permissions) as $permName) {
        if (!$superAdmin->hasPermissionTo($permName)) {
            $superAdmin->givePermissionTo($permName);
            echo "  ✓ Assigned {$permName} to Super Admin\n";
        } else {
            echo "  - {$permName} already assigned to Super Admin\n";
        }
    }
} else {
    echo "  ! Super Admin role not found\n";
}

// Assign to Admin
echo "\nAssigning to Admin...\n";
$admin = Role::where('name', 'Admin')->first();
if ($admin) {
    foreach (array_keys($permissions) as $permName) {
        if (!$admin->hasPermissionTo($permName)) {
            $admin->givePermissionTo($permName);
            echo "  ✓ Assigned {$permName} to Admin\n";
        } else {
            echo "  - {$permName} already assigned to Admin\n";
        }
    }
} else {
    echo "  ! Admin role not found\n";
}

// Assign view-only to HOD if exists
echo "\nAssigning view-only to HOD...\n";
$hod = Role::where('name', 'HOD')->first();
if ($hod) {
    $viewPerm = 'class-hub-reports-view';
    if (!$hod->hasPermissionTo($viewPerm)) {
        $hod->givePermissionTo($viewPerm);
        echo "  ✓ Assigned {$viewPerm} to HOD\n";
    } else {
        echo "  - {$viewPerm} already assigned to HOD\n";
    }
} else {
    echo "  ! HOD role not found (skipping)\n";
}

// Clear permission cache
echo "\nClearing permission cache...\n";
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "  ✓ Cache cleared\n";

echo "\n=== Class Hub Permissions Setup Complete ===\n";
echo "\nPermissions created:\n";
foreach ($permissions as $name => $desc) {
    echo "  - {$name}: {$desc}\n";
}

echo "\nMenu location: Admin > Students > Attendance > Class Hub Reports\n";
echo "Required permissions:\n";
echo "  - class-hub-reports-view: View reports\n";
echo "  - class-hub-reports-export: Export data\n";
