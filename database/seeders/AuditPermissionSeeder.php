<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class AuditPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Usage: php artisan db:seed --class=AuditPermissionSeeder
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            ['name' => 'audit-log-view', 'guard_name' => 'web', 'group' => 'Audit Trail', 'title' => 'View'],
            ['name' => 'audit-log-export', 'guard_name' => 'web', 'group' => 'Audit Trail', 'title' => 'Export'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']], 
                $permission
            );
        }

        echo "✓ Audit Trail permissions created successfully!\n";

        // Optionally, grant these permissions to admin roles
        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin'])->get();
        
        foreach ($adminRoles as $role) {
            $role->givePermissionTo(['audit-log-view', 'audit-log-export']);
            echo "✓ Permissions granted to role: {$role->name}\n";
        }

        echo "\nAll done! You can now access the Audit Trail at /admin/audit-log\n";
    }
}
