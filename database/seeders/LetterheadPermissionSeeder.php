<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the letterhead screen.
 *
 * Usage: php artisan db:seed --class=LetterheadPermissionSeeder
 */
class LetterheadPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'letterhead-view', 'guard_name' => 'web', 'group' => 'Academic', 'title' => 'View Letterhead'],
            ['name' => 'letterhead-edit', 'guard_name' => 'web', 'group' => 'Academic', 'title' => 'Edit Letterhead'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );
        }

        // The letterhead is institutional branding, so it belongs with the roles
        // that already administer the school rather than with finance.
        foreach (['Super Admin', 'Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo(array_column($permissions, 'name'));
            }
        }

        $this->command?->info('Letterhead permissions seeded.');
    }
}
