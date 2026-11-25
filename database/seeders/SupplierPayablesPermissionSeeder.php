<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for supplier bills, supplier payments and payables reports.
 *
 * Usage: php artisan db:seed --class=SupplierPayablesPermissionSeeder
 * (permissions:sync, run after every migrate, picks it up by its name.)
 *
 * Safe to re-run: keyed on name and guard, and it only ever adds a grant.
 *
 * Entering a bill and approving it are separate permissions on purpose:
 * approving posts the cost and the debt to the ledger, so it goes with the
 * people who post journal entries, and a school that wants one person to
 * enter bills and another to approve them can grant them apart.
 */
class SupplierPayablesPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'supplier-bill-view', 'title' => 'View Supplier Bills', 'mirrors' => 'expense-view'],
            ['name' => 'supplier-bill-create', 'title' => 'Enter Supplier Bills', 'mirrors' => 'expense-create'],
            ['name' => 'supplier-bill-approve', 'title' => 'Approve Supplier Bills', 'mirrors' => 'journal-entry-post'],
            ['name' => 'supplier-bill-void', 'title' => 'Void Supplier Bills', 'mirrors' => 'journal-entry-reverse'],
            ['name' => 'supplier-payment-view', 'title' => 'View Supplier Payments', 'mirrors' => 'expense-view'],
            ['name' => 'supplier-payment-create', 'title' => 'Pay Suppliers', 'mirrors' => 'expense-create'],
            ['name' => 'supplier-payment-void', 'title' => 'Void Supplier Payments', 'mirrors' => 'journal-entry-reverse'],
        ];

        $created = 0;

        foreach ($permissions as $permission) {
            $mirrors = $permission['mirrors'];

            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                ['name' => $permission['name'], 'guard_name' => 'web', 'group' => 'Supplier Payables', 'title' => $permission['title']]
            );

            if ($row->wasRecentlyCreated) {
                $created++;
            }

            $this->grantLike($row, $mirrors);
        }

        $this->command?->info(sprintf(
            'Supplier payables permissions: %d created, %d already present.',
            $created,
            count($permissions) - $created
        ));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Give a permission to whoever already holds the one it mirrors. */
    protected function grantLike(Permission $permission, string $mirrors): void
    {
        $source = Permission::where('name', $mirrors)->where('guard_name', $permission->guard_name)->first();

        if (!$source) {
            $this->command?->warn("{$permission->name}: '{$mirrors}' does not exist, so nobody was granted it. Grant it by hand.");

            return;
        }

        $roles = Role::whereHas('permissions', fn ($q) => $q->where('id', $source->id))->get();

        foreach ($roles as $role) {
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
