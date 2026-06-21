<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class PaymentPlanPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Usage: php artisan db:seed --class=PaymentPlanPermissionSeeder
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            ['name' => 'payment-plan.index', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'View All'],
            ['name' => 'payment-plan.create', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'Create'],
            ['name' => 'payment-plan.store', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'Store'],
            ['name' => 'payment-plan.show', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'View Details'],
            ['name' => 'payment-plan.edit', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'Edit'],
            ['name' => 'payment-plan.update', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'Update'],
            ['name' => 'payment-plan.destroy', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'Delete'],
            ['name' => 'payment-plan.pay', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'Process Payment'],
            ['name' => 'payment-plan.approve', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'Approve'],
            ['name' => 'payment-plan.cancel', 'guard_name' => 'web', 'group' => 'Payment Plan', 'title' => 'Cancel'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']], 
                $permission
            );
        }

        echo "✓ Payment Plan permissions created successfully!\n";

        // Grant all permissions to Chancellor role
        $chancellorRole = Role::where('name', 'Chancellor')->first();
        
        if ($chancellorRole) {
            foreach ($permissions as $permission) {
                $chancellorRole->givePermissionTo($permission['name']);
            }
            echo "✓ All permissions granted to Chancellor role\n";
        } else {
            echo "⚠ Warning: Chancellor role not found! Please grant permissions manually.\n";
        }

        // Also grant to Super Admin and Admin
        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();
        
        foreach ($adminRoles as $role) {
            foreach ($permissions as $permission) {
                $role->givePermissionTo($permission['name']);
            }
            echo "✓ All permissions granted to {$role->name} role\n";
        }

        echo "\nAll done! Payment Plan permissions are now available.\n";
    }
}
