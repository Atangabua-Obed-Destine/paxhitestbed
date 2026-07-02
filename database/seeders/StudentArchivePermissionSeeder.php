<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StudentArchivePermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
        [
            'name' => 'student-archive-view',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'student-archive-edit',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'student-archive-password-change',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];

        $permNames = [];
        foreach ($permissions as $perm) {
            if (is_array($perm)) {
                $name = $perm['name'];
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
