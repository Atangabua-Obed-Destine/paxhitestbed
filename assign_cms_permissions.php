<?php

/**
 * Assign CMS Permissions to Admin Role
 * Run this file by visiting: http://localhost/paxhitest/assign_cms_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    echo "<h2>Assigning CMS Permissions to Admin Role</h2>";
    
    // Find the admin role (usually ID 1 or name 'Admin')
    $adminRole = Role::where('name', 'Admin')->orWhere('id', 1)->first();
    
    if (!$adminRole) {
        echo "<p style='color: red;'>❌ Admin role not found!</p>";
        exit;
    }
    
    echo "<p>✅ Found role: <strong>{$adminRole->name}</strong> (ID: {$adminRole->id})</p>";
    echo "<hr>";
    
    // CMS permissions to assign
    $cmsPermissions = [
        'project-view',
        'project-create',
        'project-edit',
        'project-delete',
        'leadership-team-view',
        'leadership-team-create',
        'leadership-team-edit',
        'leadership-team-delete',
        'accreditation-view',
        'accreditation-create',
        'accreditation-edit',
        'accreditation-delete',
        'history-timeline-view',
        'history-timeline-create',
        'history-timeline-edit',
        'history-timeline-delete',
        'support-service-view',
        'support-service-create',
        'support-service-edit',
        'support-service-delete',
        'admission-date-view',
        'admission-date-create',
        'admission-date-edit',
        'admission-date-delete',
    ];
    
    $assigned = 0;
    $alreadyHad = 0;
    $notFound = [];
    
    foreach ($cmsPermissions as $permissionName) {
        $permission = Permission::where('name', $permissionName)->first();
        
        if ($permission) {
            if (!$adminRole->hasPermissionTo($permissionName)) {
                $adminRole->givePermissionTo($permissionName);
                echo "<p style='color: green;'>✅ Assigned: <strong>{$permissionName}</strong></p>";
                $assigned++;
            } else {
                echo "<p style='color: blue;'>ℹ️ Already has: <strong>{$permissionName}</strong></p>";
                $alreadyHad++;
            }
        } else {
            echo "<p style='color: orange;'>⚠️ Permission not found: <strong>{$permissionName}</strong></p>";
            $notFound[] = $permissionName;
        }
    }
    
    echo "<hr>";
    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li>✅ Newly assigned: <strong>{$assigned}</strong> permissions</li>";
    echo "<li>ℹ️ Already had: <strong>{$alreadyHad}</strong> permissions</li>";
    echo "<li>⚠️ Not found: <strong>".count($notFound)."</strong> permissions</li>";
    echo "</ul>";
    
    if (count($notFound) > 0) {
        echo "<p style='color: orange;'><strong>Missing permissions:</strong> " . implode(', ', $notFound) . "</p>";
        echo "<p>You may need to run: <code>php artisan db:seed --class=PermissionSeeder</code></p>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Done! Now refresh your admin panel and check the sidebar under 'Front Web' menu.</h3>";
    echo "<p>The following CMS items should now be visible:</p>";
    echo "<ul>";
    echo "<li>Projects</li>";
    echo "<li>Leadership Team</li>";
    echo "<li>Accreditations</li>";
    echo "<li>History Timeline</li>";
    echo "<li>Support Services</li>";
    echo "<li>Admission Dates</li>";
    echo "</ul>";
    
} catch (\Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
