<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class PaymentReceiptPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Usage: php artisan db:seed --class=PaymentReceiptPermissionSeeder
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            ['name' => 'payment-receipt-verify', 'guard_name' => 'web', 'group' => 'Payment Verification', 'title' => 'Verify'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']], 
                $permission
            );
        }

        echo "✓ Payment Receipt permissions created successfully!\n";

        // Optionally, grant these permissions to admin roles
        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();
        
        foreach ($adminRoles as $role) {
            $role->givePermissionTo('payment-receipt-verify');
            echo "✓ Permissions granted to role: {$role->name}\n";
        }

        echo "\nAll done! Admins can now verify payment receipts.\n";
    }
}
