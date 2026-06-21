<?php
/**
 * Fix Missing Permission Groups and Titles
 * Run: php fix_missing_permission_metadata.php
 * Or visit: http://localhost/paxhiproduction/fix_missing_permission_metadata.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

echo "<pre>";
echo "=== FIX MISSING PERMISSION METADATA ===\n\n";

// Find permissions without group or title
$missingMetadata = Permission::where(function($q) {
    $q->whereNull('group')
      ->orWhere('group', '')
      ->orWhereNull('title')
      ->orWhere('title', '');
})->get();

echo "Found " . $missingMetadata->count() . " permissions with missing metadata:\n";
echo str_repeat("-", 60) . "\n";

foreach ($missingMetadata as $perm) {
    echo "  Name: {$perm->name}\n";
    echo "  Group: " . ($perm->group ?: '(empty)') . "\n";
    echo "  Title: " . ($perm->title ?: '(empty)') . "\n";
    echo "  ---\n";
}

// Define the fixes for known missing permissions
$fixes = [
    // Dynamic Popup permissions
    'dynamic-popup-view' => ['group' => 'Dynamic Popup', 'title' => 'View'],
    'dynamic-popup-create' => ['group' => 'Dynamic Popup', 'title' => 'Create'],
    'dynamic-popup-edit' => ['group' => 'Dynamic Popup', 'title' => 'Edit'],
    'dynamic-popup-delete' => ['group' => 'Dynamic Popup', 'title' => 'Delete'],
    
    // Attendance Setting permissions - should be in Attendance group
    'attendance-setting-view' => ['group' => 'Attendance', 'title' => 'View Kiosk Settings'],
    'attendance-setting-edit' => ['group' => 'Attendance', 'title' => 'Edit Kiosk Settings'],
    
    // Class Hub Reports
    'class-hub-reports-view' => ['group' => 'Attendance', 'title' => 'View Class Hub Reports'],
    'class-hub-reports-export' => ['group' => 'Attendance', 'title' => 'Export Class Hub Reports'],
];

echo "\n\nApplying fixes...\n";
echo str_repeat("-", 60) . "\n";

$fixed = 0;
foreach ($fixes as $permName => $metadata) {
    $perm = Permission::where('name', $permName)->first();
    if ($perm) {
        $needsUpdate = false;
        
        if (empty($perm->group) && !empty($metadata['group'])) {
            $needsUpdate = true;
        }
        if (empty($perm->title) && !empty($metadata['title'])) {
            $needsUpdate = true;
        }
        
        if ($needsUpdate) {
            $perm->update($metadata);
            echo "✓ Fixed: {$permName}\n";
            echo "  Group: {$metadata['group']}\n";
            echo "  Title: {$metadata['title']}\n";
            $fixed++;
        } else {
            echo "→ Already has metadata: {$permName}\n";
        }
    } else {
        echo "⚠ Not found: {$permName}\n";
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "Fixed $fixed permissions\n\n";

// Clear permission cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "✓ Permission cache cleared\n";

echo "\n</pre>";
