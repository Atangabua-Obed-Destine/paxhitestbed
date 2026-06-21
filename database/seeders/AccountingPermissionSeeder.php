<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class AccountingPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Usage: php artisan db:seed --class=AccountingPermissionSeeder
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            // Chart of Accounts Permissions
            ['name' => 'chart-of-accounts-view', 'guard_name' => 'web', 'group' => 'Chart of Accounts', 'title' => 'View Chart of Accounts'],
            ['name' => 'chart-of-accounts-create', 'guard_name' => 'web', 'group' => 'Chart of Accounts', 'title' => 'Create Account'],
            ['name' => 'chart-of-accounts-edit', 'guard_name' => 'web', 'group' => 'Chart of Accounts', 'title' => 'Edit Account'],
            ['name' => 'chart-of-accounts-delete', 'guard_name' => 'web', 'group' => 'Chart of Accounts', 'title' => 'Delete Account'],
            ['name' => 'chart-of-accounts-activate', 'guard_name' => 'web', 'group' => 'Chart of Accounts', 'title' => 'Activate/Deactivate Account'],
            
            // Accounting Period (Fiscal Year) Permissions
            ['name' => 'accounting-period-view', 'guard_name' => 'web', 'group' => 'Accounting Period', 'title' => 'View Accounting Periods'],
            ['name' => 'accounting-period-create', 'guard_name' => 'web', 'group' => 'Accounting Period', 'title' => 'Create Accounting Period'],
            ['name' => 'accounting-period-edit', 'guard_name' => 'web', 'group' => 'Accounting Period', 'title' => 'Edit Accounting Period'],
            ['name' => 'accounting-period-delete', 'guard_name' => 'web', 'group' => 'Accounting Period', 'title' => 'Delete Accounting Period'],
            ['name' => 'accounting-period-close', 'guard_name' => 'web', 'group' => 'Accounting Period', 'title' => 'Close Accounting Period'],
            ['name' => 'accounting-period-reopen', 'guard_name' => 'web', 'group' => 'Accounting Period', 'title' => 'Reopen Accounting Period'],
            
            // Journal Entry Permissions
            ['name' => 'journal-entry-view', 'guard_name' => 'web', 'group' => 'Journal Entry', 'title' => 'View Journal Entries'],
            ['name' => 'journal-entry-create', 'guard_name' => 'web', 'group' => 'Journal Entry', 'title' => 'Create Manual Journal Entry'],
            ['name' => 'journal-entry-edit', 'guard_name' => 'web', 'group' => 'Journal Entry', 'title' => 'Edit Journal Entry'],
            ['name' => 'journal-entry-delete', 'guard_name' => 'web', 'group' => 'Journal Entry', 'title' => 'Delete Journal Entry'],
            ['name' => 'journal-entry-post', 'guard_name' => 'web', 'group' => 'Journal Entry', 'title' => 'Post Journal Entry'],
            ['name' => 'journal-entry-reverse', 'guard_name' => 'web', 'group' => 'Journal Entry', 'title' => 'Reverse Journal Entry'],
            
            // Transaction Mapping Permissions
            ['name' => 'transaction-mapping-view', 'guard_name' => 'web', 'group' => 'Transaction Mapping', 'title' => 'View Transaction Mappings'],
            ['name' => 'transaction-mapping-manage', 'guard_name' => 'web', 'group' => 'Transaction Mapping', 'title' => 'Manage Individual Mappings'],
            ['name' => 'transaction-mapping-settings', 'guard_name' => 'web', 'group' => 'Transaction Mapping', 'title' => 'Manage Default Settings'],
            ['name' => 'transaction-mapping-remap', 'guard_name' => 'web', 'group' => 'Transaction Mapping', 'title' => 'Remap Transactions'],
            
            // Accounting Reports Permissions
            ['name' => 'general-ledger-view', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'View General Ledger'],
            ['name' => 'general-ledger-export', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'Export General Ledger'],
            ['name' => 'trial-balance-view', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'View Trial Balance'],
            ['name' => 'trial-balance-export', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'Export Trial Balance'],
            ['name' => 'balance-sheet-view', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'View Balance Sheet'],
            ['name' => 'balance-sheet-export', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'Export Balance Sheet'],
            ['name' => 'income-statement-view', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'View Income Statement'],
            ['name' => 'income-statement-export', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'Export Income Statement'],
            ['name' => 'cash-flow-view', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'View Cash Flow Statement'],
            ['name' => 'cash-flow-export', 'guard_name' => 'web', 'group' => 'Accounting Reports', 'title' => 'Export Cash Flow Statement'],
            
            // Payment Account Permissions
            ['name' => 'payment-account-view', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'View Payment Accounts'],
            ['name' => 'payment-account-create', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'Create Payment Account'],
            ['name' => 'payment-account-edit', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'Edit Payment Account'],
            ['name' => 'payment-account-delete', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'Delete Payment Account'],
            ['name' => 'payment-account-book', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'View Account Book'],
            ['name' => 'payment-account-deposit', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'Make Deposits'],
            ['name' => 'payment-account-withdraw', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'Make Withdrawals'],
            ['name' => 'payment-account-transaction-edit', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'Edit Transactions'],
            ['name' => 'payment-account-transaction-delete', 'guard_name' => 'web', 'group' => 'Payment Account', 'title' => 'Delete Transactions'],
            
            // Payment Account Transfer Permissions
            ['name' => 'payment-account-transfer-view', 'guard_name' => 'web', 'group' => 'Payment Account Transfer', 'title' => 'View Transfers'],
            ['name' => 'payment-account-transfer-create', 'guard_name' => 'web', 'group' => 'Payment Account Transfer', 'title' => 'Create Transfer'],
            ['name' => 'payment-account-transfer-edit', 'guard_name' => 'web', 'group' => 'Payment Account Transfer', 'title' => 'Edit Transfer'],
            ['name' => 'payment-account-transfer-delete', 'guard_name' => 'web', 'group' => 'Payment Account Transfer', 'title' => 'Delete Transfer'],
            
            // Payment Account Reports Permissions
            ['name' => 'payment-account-report-view', 'guard_name' => 'web', 'group' => 'Payment Account Report', 'title' => 'View Payment Account Reports'],
            ['name' => 'payment-account-report-export', 'guard_name' => 'web', 'group' => 'Payment Account Report', 'title' => 'Export Payment Account Reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']], 
                $permission
            );
        }

        echo "✓ Accounting permissions created successfully!\n";
        echo "  - Chart of Accounts: 5 permissions\n";
        echo "  - Accounting Period: 6 permissions\n";
        echo "  - Journal Entry: 6 permissions\n";
        echo "  - Transaction Mapping: 4 permissions\n";
        echo "  - Accounting Reports: 10 permissions\n";
        echo "  - Payment Account: 9 permissions\n";
        echo "  - Payment Account Transfer: 4 permissions\n";
        echo "  - Payment Account Report: 2 permissions\n";
        echo "  Total: " . count($permissions) . " permissions\n\n";

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

        // Grant view permissions to Accountant role (if exists)
        $accountantRole = Role::where('name', 'Accountant')->first();
        
        if ($accountantRole) {
            $accountantPermissions = [
                'chart-of-accounts-view',
                'accounting-period-view',
                'journal-entry-view',
                'journal-entry-create',
                'transaction-mapping-view',
                'general-ledger-view',
                'general-ledger-export',
                'trial-balance-view',
                'trial-balance-export',
                'balance-sheet-view',
                'balance-sheet-export',
                'income-statement-view',
                'income-statement-export',
                'cash-flow-view',
                'cash-flow-export',
                'payment-account-view',
                'payment-account-book',
                'payment-account-deposit',
                'payment-account-withdraw',
                'payment-account-transfer-view',
                'payment-account-transfer-create',
                'payment-account-report-view',
                'payment-account-report-export',
            ];
            
            foreach ($accountantPermissions as $permissionName) {
                $accountantRole->givePermissionTo($permissionName);
            }
            echo "✓ View and report permissions granted to Accountant role\n";
        }

        echo "\n✅ Accounting permissions seeding completed!\n";
    }
}
