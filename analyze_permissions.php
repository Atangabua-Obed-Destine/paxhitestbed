<?php
/**
 * Analyze Permissions - Find missing permissions
 * Run: php analyze_permissions.php
 * Or visit: http://localhost/paxhiproduction/analyze_permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

echo "<pre>";
echo "=== PERMISSION ANALYSIS ===\n\n";

// Get all permissions from the database
$dbPermissions = Permission::orderBy('group')->orderBy('name')->get();
$totalDbPermissions = $dbPermissions->count();

echo "Total permissions in database: $totalDbPermissions\n\n";

// Get all unique groups
$groups = Permission::select('group')->distinct()->orderBy('group')->pluck('group')->toArray();
echo "Permission Groups in database (" . count($groups) . "):\n";
echo str_repeat("-", 60) . "\n";

foreach ($groups as $group) {
    $count = Permission::where('group', $group)->count();
    $groupName = $group ?: '(No Group)';
    echo sprintf("  %-40s: %d permissions\n", $groupName, $count);
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "PERMISSIONS WITHOUT GROUP:\n";
echo str_repeat("-", 60) . "\n";

$noGroup = Permission::whereNull('group')->orWhere('group', '')->get();
if ($noGroup->count() > 0) {
    foreach ($noGroup as $perm) {
        echo "  - {$perm->name} (title: {$perm->title})\n";
    }
} else {
    echo "  None found\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "PERMISSIONS WITHOUT TITLE:\n";
echo str_repeat("-", 60) . "\n";

$noTitle = Permission::whereNull('title')->orWhere('title', '')->get();
if ($noTitle->count() > 0) {
    foreach ($noTitle as $perm) {
        echo "  - {$perm->name} (group: {$perm->group})\n";
    }
} else {
    echo "  None found\n";
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "FULL PERMISSION LIST BY GROUP:\n";
echo str_repeat("-", 60) . "\n";

foreach ($groups as $group) {
    $groupName = $group ?: '(No Group)';
    echo "\n[$groupName]\n";
    
    $perms = Permission::where('group', $group)->orderBy('name')->get();
    foreach ($perms as $perm) {
        $title = $perm->title ?: '(No title)';
        echo "  - {$perm->name} => $title\n";
    }
}

echo "\n";
echo "</pre>";
