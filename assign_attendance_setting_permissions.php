<?php

/**
 * Assign Attendance Setting Permissions
 * Run this file by visiting: http://localhost/paxhitestbed/assign_attendance_setting_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    echo "<h2>Setting up Attendance Setting Permissions</h2>";
    echo "<hr>";
    
    // Define the permissions
    $permissions = [
        'attendance-setting-view',
        'attendance-setting-edit',
    ];

    echo "<h3>Step 1: Creating Permissions</h3>";
    
    foreach ($permissions as $permissionName) {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName, 'guard_name' => 'web'],
            ['name' => $permissionName, 'guard_name' => 'web']
        );
        echo "<p>✅ Permission: <strong>{$permissionName}</strong></p>";
    }
    
    echo "<hr>";
    echo "<h3>Step 2: Assigning Permissions to Roles</h3>";
    
    // Assign to Admin role
    $adminRole = Role::where('name', 'Admin')->orWhere('id', 1)->first();
    if ($adminRole) {
        $adminRole->givePermissionTo($permissions);
        echo "<p>✅ All permissions assigned to <strong>{$adminRole->name}</strong> role</p>";
    } else {
        echo "<p>⚠️ Admin role not found</p>";
    }
    
    // Assign to Super Admin role (if exists)
    $superAdminRole = Role::where('slug', 'super-admin')->orWhere('name', 'Super Admin')->first();
    if ($superAdminRole && $superAdminRole->id != ($adminRole->id ?? 0)) {
        $superAdminRole->givePermissionTo($permissions);
        echo "<p>✅ All permissions assigned to <strong>{$superAdminRole->name}</strong> role</p>";
    }
    
    echo "<hr>";
    echo "<h3>Step 3: Verification</h3>";
    
    // Show all created permissions
    echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'><th>Permission Name</th><th>Guard</th><th>Created At</th></tr>";
    
    foreach ($permissions as $permName) {
        $perm = Permission::where('name', $permName)->first();
        if ($perm) {
            echo "<tr>";
            echo "<td>{$perm->name}</td>";
            echo "<td>{$perm->guard_name}</td>";
            echo "<td>{$perm->created_at}</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>✅ Setup Complete!</h3>";
    echo "<p>You can now access the Attendance Settings page at:</p>";
    echo "<p><a href='/paxhitestbed/admin/attendance-settings' style='font-size: 16px; color: blue;'><strong>Admin → Students → Attendances → Kiosk Settings</strong></a></p>";
    
    echo "<hr>";
    echo "<h4>Permissions Summary:</h4>";
    echo "<ul>";
    echo "<li><strong>attendance-setting-view</strong>: View the settings page</li>";
    echo "<li><strong>attendance-setting-edit</strong>: Edit and reset settings</li>";
    echo "</ul>";
    
    echo "<hr>";
    echo "<p style='color: green; font-size: 14px;'><strong>Note:</strong> Clear the permission cache if changes don't take effect immediately.</p>";
    echo "<p>Run: <code>php artisan cache:clear && php artisan permission:cache-reset</code></p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
