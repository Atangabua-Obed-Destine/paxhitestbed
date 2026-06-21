<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SectorPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Permission List
        $permissions = [
            [
                'name' => 'sector-view',
                'title' => 'View Sector',
                'group' => 'Academic',
            ],
            [
                'name' => 'sector-create',
                'title' => 'Create Sector',
                'group' => 'Academic',
            ],
            [
                'name' => 'sector-edit',
                'title' => 'Edit Sector',
                'group' => 'Academic',
            ],
            [
                'name' => 'sector-delete',
                'title' => 'Delete Sector',
                'group' => 'Academic',
            ],
        ];

        // Create Permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'title' => $permission['title'],
                    'group' => $permission['group'],
                    'guard_name' => 'web'
                ]
            );
        }

        // Assign to Super Admin
        $role = Role::where('name', 'Super Admin')->first();
        if ($role) {
            $role->givePermissionTo([
                'sector-view',
                'sector-create',
                'sector-edit',
                'sector-delete',
            ]);
        }
    }
}
