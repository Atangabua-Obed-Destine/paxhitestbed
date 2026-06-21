<?php
/**
 * Compare Seeder vs Database Permissions
 * Find permissions defined in seeder but missing from database
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;

echo "<pre>";
echo "=== COMPARE SEEDER VS DATABASE ===\n\n";

// Get all permissions from database
$dbPermissions = Permission::pluck('name')->toArray();
echo "Database permissions: " . count($dbPermissions) . "\n\n";

// Read the PermissionSeeder file
$seederPath = __DIR__ . '/database/seeders/PermissionSeeder.php';
$seederContent = file_get_contents($seederPath);

// Extract permission names from seeder
preg_match_all("/\['name'\s*=>\s*'([^']+)'/", $seederContent, $matches);
$seederPermissions = array_unique($matches[1]);
echo "Seeder permissions: " . count($seederPermissions) . "\n\n";

// Find permissions in seeder but not in database
$missingFromDb = array_diff($seederPermissions, $dbPermissions);
echo str_repeat("=", 60) . "\n";
echo "MISSING FROM DATABASE (in seeder but not in DB):\n";
echo str_repeat("-", 60) . "\n";

if (count($missingFromDb) > 0) {
    foreach ($missingFromDb as $perm) {
        echo "  ❌ $perm\n";
    }
} else {
    echo "  ✓ All seeder permissions exist in database\n";
}

// Find permissions in database but not in seeder (extra)
$extraInDb = array_diff($dbPermissions, $seederPermissions);
echo "\n";
echo str_repeat("=", 60) . "\n";
echo "EXTRA IN DATABASE (in DB but not in seeder): " . count($extraInDb) . "\n";
echo str_repeat("-", 60) . "\n";

// Group them for easier reading
$extraGrouped = [];
foreach ($extraInDb as $perm) {
    $dbPerm = Permission::where('name', $perm)->first();
    $group = $dbPerm->group ?? '(No Group)';
    if (!isset($extraGrouped[$group])) {
        $extraGrouped[$group] = [];
    }
    $extraGrouped[$group][] = $perm;
}

ksort($extraGrouped);
foreach ($extraGrouped as $group => $perms) {
    echo "\n[$group]\n";
    foreach ($perms as $perm) {
        echo "  + $perm\n";
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "SUMMARY:\n";
echo "  Seeder: " . count($seederPermissions) . "\n";
echo "  Database: " . count($dbPermissions) . "\n";
echo "  Missing from DB: " . count($missingFromDb) . "\n";
echo "  Extra in DB: " . count($extraInDb) . "\n";
echo "  Target: 686\n";
echo "  Gap: " . (686 - count($dbPermissions)) . " permissions needed\n";
echo str_repeat("=", 60) . "\n";

echo "\n</pre>";
