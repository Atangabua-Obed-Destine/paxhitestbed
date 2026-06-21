<?php
/**
 * Create remaining missing permissions to reach 698 total
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "<pre>";
echo "=== CREATE REMAINING MISSING PERMISSIONS ===\n\n";

$currentCount = Permission::count();
echo "Current: $currentCount\n";
echo "Target: 698\n";
echo "Need to create: " . (698 - $currentCount) . " more\n\n";

// All missing permissions found in controllers/views
$missingPermissions = [
    // Bank Reconciliation
    ['name' => 'bank-reconciliation-approve', 'group' => 'Accounting', 'title' => 'Approve Bank Reconciliation'],
    ['name' => 'bank-reconciliation-complete', 'group' => 'Accounting', 'title' => 'Complete Bank Reconciliation'],
    ['name' => 'bank-reconciliation-delete', 'group' => 'Accounting', 'title' => 'Delete Bank Reconciliation'],
    ['name' => 'bank-reconciliation-edit', 'group' => 'Accounting', 'title' => 'Edit Bank Reconciliation'],
    ['name' => 'bank-reconciliation-view', 'group' => 'Accounting', 'title' => 'View Bank Reconciliation'],
    
    // Fixed Assets (alternate naming)
    ['name' => 'fixed-asset-dispose', 'group' => 'Accounting', 'title' => 'Dispose Fixed Asset'],
    
    // Recurring Entries
    ['name' => 'recurring-entry-process', 'group' => 'Accounting', 'title' => 'Process Recurring Entry'],
    
    // Year End Closing
    ['name' => 'year-end-closing-approve', 'group' => 'Accounting', 'title' => 'Approve Year End Closing'],
    ['name' => 'year-end-closing-delete', 'group' => 'Accounting', 'title' => 'Delete Year End Closing'],
    ['name' => 'year-end-closing-edit', 'group' => 'Accounting', 'title' => 'Edit Year End Closing'],
    ['name' => 'year-end-closing-process', 'group' => 'Accounting', 'title' => 'Process Year End Closing'],
    ['name' => 'year-end-closing-reverse', 'group' => 'Accounting', 'title' => 'Reverse Year End Closing'],
    ['name' => 'year-end-closing-view', 'group' => 'Accounting', 'title' => 'View Year End Closing'],
    
    // Exam
    ['name' => 'exam-attendance-bypass', 'group' => 'Exam', 'title' => 'Bypass Exam Attendance'],
    
    // Reports
    ['name' => 'report-view', 'group' => 'Reports', 'title' => 'View Reports'],
    ['name' => 'transcript-view', 'group' => 'Reports', 'title' => 'View Transcript'],
    
    // Student Attendance
    ['name' => 'student-attendance-create', 'group' => 'Student Attendance', 'title' => 'Create'],
    
    // Inventory
    ['name' => 'item-issue-create', 'group' => 'Issue Item', 'title' => 'Create'],
];

$created = 0;

foreach ($missingPermissions as $perm) {
    $existing = Permission::where('name', $perm['name'])->first();
    
    if (!$existing) {
        Permission::create([
            'name' => $perm['name'],
            'group' => $perm['group'],
            'title' => $perm['title'],
            'guard_name' => 'web',
        ]);
        echo "✓ Created: {$perm['name']}\n";
        $created++;
    } else {
        echo "→ Exists: {$perm['name']}\n";
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Created: $created permissions\n";

// Assign to Super Admin and Admin roles
echo "\nAssigning to admin roles...\n";

$adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();

foreach ($adminRoles as $role) {
    $assigned = 0;
    foreach ($missingPermissions as $perm) {
        try {
            if (!$role->hasPermissionTo($perm['name'])) {
                $role->givePermissionTo($perm['name']);
                $assigned++;
            }
        } catch (\Exception $e) {
            // Permission might not exist
        }
    }
    echo "  ✓ {$role->name}: assigned $assigned new permissions\n";
}

// Clear permission cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

// Final count
$totalPermissions = Permission::count();
echo "\n";
echo str_repeat("=", 60) . "\n";
echo "TOTAL PERMISSIONS NOW: $totalPermissions\n";
echo "Target: 698\n";
echo "Gap: " . (698 - $totalPermissions) . "\n";
echo str_repeat("=", 60) . "\n";

echo "\n✓ Permission cache cleared\n";
echo "\n</pre>";
