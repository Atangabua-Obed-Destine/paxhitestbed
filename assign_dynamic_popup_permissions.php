<?php

/**
 * Assign Dynamic Popup Permissions to Admin Role
 * Run this file by visiting: http://localhost/paxhitestbed/assign_dynamic_popup_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    echo "<h2>Assigning Dynamic Popup Permissions to Admin Role</h2>";
    
    // Find the admin role (usually ID 1 or name 'Admin')
    $adminRole = Role::where('name', 'Admin')->orWhere('id', 1)->first();
    
    if (!$adminRole) {
        echo "<p style='color: red;'>❌ Admin role not found!</p>";
        exit;
    }
    
    echo "<p>✅ Found role: <strong>{$adminRole->name}</strong> (ID: {$adminRole->id})</p>";
    echo "<hr>";
    
    // Dynamic Popup permissions to assign
    $dynamicPopupPermissions = [
        'dynamic-popup-view',
        'dynamic-popup-create',
        'dynamic-popup-edit',
        'dynamic-popup-delete',
    ];
    
    $assigned = 0;
    $alreadyAssigned = 0;
    $created = 0;
    
    echo "<h3>Processing Permissions:</h3>";
    echo "<ul>";
    
    foreach ($dynamicPopupPermissions as $permissionName) {
        // Check if permission exists, create if not
        $permission = Permission::where('name', $permissionName)->first();
        
        if (!$permission) {
            $permission = Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);
            echo "<li>🆕 Created permission: <strong>{$permissionName}</strong></li>";
            $created++;
        }
        
        // Check if role already has this permission
        if ($adminRole->hasPermissionTo($permissionName)) {
            echo "<li>✓ Already has: <strong>{$permissionName}</strong></li>";
            $alreadyAssigned++;
        } else {
            // Assign permission to role
            $adminRole->givePermissionTo($permission);
            echo "<li>✅ Assigned: <strong>{$permissionName}</strong></li>";
            $assigned++;
        }
    }
    
    echo "</ul>";
    echo "<hr>";
    
    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li>Total permissions processed: <strong>" . count($dynamicPopupPermissions) . "</strong></li>";
    echo "<li>New permissions created: <strong>{$created}</strong></li>";
    echo "<li>Permissions newly assigned: <strong>{$assigned}</strong></li>";
    echo "<li>Already assigned: <strong>{$alreadyAssigned}</strong></li>";
    echo "</ul>";
    
    echo "<p style='color: green; font-size: 18px;'>✅ Dynamic Popup permissions assignment completed!</p>";
    echo "<hr>";
    
    // Show current permissions
    echo "<h3>All Dynamic Popup Permissions for {$adminRole->name} role:</h3>";
    echo "<ul>";
    
    $allPermissions = $adminRole->permissions()->where('name', 'like', 'dynamic-popup%')->get();
    foreach ($allPermissions as $perm) {
        echo "<li>{$perm->name}</li>";
    }
    echo "</ul>";
    
    echo "<br><p><a href='/paxhitestbed/admin/marketing/dynamic-popup'>&rarr; Go to Dynamic Popups Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
