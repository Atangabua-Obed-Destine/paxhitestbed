<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ResitPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionPayloads = [
            ['name' => 'resit-request-view', 'group' => 'Resit', 'title' => 'View Requests'],
            ['name' => 'resit-request-transition', 'group' => 'Resit', 'title' => 'Manage Workflow'],
            ['name' => 'resit-request-finance', 'group' => 'Resit', 'title' => 'Finance Review'],
            ['name' => 'resit-request-approve', 'group' => 'Resit', 'title' => 'Academic Approval'],
            ['name' => 'resit-request-schedule', 'group' => 'Resit', 'title' => 'Schedule'],
            ['name' => 'resit-request-reject', 'group' => 'Resit', 'title' => 'Reject'],
        ];

        $permissionNames = [];
        foreach ($permissionPayloads as $payload) {
            $permission = Permission::updateOrCreate(
                ['name' => $payload['name'], 'guard_name' => 'web'],
                $payload + ['guard_name' => 'web']
            );

            $permissionNames[] = $permission->name;
        }

        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();
        foreach ($adminRoles as $role) {
            $role->givePermissionTo($permissionNames);
            $this->command?->info("Granted resit workflow permissions to {$role->name} role");
        }

        $accountantRole = Role::where('name', 'Accountant')->first();
        if ($accountantRole) {
            $accountantPermissions = array_values(array_intersect($permissionNames, [
                'resit-request-view',
                'resit-request-transition',
                'resit-request-finance',
            ]));

            if (!empty($accountantPermissions)) {
                $accountantRole->givePermissionTo($accountantPermissions);
                $this->command?->info('Granted finance resit permissions to Accountant role');
            }
        }

        $teacherRole = Role::where('name', 'Teacher')->first();
        if ($teacherRole && in_array('resit-request-view', $permissionNames, true)) {
            $teacherRole->givePermissionTo('resit-request-view');
            $this->command?->info('Granted resit view permission to Teacher role');
        }
    }
}
