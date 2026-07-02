<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RemainingPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
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
