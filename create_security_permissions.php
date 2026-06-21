<?php
/**
 * Create Security Module Permissions
 * 
 * This script creates the 11 security permissions and updates
 * the PermissionSeeder to include them.
 * 
 * Run: php create_security_permissions.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

echo "=== Creating Security Module Permissions ===\n\n";

$securityPermissions = [
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

$created = 0;
$existed = 0;

foreach ($securityPermissions as $perm) {
    $existing = Permission::where('name', $perm['name'])->first();
    
    if ($existing) {
        // Update title and group if they were empty
        if (empty($existing->title) || empty($existing->group)) {
            $existing->update([
                'title' => $perm['title'],
                'group' => $perm['group'],
            ]);
            echo "[UPDATED] {$perm['name']} - Title: {$perm['title']}, Group: {$perm['group']}\n";
        } else {
            echo "[EXISTS]  {$perm['name']}\n";
        }
        $existed++;
    } else {
        Permission::create($perm);
        echo "[CREATED] {$perm['name']} - {$perm['title']}\n";
        $created++;
    }
}

echo "\n=== Summary ===\n";
echo "Created: {$created}\n";
echo "Already Existed: {$existed}\n";
echo "Total Security Permissions: " . count($securityPermissions) . "\n";

// Optionally assign to super admin role
echo "\n=== Assigning to Super Admin Role ===\n";

$superAdminRole = \Spatie\Permission\Models\Role::where('name', 'Super Admin')->first();

if ($superAdminRole) {
    $permissionNames = array_column($securityPermissions, 'name');
    $superAdminRole->givePermissionTo($permissionNames);
    echo "Assigned all security permissions to Super Admin role.\n";
} else {
    echo "Super Admin role not found. Skipping assignment.\n";
}

echo "\n=== Done! ===\n";
echo "\nNote: To use these permissions in the sidebar, update your sidebar.blade.php\n";
echo "to use @canany with these permission names instead of is_admin checks.\n";
