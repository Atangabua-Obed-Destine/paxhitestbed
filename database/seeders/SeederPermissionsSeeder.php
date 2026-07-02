<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SeederPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
    ['name' => 'form-a2-setting-edit', 'group' => 'Student', 'title' => 'Edit Form A2 Settings'],
    ['name' => 'form-a2-access-edit', 'group' => 'Student', 'title' => 'Edit Form A2 Access'],
    ['name' => 'bank-reconciliation-create', 'group' => 'Accounting', 'title' => 'Create Bank Reconciliation'],
    ['name' => 'fixed-asset-edit', 'group' => 'Accounting', 'title' => 'Edit Fixed Assets'],
    ['name' => 'fixed-asset-delete', 'group' => 'Accounting', 'title' => 'Delete Fixed Assets'],
    ['name' => 'fixed-asset-category-create', 'group' => 'Accounting', 'title' => 'Create Fixed Asset Categories'],
    ['name' => 'fixed-asset-category-edit', 'group' => 'Accounting', 'title' => 'Edit Fixed Asset Categories'],
    ['name' => 'fixed-asset-category-delete', 'group' => 'Accounting', 'title' => 'Delete Fixed Asset Categories'],
    ['name' => 'recurring-entry-create', 'group' => 'Accounting', 'title' => 'Create Recurring Entries'],
    ['name' => 'recurring-entry-edit', 'group' => 'Accounting', 'title' => 'Edit Recurring Entries'],
    ['name' => 'recurring-entry-delete', 'group' => 'Accounting', 'title' => 'Delete Recurring Entries'],
    ['name' => 'year-end-closing-create', 'group' => 'Accounting', 'title' => 'Create Year End Closing'],
    ['name' => 'security-users-manage', 'group' => 'Security', 'title' => 'Manage Security Users'],
    ['name' => 'security-logs-export', 'group' => 'Security', 'title' => 'Export Security Logs'],
    ['name' => 'security-whitelist-manage', 'group' => 'Security', 'title' => 'Manage IP Whitelist'],
    ['name' => 'security-settings-edit', 'group' => 'Security', 'title' => 'Edit Security Settings'],
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
