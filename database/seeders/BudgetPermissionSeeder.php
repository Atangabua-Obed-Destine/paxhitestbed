<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class BudgetPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Usage: php artisan db:seed --class=BudgetPermissionSeeder
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            // Budget Permissions
            ['name' => 'budget-view', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'View All Budgets'],
            ['name' => 'budget-create', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Create Budget'],
            ['name' => 'budget-edit', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Edit Budget'],
            ['name' => 'budget-delete', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Delete Budget'],
            ['name' => 'budget-approve', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Approve Budget'],
            ['name' => 'budget-activate', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Activate Budget'],
            ['name' => 'budget-close', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Close Budget'],
            ['name' => 'budget-cancel', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Cancel Budget'],
            
            // Budget Allocation Permissions
            ['name' => 'budget-allocation-create', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Create Allocation'],
            ['name' => 'budget-allocation-edit', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Edit Allocation'],
            ['name' => 'budget-allocation-delete', 'guard_name' => 'web', 'group' => 'Budget', 'title' => 'Delete Allocation'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']], 
                $permission
            );
        }

        echo "✓ Budget permissions created successfully!\n";

        // Grant all permissions to Chancellor role
        $chancellorRole = Role::where('name', 'Chancellor')->first();
        
        if ($chancellorRole) {
            foreach ($permissions as $permission) {
                $chancellorRole->givePermissionTo($permission['name']);
            }
            echo "✓ All permissions granted to Chancellor role\n";
        } else {
            echo "⚠ Warning: Chancellor role not found!\n";
        }

        // Also grant to Super Admin and Admin
        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();
        
        foreach ($adminRoles as $role) {
            foreach ($permissions as $permission) {
                $role->givePermissionTo($permission['name']);
            }
            echo "✓ All permissions granted to {$role->name} role\n";
        }

        // Grant view-only permissions to Accountant role
        $accountantRole = Role::where('name', 'Accountant')->first();
        
        if ($accountantRole) {
            $viewPermissions = ['budget-view'];
            foreach ($viewPermissions as $permName) {
                $accountantRole->givePermissionTo($permName);
            }
            echo "✓ View permissions granted to Accountant role\n";
        }

        echo "\n✅ Budget permission seeding completed!\n";
    }
}
