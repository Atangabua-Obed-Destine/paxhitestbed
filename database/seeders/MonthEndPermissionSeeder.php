<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Month-end work: whoever prepares a bank reconciliation must be able to tick
 * the statement's movements on it (bank-reconciliation-edit). The Accountant
 * could start one (create) but not work on it. Completing it stays with the
 * roles that hold bank-reconciliation-complete, so preparing and signing off
 * remain separate.
 *
 * Usage: php artisan db:seed --class=MonthEndPermissionSeeder
 * (permissions:sync, run after every migrate, picks it up by its name.)
 * Safe to re-run: it only ever adds a grant.
 */
class MonthEndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $edit = Permission::where('name', 'bank-reconciliation-edit')->where('guard_name', 'web')->first();
        $create = Permission::where('name', 'bank-reconciliation-create')->where('guard_name', 'web')->first();

        if (!$edit || !$create) {
            $this->command?->warn('Bank reconciliation permissions are missing; run the accounting permission seeder first.');

            return;
        }

        $granted = 0;
        foreach ($create->roles as $role) {
            if (!$role->hasPermissionTo($edit)) {
                $role->givePermissionTo($edit);
                $granted++;
            }
        }

        $this->command?->info("Month-end permissions: bank-reconciliation-edit granted to {$granted} more role(s).");

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
