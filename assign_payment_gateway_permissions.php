<?php

/**
 * Assign Mobile Money Gateway configuration permission to the Admin role.
 * Run once from the browser:
 *   http://localhost/paxhitestbed/assign_payment_gateway_permissions.php
 *
 * Idempotent — safe to re-run.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

try {
    echo "<h2>Assigning Payment Gateway (Mobile Money) Permission</h2>";

    $adminRole = Role::where('name', 'Admin')->orWhere('id', 1)->first();
    if (!$adminRole) {
        echo "<p style='color:red'>Admin role not found.</p>";
        exit;
    }
    echo "<p>Target role: <strong>{$adminRole->name}</strong> (ID: {$adminRole->id})</p><hr>";

    $permissions = ['payment-gateway-manage'];

    $created = 0; $assigned = 0; $already = 0;
    echo "<ul>";
    foreach ($permissions as $name) {
        $perm = Permission::where('name', $name)->where('guard_name', 'web')->first();
        if (!$perm) {
            $perm = Permission::create(['name' => $name, 'guard_name' => 'web']);
            echo "<li>Created: <strong>{$name}</strong></li>";
            $created++;
        }
        if ($adminRole->hasPermissionTo($name)) {
            echo "<li>Already assigned: <strong>{$name}</strong></li>";
            $already++;
        } else {
            $adminRole->givePermissionTo($perm);
            echo "<li>Assigned: <strong>{$name}</strong></li>";
            $assigned++;
        }
    }
    echo "</ul><hr>";

    // Clear the permission cache so the guard picks it up on the next request.
    app()['cache']->forget('spatie.permission.cache');

    echo "<h3>Summary</h3>";
    echo "<ul>";
    echo "<li>Created: <strong>{$created}</strong></li>";
    echo "<li>Newly assigned: <strong>{$assigned}</strong></li>";
    echo "<li>Already assigned: <strong>{$already}</strong></li>";
    echo "</ul>";
    echo "<p style='color:green;font-size:16px'>Done. You should now see the &quot;Mobile Money (MTN / Orange)&quot; sidebar entry after logging out and back in.</p>";
    echo "<p><a href='/paxhitestbed/admin/mobile-money-config'>&rarr; Open Mobile Money configuration</a></p>";

} catch (\Throwable $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
