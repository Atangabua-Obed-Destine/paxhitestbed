<?php
/**
 * Find Missing Permissions
 * 
 * This script extracts all permissions from sidebar.blade.php and other views
 * and compares them with the database to find missing permissions.
 * 
 * Run: php find_missing_permissions.php
 * Or visit: http://localhost/paxhiproduction/find_missing_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

echo "<pre>";
echo "=== FIND MISSING PERMISSIONS ===\n\n";

// Read the sidebar file
$sidebarPath = __DIR__ . '/resources/views/admin/layouts/inc/sidebar.blade.php';
$sidebarContent = file_get_contents($sidebarPath);

// Extract all permission names from @can and @canany directives
$permissionsFromSidebar = [];

// Match @can('permission-name')
preg_match_all("/@can\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/", $sidebarContent, $matches);
$permissionsFromSidebar = array_merge($permissionsFromSidebar, $matches[1]);

// Match @canany(['permission1', 'permission2', ...])
preg_match_all("/@canany\s*\(\s*\[([^\]]+)\]/", $sidebarContent, $matches);
foreach ($matches[1] as $match) {
    // Extract individual permissions from the array string
    preg_match_all("/['\"]([^'\"]+)['\"]/", $match, $perms);
    $permissionsFromSidebar = array_merge($permissionsFromSidebar, $perms[1]);
}

// Also match inline @canany without array brackets
preg_match_all("/@canany\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $sidebarContent, $matches);
$permissionsFromSidebar = array_merge($permissionsFromSidebar, $matches[1], $matches[2]);

// Get unique permissions
$permissionsFromSidebar = array_unique($permissionsFromSidebar);
sort($permissionsFromSidebar);

echo "Found " . count($permissionsFromSidebar) . " unique permissions in sidebar\n\n";

// Get all permissions from database
$dbPermissions = Permission::pluck('name')->toArray();

echo "Found " . count($dbPermissions) . " permissions in database\n\n";

// Find missing permissions (in sidebar but not in database)
$missing = array_diff($permissionsFromSidebar, $dbPermissions);

echo str_repeat("=", 60) . "\n";
echo "MISSING PERMISSIONS (in sidebar but NOT in database):\n";
echo str_repeat("-", 60) . "\n";

if (count($missing) > 0) {
    foreach ($missing as $perm) {
        echo "  ❌ $perm\n";
    }
} else {
    echo "  ✓ All sidebar permissions exist in database!\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "ALL SIDEBAR PERMISSIONS:\n";
echo str_repeat("-", 60) . "\n";

foreach ($permissionsFromSidebar as $perm) {
    $exists = in_array($perm, $dbPermissions);
    $status = $exists ? "✓" : "❌";
    
    // If exists, check if it has group and title
    if ($exists) {
        $dbPerm = Permission::where('name', $perm)->first();
        $hasMetadata = !empty($dbPerm->group) && !empty($dbPerm->title);
        if (!$hasMetadata) {
            $status = "⚠";
            $perm .= " (missing group/title)";
        }
    }
    
    echo "  $status $perm\n";
}

// Create missing permissions
if (count($missing) > 0) {
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "WOULD YOU LIKE TO CREATE THESE PERMISSIONS?\n";
    echo "Run: php create_sidebar_permissions.php\n";
    echo str_repeat("-", 60) . "\n";
    
    // Output as PHP array for easy copying
    echo "\n// Copy this to create_sidebar_permissions.php:\n";
    echo "\$missingPermissions = [\n";
    foreach ($missing as $perm) {
        // Try to guess the group from the permission name
        $parts = explode('-', $perm);
        $group = ucwords(implode(' ', array_slice($parts, 0, -1)));
        $title = ucfirst(end($parts));
        
        echo "    ['name' => '$perm', 'group' => '$group', 'title' => '$title'],\n";
    }
    echo "];\n";
}

echo "\n</pre>";
