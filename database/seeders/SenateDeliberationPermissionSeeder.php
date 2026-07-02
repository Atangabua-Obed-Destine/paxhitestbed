<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SenateDeliberationPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            ['name' => 'senate-deliberation-view',   'title' => 'View',   'group' => 'Senate Deliberation', 'guard_name' => 'web'],
            ['name' => 'senate-deliberation-create', 'title' => 'Create', 'group' => 'Senate Deliberation', 'guard_name' => 'web'],
            ['name' => 'senate-deliberation-export', 'title' => 'Export', 'group' => 'Senate Deliberation', 'guard_name' => 'web'],
        ];

        $permNames = [];
        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'guard_name' => $perm['guard_name']],
                ['title' => $perm['title'], 'group' => $perm['group']]
            );
            $permNames[] = $perm['name'];
        }

        // Assign to Super Admin
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permNames);
        }
    }
}
