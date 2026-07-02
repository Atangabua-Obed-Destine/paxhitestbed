<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SidebarPermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
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

        $permNames = [];
        foreach ($permissions as $perm) {
            if (is_array($perm)) {
                $name = $perm['name'] ?? $perm[0] ?? null;
                if (!$name) continue;
                
                $guard = $perm['guard_name'] ?? 'web';
                $title = $perm['title'] ?? null;
                $group = $perm['group'] ?? null;
                
                $updateData = [];
                if ($title) $updateData['title'] = $title;
                if ($group) $updateData['group'] = $group;

                Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                    $updateData
                );
                $permNames[] = $name;
            } else {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
                $permNames[] = $perm;
            }
        }

        // Try to assign to sensible roles
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) $adminRole->givePermissionTo($permNames);
        
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) $superAdminRole->givePermissionTo($permNames);
    }
}
