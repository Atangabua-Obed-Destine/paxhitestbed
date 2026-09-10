<?php

namespace Database\Seeders;

use App\Services\PermissionSync;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every permission seeder, in the right order.
 *
 * This used to be a hand-kept list of 30 seeders, and it had already fallen
 * five behind — Daybook, Academic Health, Tax Remittance, Letterhead and
 * EdutrustPay were missing from it. It now asks PermissionSync, the same place
 * `php artisan permissions:sync` asks, so there is one definition of "all the
 * permission seeders" and it cannot go stale.
 *
 * DatabaseSeeder calls this on a fresh install, after AdminSeeder has created
 * the roles these seeders grant to.
 */
class SyncAllPermissionsSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionSync::seeders() as $seeder) {
            $this->call($seeder);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
