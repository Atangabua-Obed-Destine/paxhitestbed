<?php
/**
 * Exam Publishing Permissions Setup Script
 * 
 * Run this script to create and assign permissions for the Exam Publishing module.
 * Usage: php assign_exam_publishing_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

echo "=== Exam Publishing Permissions Setup ===\n\n";

// Define the permissions to create
$permissions = [
    [
        'name' => 'exam-publishing-view',
        'group' => 'Exam Publishing',
        'title' => 'View',
    ],
    [
        'name' => 'exam-publishing-submit',
        'group' => 'Exam Publishing',
        'title' => 'Submit',
    ],
    [
        'name' => 'exam-publishing-check',
        'group' => 'Exam Publishing',
        'title' => 'Check',
    ],
    [
        'name' => 'exam-publishing-approve',
        'group' => 'Exam Publishing',
        'title' => 'Approve',
    ],
    [
        'name' => 'exam-publishing-publish',
        'group' => 'Exam Publishing',
        'title' => 'Publish',
    ],
];

DB::beginTransaction();

try {
    echo "Creating permissions...\n";
    
    foreach ($permissions as $permData) {
        $permission = Permission::firstOrCreate(
            ['name' => $permData['name'], 'guard_name' => 'web'],
            [
                'group' => $permData['group'],
                'title' => $permData['title'],
            ]
        );
        
        if ($permission->wasRecentlyCreated) {
            echo "  ✓ Created: {$permData['name']}\n";
        } else {
            echo "  - Already exists: {$permData['name']}\n";
        }
    }
    
    echo "\nAssigning permissions to Super Admin role...\n";
    
    // Find super-admin role
    $superAdmin = Role::where('name', 'Super Admin')->first();
    
    if ($superAdmin) {
        $permissionNames = array_column($permissions, 'name');
        
        foreach ($permissionNames as $permName) {
            if (!$superAdmin->hasPermissionTo($permName)) {
                $superAdmin->givePermissionTo($permName);
                echo "  ✓ Assigned {$permName} to Super Admin\n";
            }
        }
        
        echo "  ✓ Super Admin has all exam-publishing permissions\n";
    } else {
        echo "  ⚠ Super Admin role not found\n";
    }
    
    DB::commit();
    
    echo "\n=== Setup Complete! ===\n";
    echo "\nPermissions created:\n";
    foreach ($permissions as $p) {
        echo "  - {$p['name']}: {$p['title']}\n";
    }
    
    echo "\nYou can now access Exam Publishing at:\n";
    echo "  http://localhost/paxhitestbed/admin/exam/exam-publishing\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
