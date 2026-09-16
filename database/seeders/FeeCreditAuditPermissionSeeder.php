<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for Fees → Credit audit.
 *
 * Viewing shows the audit. Correcting voids duplicated student credit and
 * reposts fee entries in the general ledger — it moves posted cash and income
 * figures — so both are granted to Super Admin and nobody else. Grant them to
 * the Bursar or Accountant on Settings → Roles if the school chooses to.
 *
 * Found and run by `php artisan permissions:sync`, which also runs after every
 * migrate. Safe to re-run: creates only what is missing, only ever adds grants.
 */
class FeeCreditAuditPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'fee-credit-audit-view', 'group' => 'Fees', 'title' => 'View Fee Credit Audit'],
            ['name' => 'fee-credit-audit-correct', 'group' => 'Fees', 'title' => 'Correct Fee Credit and Ledger'],
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

        $this->command?->info('Fee credit audit permissions synced to Super Admin only.');
    }
}
