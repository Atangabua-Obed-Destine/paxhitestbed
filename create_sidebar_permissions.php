<?php
/**
 * Create Missing Sidebar Permissions
 * 
 * This script creates permissions that are referenced in sidebar.blade.php
 * but don't exist in the database.
 * 
 * Run: php create_sidebar_permissions.php
 * Or visit: http://localhost/paxhiproduction/create_sidebar_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "<pre>";
echo "=== CREATE MISSING SIDEBAR PERMISSIONS ===\n\n";

// Missing permissions with proper groups and titles
$missingPermissions = [
    // Accounting Reports
    ['name' => 'accounting-report-budget', 'group' => 'Accounting Reports', 'title' => 'Budget Report'],
    ['name' => 'accounting-report-cashflow', 'group' => 'Accounting Reports', 'title' => 'Cash Flow Report'],
    ['name' => 'accounting-report-payables', 'group' => 'Accounting Reports', 'title' => 'Accounts Payable Report'],
    ['name' => 'accounting-report-receivables', 'group' => 'Accounting Reports', 'title' => 'Accounts Receivable Report'],
    ['name' => 'accounting-report-student-fee', 'group' => 'Accounting Reports', 'title' => 'Student Fee Report'],
    
    // Admission
    ['name' => 'admission-fee-config-view', 'group' => 'Application', 'title' => 'View Admission Fee Config'],
    
    // Budget Reports
    ['name' => 'budget-report-cashflow', 'group' => 'Budget', 'title' => 'Cash Flow Report'],
    ['name' => 'budget-report-department', 'group' => 'Budget', 'title' => 'Department Report'],
    ['name' => 'budget-report-performance', 'group' => 'Budget', 'title' => 'Performance Report'],
    ['name' => 'budget-report-variance', 'group' => 'Budget', 'title' => 'Variance Report'],
    
    // Dashboard
    ['name' => 'dashboard-view', 'group' => 'Dashboard', 'title' => 'View Dashboard'],
    
    // Fixed Assets
    ['name' => 'fixed-asset-category-list', 'group' => 'Accounting', 'title' => 'View Fixed Asset Categories'],
    
    // Max Credit Config
    ['name' => 'max-credit-config-view', 'group' => 'Academic', 'title' => 'View Max Credit Config'],
    
    // Payment Account Reports
    ['name' => 'payment-account-report-cashflow', 'group' => 'Payment Account Report', 'title' => 'Cash Flow'],
    ['name' => 'payment-account-report-statement', 'group' => 'Payment Account Report', 'title' => 'Statement'],
    ['name' => 'payment-account-report-summary', 'group' => 'Payment Account Report', 'title' => 'Summary'],
    
    // Security (already in seeder but missing from DB)
    ['name' => 'security-logs-view', 'group' => 'Security', 'title' => 'View Security Logs'],
    ['name' => 'security-settings-view', 'group' => 'Security', 'title' => 'View Security Settings'],
    ['name' => 'security-users-view', 'group' => 'Security', 'title' => 'View Security Users'],
    ['name' => 'security-whitelist-view', 'group' => 'Security', 'title' => 'View IP Whitelist'],
    
    // Staff ID Card
    ['name' => 'staff-id-card-view', 'group' => 'Staff', 'title' => 'View Staff ID Card'],
];

$created = 0;
$existed = 0;

foreach ($missingPermissions as $perm) {
    $existing = Permission::where('name', $perm['name'])->first();
    
    if ($existing) {
        // Update group and title if missing
        $needsUpdate = false;
        if (empty($existing->group) && !empty($perm['group'])) {
            $existing->group = $perm['group'];
            $needsUpdate = true;
        }
        if (empty($existing->title) && !empty($perm['title'])) {
            $existing->title = $perm['title'];
            $needsUpdate = true;
        }
        
        if ($needsUpdate) {
            $existing->save();
            echo "→ Updated: {$perm['name']} (added group/title)\n";
        } else {
            echo "→ Exists: {$perm['name']}\n";
        }
        $existed++;
    } else {
        Permission::create([
            'name' => $perm['name'],
            'group' => $perm['group'],
            'title' => $perm['title'],
            'guard_name' => 'web',
        ]);
        echo "✓ Created: {$perm['name']}\n";
        $created++;
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Summary:\n";
echo "  Created: $created\n";
echo "  Already existed: $existed\n";
echo str_repeat("=", 60) . "\n";

// Assign to Super Admin and Admin roles
echo "\n";
echo "Assigning to admin roles...\n";

$adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();

foreach ($adminRoles as $role) {
    $assigned = 0;
    foreach ($missingPermissions as $perm) {
        if (!$role->hasPermissionTo($perm['name'])) {
            $role->givePermissionTo($perm['name']);
            $assigned++;
        }
    }
    echo "  ✓ {$role->name}: assigned $assigned new permissions\n";
}

// Clear permission cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "\n✓ Permission cache cleared\n";

echo "\n</pre>";
