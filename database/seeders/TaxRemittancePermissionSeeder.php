<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the tax remittance screen.
 *
 * Usage: php artisan db:seed --class=TaxRemittancePermissionSeeder
 *
 * Safe to re-run: keyed on name and guard, and it only ever adds a grant —
 * a role deliberately given one of these and not the permission it mirrors
 * keeps it.
 *
 * Without this the sidebar entry is hidden behind @can and the screen looks
 * like it was never deployed rather than looking unauthorised — the same trap
 * the Daybook fell into.
 */
class TaxRemittancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Each names the permission it should be held by the same people as.
        //
        // Seeing what is owed to DGI and CNPS is a payroll report, so it goes
        // with the other payroll reporting. Recording a payment moves money
        // and posts to the ledger, so it goes with the action that pays a
        // payroll rather than with reading one. Voiding reverses a posted
        // entry, so it mirrors the permission for reversing journal entries —
        // deliberately narrower than recording.
        $permissions = [
            ['name' => 'tax-remittance-view', 'guard_name' => 'web', 'group' => 'Payroll', 'title' => 'View Tax Remittance', 'mirrors' => 'payroll-report'],
            ['name' => 'tax-remittance-create', 'guard_name' => 'web', 'group' => 'Payroll', 'title' => 'Record Tax Remittance', 'mirrors' => 'payroll-action'],
            ['name' => 'tax-remittance-void', 'guard_name' => 'web', 'group' => 'Payroll', 'title' => 'Void Tax Remittance', 'mirrors' => 'journal-entry-reverse'],
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
            'Tax remittance permissions: %d created, %d already present.',
            $created,
            count($permissions) - $created
        ));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Give a permission to whoever already holds the one it mirrors.
     *
     * A hardcoded role list is wrong on every installation that named its
     * roles differently. Copying the roles from the permission this one
     * belongs beside gets it right anywhere.
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
            '  %-24s -> %s',
            $permission->name,
            $added ? 'granted to ' . implode(', ', $added) : 'already held by everyone with ' . $mirrors
        ));
    }
}
