<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UndocumentedPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            // 1. Exam & Grading
            ['name' => 'subject-marking-unlock', 'title' => 'Unlock Marks/Attendance', 'group' => 'Exam Marking'],

            // 2. Accounting (Fixed Assets plural fix)
            ['name' => 'fixed-assets-create', 'title' => 'Create Fixed Assets', 'group' => 'Accounting'],
            ['name' => 'fixed-assets-edit', 'title' => 'Edit Fixed Assets', 'group' => 'Accounting'],
            ['name' => 'fixed-assets-delete', 'title' => 'Delete Fixed Assets', 'group' => 'Accounting'],

            // 3. Accounting (Recurring Entries plural fix)
            ['name' => 'recurring-entries-process', 'title' => 'Process Recurring Entries', 'group' => 'Accounting'],
            ['name' => 'recurring-entries-create', 'title' => 'Create Recurring Entries', 'group' => 'Accounting'],
            ['name' => 'recurring-entries-view', 'title' => 'View Recurring Entries', 'group' => 'Accounting'],
            ['name' => 'recurring-entries-edit', 'title' => 'Edit Recurring Entries', 'group' => 'Accounting'],
            ['name' => 'recurring-entries-delete', 'title' => 'Delete Recurring Entries', 'group' => 'Accounting'],

            // 4. Finance & Reports
            ['name' => 'fee-assignments-history-transfer', 'title' => 'Transfer Fee History', 'group' => 'Fee Assignments History'],
            ['name' => 'fee-assignments-history-delete', 'title' => 'Delete Fee History', 'group' => 'Fee Assignments History'],
            ['name' => 'fee-assignments-history-view', 'title' => 'View Fee History', 'group' => 'Fee Assignments History'],
            ['name' => 'partial-payment-report-view', 'title' => 'View Partial Payment Report', 'group' => 'Accounting Reports'],

            // 5. Library / Inventory
            ['name' => 'issue-return-create', 'title' => 'Create Issue/Return', 'group' => 'Library'],
            ['name' => 'issue-return-view', 'title' => 'View Issue/Return', 'group' => 'Library'],

            // 6. HR / Admin
            ['name' => 'staff-view', 'title' => 'View Staff Directory', 'group' => 'Staff'],
        ];

        $permNames = [];
        foreach ($permissions as $perm) {
            $perm['guard_name'] = 'web';
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['title' => $perm['title'], 'group' => $perm['group']]
            );
            $permNames[] = $perm['name'];
        }

        // Assign all to Admin
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permNames);
        }

        // Assign all to Super Admin
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permNames);
        }
    }
}
