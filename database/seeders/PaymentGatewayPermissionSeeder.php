<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The permission for the Mobile Money settings screen.
 *
 * MobileMoneyConfigController and its sidebar link have always checked
 * `payment-gateway-manage`, but nothing ever created it. So the screen opened
 * for full administrators only, and there was not even a checkbox on the role
 * page to give it to anyone else.
 *
 * Granted to Super Admin only. The screen holds the MTN and Orange API
 * credentials, so any other role gets it only by somebody deliberately ticking
 * it on the role page.
 *
 * Safe to re-run: creates the permission only if it is missing, and only ever
 * adds the one grant.
 */
class PaymentGatewayPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'payment-gateway-manage', 'guard_name' => 'web'],
            ['group' => 'Setting', 'title' => 'Mobile Money Settings']
        );

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();

        if (!$superAdmin) {
            $this->command?->warn('payment-gateway-manage: there is no Super Admin role yet, so nobody was granted it.');
        } elseif (!$superAdmin->hasPermissionTo($permission)) {
            $superAdmin->givePermissionTo($permission);
            $this->command?->info('  payment-gateway-manage -> granted to Super Admin');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
