<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CmsPermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
        'project-view',
        'project-create',
        'project-edit',
        'project-delete',
        'leadership-team-view',
        'leadership-team-create',
        'leadership-team-edit',
        'leadership-team-delete',
        'accreditation-view',
        'accreditation-create',
        'accreditation-edit',
        'accreditation-delete',
        'history-timeline-view',
        'history-timeline-create',
        'history-timeline-edit',
        'history-timeline-delete',
        'support-service-view',
        'support-service-create',
        'support-service-edit',
        'support-service-delete',
        'admission-date-view',
        'admission-date-create',
        'admission-date-edit',
        'admission-date-delete',
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
