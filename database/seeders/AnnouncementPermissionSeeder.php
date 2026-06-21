<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AnnouncementPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            // Announcement Permissions
            ['name' => 'announcement-view', 'group' => 'Announcement', 'title' => 'View'],
            ['name' => 'announcement-create', 'group' => 'Announcement', 'title' => 'Create'],
            ['name' => 'announcement-edit', 'group' => 'Announcement', 'title' => 'Edit'],
            ['name' => 'announcement-delete', 'group' => 'Announcement', 'title' => 'Delete'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // Assign permissions to Super Admin role
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $permissionNames = array_column($permissions, 'name');
            $superAdminRole->givePermissionTo($permissionNames);
        }

        $this->command->info('Announcement permissions created successfully!');
    }
}
