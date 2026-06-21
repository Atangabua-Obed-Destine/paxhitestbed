<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\User;

echo "=== Checking Staff Permissions ===\n\n";

// Get staff with ID 0003
$staff = User::where('staff_id', 'LIKE', '%0003%')->first();

if (!$staff) {
    echo "❌ Staff with ID 0003 not found!\n";
    exit;
}

echo "✓ Staff: {$staff->first_name} {$staff->last_name} (ID: {$staff->id})\n";
echo "  Email: {$staff->email}\n";
echo "  Status: " . ($staff->status ? 'Active' : 'Inactive') . "\n\n";

// Get roles
$roles = $staff->roles;
echo "Roles ({$roles->count()}):\n";
foreach ($roles as $role) {
    echo "  - {$role->name} (slug: {$role->slug})\n";
}

// Get all permissions
$permissions = $staff->getAllPermissions();
echo "\nAll Permissions ({$permissions->count()}):\n";

// Check for subject-marking permission specifically
$hasSubjectMarking = $staff->can('subject-marking');
echo "\n📋 Required Permission: subject-marking\n";
echo "Has Permission: " . ($hasSubjectMarking ? '✅ YES' : '❌ NO') . "\n";

if (!$hasSubjectMarking) {
    echo "\n⚠️  This is why you're getting 404 error!\n";
    echo "The staff needs 'subject-marking' permission to access the page.\n\n";
    echo "Solution:\n";
    echo "1. Go to Admin → Settings → Role Management\n";
    echo "2. Edit the role: {$roles->first()->name}\n";
    echo "3. Grant 'Subject Marking' permission\n";
    echo "4. Save and try again\n";
} else {
    echo "\n✅ Permission is granted. The 404 might be from something else.\n";
}

// Check other related permissions
echo "\nOther Related Permissions:\n";
$relatedPerms = [
    'subject-marking',
    'subject-result', 
    'subject-view',
    'subject-create',
    'subject-edit',
    'subject-delete',
];

foreach ($relatedPerms as $perm) {
    $has = $staff->can($perm);
    echo "  - {$perm}: " . ($has ? '✅' : '❌') . "\n";
}

// List all permissions for debugging
echo "\nComplete Permission List:\n";
foreach ($permissions as $permission) {
    echo "  • {$permission->name}\n";
}

echo "\n";
