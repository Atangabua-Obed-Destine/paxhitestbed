<?php
/**
 * Create Missing Permissions
 * 
 * This script creates permissions that are referenced in sidebar.blade.php
 * but don't exist in the database.
 * 
 * Run: php create_missing_permissions.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;

echo "=== Creating Missing Sidebar Permissions ===\n\n";

// Permissions referenced in sidebar but might be missing
$missingPermissions = [
    // Accounting permissions
    [
        'name' => 'accounting-report-view',
        'title' => 'View Accounting Reports',
        'group' => 'Accounting',
        'guard_name' => 'web',
    ],
    [
        'name' => 'bank-reconciliation-list',
        'title' => 'List Bank Reconciliations',
        'group' => 'Accounting',
        'guard_name' => 'web',
    ],
    [
        'name' => 'fixed-asset-list',
        'title' => 'List Fixed Assets',
        'group' => 'Accounting',
        'guard_name' => 'web',
    ],
    [
        'name' => 'fixed-asset-create',
        'title' => 'Create Fixed Asset',
        'group' => 'Accounting',
        'guard_name' => 'web',
    ],
    [
        'name' => 'fixed-asset-depreciation',
        'title' => 'Manage Asset Depreciation',
        'group' => 'Accounting',
        'guard_name' => 'web',
    ],
    [
        'name' => 'recurring-entry-list',
        'title' => 'List Recurring Entries',
        'group' => 'Accounting',
        'guard_name' => 'web',
    ],
    [
        'name' => 'year-end-closing-list',
        'title' => 'List Year End Closings',
        'group' => 'Accounting',
        'guard_name' => 'web',
    ],
    
    // Student Form A3 permission
    [
        'name' => 'student-form-a3-view',
        'title' => 'View Student Form A3',
        'group' => 'Student',
        'guard_name' => 'web',
    ],
    
    // Class Hub permissions
    [
        'name' => 'class-hub-reports-view',
        'title' => 'View Class Hub Reports',
        'group' => 'Class Hub',
        'guard_name' => 'web',
    ],
    
    // Class Session permissions
    [
        'name' => 'class-session-list',
        'title' => 'List Class Sessions',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-create',
        'title' => 'Create Class Session',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-view',
        'title' => 'View Class Session',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-edit',
        'title' => 'Edit Class Session',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-delete',
        'title' => 'Delete Class Session',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-start',
        'title' => 'Start Class Session',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-end',
        'title' => 'End Class Session',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-logbook',
        'title' => 'Manage Class Logbook',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-attendance',
        'title' => 'Manage Class Attendance',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-messages',
        'title' => 'View Class Messages',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    [
        'name' => 'class-session-delegate',
        'title' => 'Delegate Class Rep',
        'group' => 'Class Session',
        'guard_name' => 'web',
    ],
    
    // Attendance Setting permissions
    [
        'name' => 'attendance-setting-list',
        'title' => 'List Attendance Settings',
        'group' => 'Attendance Setting',
        'guard_name' => 'web',
    ],
    [
        'name' => 'attendance-setting-edit',
        'title' => 'Edit Attendance Settings',
        'group' => 'Attendance Setting',
        'guard_name' => 'web',
    ],
    
    // Form A3 Setting
    [
        'name' => 'form-a3-setting-view',
        'title' => 'View Form A3 Settings',
        'group' => 'Form A3',
        'guard_name' => 'web',
    ],
    [
        'name' => 'form-a3-setting-edit',
        'title' => 'Edit Form A3 Settings',
        'group' => 'Form A3',
        'guard_name' => 'web',
    ],
];

$created = 0;
$existed = 0;

foreach ($missingPermissions as $perm) {
    $existing = Permission::where('name', $perm['name'])->first();
    
    if ($existing) {
        // Update if title/group empty
        if (empty($existing->title) || empty($existing->group)) {
            $existing->update([
                'title' => $perm['title'],
                'group' => $perm['group'],
            ]);
            echo "[UPDATED] {$perm['name']}\n";
        } else {
            echo "[EXISTS]  {$perm['name']}\n";
        }
        $existed++;
    } else {
        Permission::create($perm);
        echo "[CREATED] {$perm['name']} - {$perm['title']}\n";
        $created++;
    }
}

echo "\n=== Summary ===\n";
echo "Created: {$created}\n";
echo "Already Existed: {$existed}\n";
echo "Total Checked: " . count($missingPermissions) . "\n";

echo "\n=== Done! ===\n";
