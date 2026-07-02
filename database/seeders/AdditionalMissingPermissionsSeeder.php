<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdditionalMissingPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
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
