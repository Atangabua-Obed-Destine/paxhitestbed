<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for Fees → Second Instalment catch-up.
 *
 * Applying it bills students real money and can leave them owing, so both start
 * with Super Admin and nobody else. The school passes them to the Bursar on
 * Settings → Roles if it chooses to.
 *
 * Found and run by `php artisan permissions:sync`, which also runs after every
 * migrate. Safe to re-run: creates only what is missing, only ever adds grants.
 */
class SecondInstalmentCatchUpPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'second-instalment-catchup-view', 'group' => 'Fees', 'title' => 'View Second Instalment Catch-up'],
            ['name' => 'second-instalment-catchup-apply', 'group' => 'Fees', 'title' => 'Raise Second Instalment Catch-up Fees'],
        ];

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();

        foreach ($permissions as $permission) {
            $permission['guard_name'] = 'web';

            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                $permission
            );

            if ($superAdmin && !$superAdmin->hasPermissionTo($row)) {
                $superAdmin->givePermissionTo($row);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Second Instalment catch-up permissions synced to Super Admin only.');
    }
}
