<?php

/**
 * Assign Class Session Tracking Permissions
 * Run this file by visiting: http://localhost/paxhitest/assign_class_session_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    echo "<h2>Setting up Class Session Tracking Permissions</h2>";
    
    // Define the permissions
    $permissions = [
        'class-session-view',
        'class-session-create',
        'class-session-edit',
        'class-session-delete',
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
    }
    
    // Assign to Super Admin role
    $superAdminRole = Role::where('slug', 'super-admin')->orWhere('name', 'Super Admin')->first();
    if ($superAdminRole) {
        $superAdminRole->givePermissionTo($permissions);
        echo "<p>✅ All permissions assigned to <strong>{$superAdminRole->name}</strong> role</p>";
    }
    
    // Assign view and create permissions to Teacher/Lecturer role
    $teacherRole = Role::where('name', 'Teacher')
                    ->orWhere('name', 'Lecturer')
                    ->orWhere('name', 'Staff')
                    ->orWhere('slug', 'teacher')
                    ->orWhere('slug', 'staff')
                    ->first();
    if ($teacherRole) {
        $teacherRole->givePermissionTo([
            'class-session-view',
            'class-session-create',
            'class-session-edit'
        ]);
        echo "<p>✅ View, Create, Edit permissions assigned to <strong>{$teacherRole->name}</strong> role</p>";
    }
    
    // Also try to assign to all staff roles
    $staffRoles = Role::whereIn('name', ['Staff', 'Teacher', 'Lecturer', 'Academic Staff'])->get();
    foreach ($staffRoles as $role) {
        $role->givePermissionTo([
            'class-session-view',
            'class-session-create',
            'class-session-edit'
        ]);
        echo "<p>✅ Permissions also assigned to <strong>{$role->name}</strong> role</p>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Setup Complete!</h3>";
    echo "<p>The following features are now available:</p>";
    echo "<ul>";
    echo "<li><strong>Class Session Kiosk</strong>: Lecturers can open a kiosk for their scheduled classes</li>";
    echo "<li><strong>Student Clock In/Out</strong>: Students scan QR codes to clock in/out of classes</li>";
    echo "<li><strong>Digital Logbook</strong>: Lecturers can fill in class logbook during/after class</li>";
    echo "<li><strong>Auto Lecturer Attendance</strong>: Syncs to staff_hourly_attendances when class completes</li>";
    echo "<li><strong>Admin Dashboard</strong>: View all class sessions and logbooks with filters</li>";
    echo "</ul>";
    echo "<p><a href='/paxhitest/admin/class-session'>View Class Sessions</a> | ";
    echo "<a href='/paxhitest/admin/class-session/kiosk'>Open Kiosk</a></p>";

} catch (\Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
