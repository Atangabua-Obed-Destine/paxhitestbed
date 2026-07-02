<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SecurityPermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
    [
        'name' => 'security-dashboard-view',
        'title' => 'View Security Dashboard',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-audit-view',
        'title' => 'View Audit Logs',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-audit-export',
        'title' => 'Export Audit Logs',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-login-history-view',
        'title' => 'View Login History',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-active-sessions-view',
        'title' => 'View Active Sessions',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-active-sessions-terminate',
        'title' => 'Terminate Active Sessions',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-failed-logins-view',
        'title' => 'View Failed Logins',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-ip-whitelist-view',
        'title' => 'View IP Whitelist',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-ip-whitelist-manage',
        'title' => 'Manage IP Whitelist',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-blocked-ips-view',
        'title' => 'View Blocked IPs',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
    [
        'name' => 'security-blocked-ips-manage',
        'title' => 'Manage Blocked IPs',
        'group' => 'Security',
        'guard_name' => 'web',
    ],
];

        $permNames = [];
        foreach ($permissions as $perm) {
            if (is_array($perm)) {
                $name = $perm['name'] ?? $perm[0] ?? null;
                if (!$name) continue;
                
                $guard = $perm['guard_name'] ?? 'web';
                $title = $perm['title'] ?? null;
                $group = $perm['group'] ?? null;
                
                $updateData = [];
                if ($title) $updateData['title'] = $title;
                if ($group) $updateData['group'] = $group;

                Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                    $updateData
                );
                $permNames[] = $name;
            } else {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
                $permNames[] = $perm;
            }
        }

        // Try to assign to sensible roles
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) $adminRole->givePermissionTo($permNames);
        
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) $superAdminRole->givePermissionTo($permNames);
    }
}
