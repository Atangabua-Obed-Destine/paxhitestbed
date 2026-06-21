<?php

/**
 * Assign Resource Permissions to Admin Role
 * Run this file by visiting: http://localhost/paxhitestbed/assign_resource_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    echo "<h2>Assigning Resource/Downloads Permissions to Admin Role</h2>";
    
    // Find the admin role
    $adminRole = Role::where('name', 'Admin')->orWhere('id', 1)->first();
    
    if (!$adminRole) {
        echo "<p style='color: red;'>❌ Admin role not found!</p>";
        exit;
    }
    
    echo "<p>✅ Found role: <strong>{$adminRole->name}</strong> (ID: {$adminRole->id})</p>";
    echo "<hr>";
    
    // Resource permissions to create and assign
    $resourcePermissions = [
        'resource-view',
        'resource-create',
        'resource-edit',
        'resource-delete',
    ];
    
    $created = 0;
    $assigned = 0;
    
    echo "<h3>Creating & Assigning Permissions:</h3>";
    echo "<ul>";
    
    foreach ($resourcePermissions as $permissionName) {
        // Create permission if it doesn't exist
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web']
        );
        
        if ($permission->wasRecentlyCreated) {
            echo "<li style='color: blue;'>✨ Created new permission: <strong>{$permissionName}</strong></li>";
            $created++;
        }
        
        // Assign to admin role if not already assigned
        if (!$adminRole->hasPermissionTo($permissionName)) {
            $adminRole->givePermissionTo($permissionName);
            echo "<li style='color: green;'>✅ Assigned: <strong>{$permissionName}</strong></li>";
            $assigned++;
        } else {
            echo "<li style='color: gray;'>⏭️ Already assigned: {$permissionName}</li>";
        }
    }
    
    echo "</ul>";
    echo "<hr>";
    echo "<h3>Summary:</h3>";
    echo "<p>✨ New permissions created: <strong>{$created}</strong></p>";
    echo "<p>✅ Permissions assigned: <strong>{$assigned}</strong></p>";
    echo "<p style='color: green; font-size: 18px;'><strong>✅ Resource permissions setup complete!</strong></p>";
    echo "<p><a href='/paxhitestbed/admin/web/resource'>→ Go to Resources & Downloads Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
