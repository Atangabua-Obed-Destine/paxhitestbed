<?php

// Insert Student Archive Permissions
// Run this file once to add permissions for Student Archives feature

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Check if permissions already exist
    $exists = DB::table('permissions')
        ->whereIn('name', [
            'student-archive-view',
            'student-archive-edit',
            'student-archive-password-change'
        ])
        ->count();

    if ($exists > 0) {
        echo "Permissions already exist. Skipping...\n";
        exit;
    }

    // Insert permissions
    $permissions = [
        [
            'name' => 'student-archive-view',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'student-archive-edit',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'student-archive-password-change',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];

    DB::table('permissions')->insert($permissions);
    
    echo "✓ Successfully inserted 3 permissions:\n";
    echo "  - student-archive-view\n";
    echo "  - student-archive-edit\n";
    echo "  - student-archive-password-change\n\n";
    
    // Assign to Super Admin role (role_id = 1)
    $permissionIds = DB::table('permissions')
        ->whereIn('name', [
            'student-archive-view',
            'student-archive-edit',
            'student-archive-password-change'
        ])
        ->pluck('id');

    $rolePermissions = [];
    foreach ($permissionIds as $permissionId) {
        $rolePermissions[] = [
            'permission_id' => $permissionId,
            'role_id' => 1, // Super Admin
        ];
    }

    DB::table('role_has_permissions')->insert($rolePermissions);
    
    echo "✓ Assigned permissions to Super Admin role\n\n";
    echo "Installation complete!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
