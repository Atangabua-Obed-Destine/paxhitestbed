<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permissions for the security screens.
 *
 * These routes were behind `auth:web` alone, which meant any member of staff
 * who could sign in — a lecturer, a bursar — could open Security → Users and
 * switch off a Super Admin's two-factor authentication, read the security log,
 * or change the password policy. The screens that guard the system were the
 * least guarded in it.
 *
 * Usage: php artisan db:seed --class=SecurityPermissionSeeder
 */
class SecurityPermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            ['name' => 'security-view', 'guard_name' => 'web', 'group' => 'Security', 'title' => 'View Security Screens'],
            ['name' => 'security-manage', 'guard_name' => 'web', 'group' => 'Security', 'title' => 'Block Users, Toggle 2FA, Manage IPs'],
            ['name' => 'security-settings', 'guard_name' => 'web', 'group' => 'Security', 'title' => 'Change Security Settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );
        }

        echo "✓ Security permissions created successfully!\n";

        // Only the two administrative roles. Deliberately not handed to every
        // role that happens to exist: the point of this seeder is that these
        // screens stop being open to everyone.
        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();

        foreach ($adminRoles as $role) {
            foreach ($permissions as $permission) {
                $role->givePermissionTo($permission['name']);
            }
            echo "✓ Granted to role: {$role->name}\n";
        }

        if ($adminRoles->isEmpty()) {
            echo "⚠ Warning: no Super Admin or Admin role found. Grant these by hand or nobody can open Security.\n";
        }

        // Spatie caches the permission map; without this the new permissions
        // are not visible until the cache happens to expire.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        echo "All done! Security screens are now restricted.\n";
    }
}
