<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class WebCMSPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Admissions Page Permissions
            ['name' => 'admissions-page-view', 'group' => 'Admissions Page', 'title' => 'View'],
            ['name' => 'admissions-page-edit', 'group' => 'Admissions Page', 'title' => 'Edit'],

            // Campus Life Permissions
            ['name' => 'campus-life-view', 'group' => 'Campus Life', 'title' => 'View'],
            ['name' => 'campus-life-create', 'group' => 'Campus Life', 'title' => 'Create'],
            ['name' => 'campus-life-edit', 'group' => 'Campus Life', 'title' => 'Edit'],
            ['name' => 'campus-life-delete', 'group' => 'Campus Life', 'title' => 'Delete'],
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

        $this->command->info('Web CMS permissions created successfully!');
    }
}

