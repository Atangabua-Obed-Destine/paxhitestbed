<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MissingPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Academic
            'academic-department-create',
            'academic-department-view',
            'degree-type-create',
            'degree-type-view',
            'program-semester-fee-create',
            'program-semester-fee-view',
            
            // Accounting (OHADA)
            'accounting-period-view',
            'chart-of-accounts-view',
            'general-ledger-view',
            'journal-entry-view',
            'trial-balance-view',
            'transaction-mapping-settings',
            'transaction-mapping-view',
            
            // Web / CMS
            'admissions-page-edit',
            'admissions-page-view',
            'announcement-create',
            'announcement-view',
            'campus-life-create',
            'campus-life-delete',
            'campus-life-edit',
            'campus-life-view',
            'welcome-message-create',
            'welcome-message-view',
            
            // Budget
            'budget-view',
            
            // Payment Accounts
            'payment-account-report-view',
            'payment-account-transfer-view',
            'payment-account-view',
            
            // Fees
            'payment-plan.index',
            
            // Staff
            'staff-assignment-create',
            'staff-assignment-delete',
            'staff-assignment-edit',
            'staff-assignment-index',
            'staff-view',
            'user-password-print',
            
            // Settings
            'schedule-setting-view',
            
            // New permissions for unprotected items
            'form-a2-setting-view',
            'form-a2-access-view',
            'form-a3-setting-view',
            'partial-payment-report-view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign all permissions to Super Admin
        $role = Role::where('name', 'Super Admin')->first();
        if ($role) {
            $role->syncPermissions(Permission::all());
        }
    }
}
