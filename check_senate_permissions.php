<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "=== Senate Deliberation Permissions ===\n\n";

$perms = Permission::where('name', 'like', 'senate-deliberation%')->get();
if ($perms->isEmpty()) {
    echo "⚠ NO senate-deliberation permissions found in DB!\n";
    echo "  Run: php assign_senate_deliberation_permissions.php\n";
} else {
    echo "Found " . $perms->count() . " permissions:\n";
    foreach ($perms as $p) {
        echo "  ✓ {$p->name} (id: {$p->id})\n";
    }
}

echo "\n=== Roles with these permissions ===\n\n";
$roles = Role::all();
foreach ($roles as $role) {
    $rolePerms = $role->permissions()->where('name', 'like', 'senate-deliberation%')->pluck('name');
    if ($rolePerms->isNotEmpty()) {
        echo "Role: {$role->name}\n";
        foreach ($rolePerms as $rp) {
            echo "  - {$rp}\n";
        }
        echo "\n";
    }
}

echo "Done.\n";
