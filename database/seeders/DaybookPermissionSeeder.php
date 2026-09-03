<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the Daybook and the Budget Lines screen.
 *
 * These three were created by hand during development and never written into a
 * seeder, so they existed only on the machine they were typed on. On any other
 * installation the permission row is simply absent, and the effect is silent
 * rather than loud: the sidebar hides the entry behind @can, so the Daybook
 * looks like it was never deployed instead of looking unauthorised.
 *
 * Usage: php artisan db:seed --class=DaybookPermissionSeeder
 *
 * Safe to re-run: keyed on name and guard, and the row's own values are all it
 * writes, so nothing else on an existing permission is disturbed.
 */
class DaybookPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Each one names the permission it should be held by the same people
        // as. The Daybook is the Income & Expenditure Sheet's own figures
        // itemised, so whoever may read the sheet may read the book; editing a
        // budget line is an edit like any other on the sheet.
        $permissions = [
            ['name' => 'daybook-view', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'View Daybook', 'mirrors' => 'budget-view'],
            ['name' => 'budget-line-view', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'View Budget Lines', 'mirrors' => 'budget-view'],
            ['name' => 'budget-line-edit', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Edit Budget Line', 'mirrors' => 'budget-edit'],
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
            'Daybook permissions: %d created, %d already present.',
            $created,
            count($permissions) - $created
        ));

        // Spatie caches the permission map; without this the new grants do not
        // take effect until the cache expires on its own.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Give a permission to whoever already holds the one it mirrors.
     *
     * A hardcoded role list is wrong on every installation that named its roles
     * differently — this one has no Chancellor, but does have Accountant and
     * VC, and both hold the sheet permissions. Copying the roles from the
     * permission this one belongs beside gets that right anywhere, and keeps
     * the Daybook and the sheet in the hands of the same people.
     *
     * Only ever adds. A role that was deliberately given the Daybook and not
     * the sheet keeps it.
     */
    protected function grantLike(Permission $permission, string $mirrors): void
    {
        $source = Permission::where('name', $mirrors)->where('guard_name', $permission->guard_name)->first();

        if (!$source) {
            $this->command?->warn(
                "{$permission->name}: '{$mirrors}' does not exist, so nobody was granted it. "
                . 'Run the budget permission seeder first, or grant it by hand.'
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
            '  %-18s -> %s',
            $permission->name,
            $added ? 'granted to ' . implode(', ', $added) : 'already held by everyone with ' . $mirrors
        ));
    }
}
