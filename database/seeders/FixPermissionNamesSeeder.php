<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class FixPermissionNamesSeeder extends Seeder
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
            'academic-department-create' => ['group' => 'Academic', 'title' => 'Create Academic Department'],
            'academic-department-view' => ['group' => 'Academic', 'title' => 'View Academic Department'],
            'degree-type-create' => ['group' => 'Academic', 'title' => 'Create Degree Type'],
            'degree-type-view' => ['group' => 'Academic', 'title' => 'View Degree Type'],
            'program-semester-fee-create' => ['group' => 'Fees', 'title' => 'Create Program Semester Fee'],
            'program-semester-fee-view' => ['group' => 'Fees', 'title' => 'View Program Semester Fee'],
            
            // Accounting (OHADA)
            'accounting-period-view' => ['group' => 'Accounting', 'title' => 'View Accounting Period'],
            'chart-of-accounts-view' => ['group' => 'Accounting', 'title' => 'View Chart of Accounts'],
            'general-ledger-view' => ['group' => 'Accounting', 'title' => 'View General Ledger'],
            'journal-entry-view' => ['group' => 'Accounting', 'title' => 'View Journal Entry'],
            'trial-balance-view' => ['group' => 'Accounting', 'title' => 'View Trial Balance'],
            'transaction-mapping-settings' => ['group' => 'Accounting', 'title' => 'Transaction Mapping Settings'],
            'transaction-mapping-view' => ['group' => 'Accounting', 'title' => 'View Transaction Mapping'],
            
            // Web / CMS
            'admissions-page-edit' => ['group' => 'Web', 'title' => 'Edit Admissions Page'],
            'admissions-page-view' => ['group' => 'Web', 'title' => 'View Admissions Page'],
            'announcement-create' => ['group' => 'Web', 'title' => 'Create Announcement'],
            'announcement-view' => ['group' => 'Web', 'title' => 'View Announcement'],
            'campus-life-create' => ['group' => 'Web', 'title' => 'Create Campus Life'],
            'campus-life-delete' => ['group' => 'Web', 'title' => 'Delete Campus Life'],
            'campus-life-edit' => ['group' => 'Web', 'title' => 'Edit Campus Life'],
            'campus-life-view' => ['group' => 'Web', 'title' => 'View Campus Life'],
            'welcome-message-create' => ['group' => 'Web', 'title' => 'Create Welcome Message'],
            'welcome-message-view' => ['group' => 'Web', 'title' => 'View Welcome Message'],
            
            // Budget
            'budget-view' => ['group' => 'Budget', 'title' => 'View Budget'],
            
            // Payment Accounts
            'payment-account-report-view' => ['group' => 'Payment Account', 'title' => 'View Payment Account Report'],
            'payment-account-transfer-view' => ['group' => 'Payment Account', 'title' => 'View Payment Account Transfer'],
            'payment-account-view' => ['group' => 'Payment Account', 'title' => 'View Payment Account'],
            
            // Fees
            'payment-plan.index' => ['group' => 'Fees', 'title' => 'View Payment Plans'],
            
            // Staff
            'staff-assignment-create' => ['group' => 'Staff', 'title' => 'Create Staff Assignment'],
            'staff-assignment-delete' => ['group' => 'Staff', 'title' => 'Delete Staff Assignment'],
            'staff-assignment-edit' => ['group' => 'Staff', 'title' => 'Edit Staff Assignment'],
            'staff-assignment-index' => ['group' => 'Staff', 'title' => 'View Staff Assignment'],
            'staff-view' => ['group' => 'Staff', 'title' => 'View Staff'],
            'user-password-print' => ['group' => 'Staff', 'title' => 'Print User Password'],
            
            // Settings
            'schedule-setting-view' => ['group' => 'Setting', 'title' => 'View Schedule Setting'],
            'form-a2-setting-view' => ['group' => 'Setting', 'title' => 'View Form A2 Config'],
            'form-a2-access-view' => ['group' => 'Setting', 'title' => 'View Form A2 Access'],
            
            // Report
            'partial-payment-report-view' => ['group' => 'Report', 'title' => 'View Partial Payment Report'],

            // Student Archive
            'student-archive-view' => ['group' => 'Student', 'title' => 'View Student Archive'],
            'student-archive-edit' => ['group' => 'Student', 'title' => 'Edit Student Archive'],
            'student-archive-password-change' => ['group' => 'Student', 'title' => 'Change Student Archive Password'],
        ];

        foreach ($permissions as $name => $data) {
            $permission = Permission::where('name', $name)->first();
            if ($permission) {
                $permission->group = $data['group'];
                $permission->title = $data['title'];
                $permission->save();
            }
        }
    }
}
