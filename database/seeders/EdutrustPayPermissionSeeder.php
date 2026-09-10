<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the EdutrustPay reporting settings screen.
 *
 * Usage: php artisan db:seed --class=EdutrustPayPermissionSeeder
 *
 * Safe to re-run: keyed on name and guard, and it only ever adds a grant.
 *
 * Without this the sidebar entry is hidden behind @can and the screen looks like
 * it was never deployed rather than looking unauthorised.
 */
class EdutrustPayPermissionSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Each names the permission it should be held by the same people as.
         *
         * These credentials decide whose figures reach the body, and pasting a
         * wrong one stops reporting with NO visible error here — from the
         * console this institution simply goes quiet. So they sit with the
         * administrative permissions rather than the finance ones:
         *
         *   view    mirrors audit-log-view — oversight of how this institution
         *           is being reported on, which is the same register.
         *   update  mirrors journal-entry-reverse, the narrowest and most
         *           consequential accounting act in this system. Changing where
         *           the figures go belongs beside it, and its holders are a
         *           proper SUBSET of the view holders, which is what a narrower
         *           permission should be.
         *   test    mirrors audit-log-view: it sends a heartbeat carrying no
         *           figures, so it is a diagnostic rather than a change.
         *
         * role-edit was the first choice for update and was wrong on this
         * installation: it is held by Human Resource and Registery, so it would
         * have let them change the credentials while Admin and IT Administrator,
         * who can see them, could not. Mirroring is only as good as the
         * permission you mirror — check who actually holds it.
         */
        $permissions = [
            ['name' => 'edutrustpay-view', 'guard_name' => 'web', 'group' => 'EdutrustPay', 'title' => 'View EdutrustPay Settings', 'mirrors' => 'audit-log-view'],
            ['name' => 'edutrustpay-update', 'guard_name' => 'web', 'group' => 'EdutrustPay', 'title' => 'Change EdutrustPay Settings', 'mirrors' => 'journal-entry-reverse'],
            ['name' => 'edutrustpay-test', 'guard_name' => 'web', 'group' => 'EdutrustPay', 'title' => 'Test EdutrustPay Connection', 'mirrors' => 'audit-log-view'],
        ];

        $created = 0;

        foreach ($permissions as $permission) {
            $mirrors = $permission['mirrors'];
            unset($permission['mirrors']);

            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );

            if ($row->wasRecentlyCreated) {
                $created++;
            }

            $this->grantLike($row, $mirrors);
        }

        $this->command?->info(sprintf(
            'EdutrustPay permissions: %d created, %d already present.',
            $created,
            count($permissions) - $created
        ));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Give a permission to whoever already holds the one it mirrors.
     *
     * A hardcoded role list is wrong on every installation that named its roles
     * differently. Copying the roles from the permission this one belongs beside
     * gets it right anywhere.
     */
    protected function grantLike(Permission $permission, string $mirrors): void
    {
        $source = Permission::where('name', $mirrors)->where('guard_name', $permission->guard_name)->first();

        if (!$source) {
            $this->command?->warn(
                "{$permission->name}: '{$mirrors}' does not exist, so nobody was granted it. Grant it by hand."
            );

            return;
        }

        $roles = Role::whereHas('permissions', fn ($q) => $q->where('id', $source->id))->get();

        if ($roles->isEmpty()) {
            $this->command?->warn("{$permission->name}: no role holds '{$mirrors}' either — grant it by hand.");

            return;
        }

        $added = [];

        foreach ($roles as $role) {
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
                $added[] = $role->name;
            }
        }

        $this->command?->info(sprintf(
            '  %-22s -> %s',
            $permission->name,
            $added ? 'granted to ' . implode(', ', $added) : 'already held by everyone with ' . $mirrors
        ));
    }
}
