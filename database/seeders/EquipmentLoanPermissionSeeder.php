<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for equipment loans.
 *
 *   equipment-desk     run a lending desk: scan, lend and check in
 *   equipment-view     see what is out, the follow-up list and histories
 *   equipment-manage   the equipment list, stickers, desks and desk settings
 *
 * Each goes to whoever already does the nearest thing in Inventory, so the
 * people who issue stock today can lend equipment tomorrow. The library's scan
 * desk needs nothing new: it uses the library's own book-issue-action.
 *
 * Safe to re-run; permissions:sync picks it up by its name after migrate.
 */
class EquipmentLoanPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'equipment-desk', 'title' => 'Run the lending desk', 'mirrors' => ['item-issue-action']],
            ['name' => 'equipment-view', 'title' => 'See loans and histories', 'mirrors' => ['item-issue-view', 'item-issue-action']],
            ['name' => 'equipment-manage', 'title' => 'Manage equipment and desks', 'mirrors' => ['item-create']],
        ];

        foreach ($permissions as $permission) {
            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                ['name' => $permission['name'], 'guard_name' => 'web', 'group' => 'Equipment loans', 'title' => $permission['title']]
            );

            $sources = Permission::whereIn('name', $permission['mirrors'])->where('guard_name', 'web')->pluck('id');
            if ($sources->isEmpty()) {
                $this->command?->warn("{$permission['name']}: none of " . implode(', ', $permission['mirrors']) . ' exists; grant it by hand.');
                continue;
            }

            foreach (Role::whereHas('permissions', fn ($q) => $q->whereIn('id', $sources))->get() as $role) {
                if (!$role->hasPermissionTo($row)) {
                    $role->givePermissionTo($row);
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
