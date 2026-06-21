<?php
/**
 * Restore Role Assignments After DB Restore
 * 
 * Run: php restore_roles.php
 * 
 * This script restores role assignments that were lost during DB restore.
 * Assignments are based on:
 * - Class routine teaching assignments (need Lecturer role to appear in dropdown)
 * - User designation_id matching to system roles
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ROLE RESTORATION SCRIPT ===\n\n";

// Role IDs
$ROLE_LECTURER = 13;          // slug: teacher
$ROLE_ACCOUNTANT = 10;        // slug: accountant
$ROLE_EXECUTIVE_ASSISTANT = 16; // slug: executive-assistant
$ROLE_ADMINISTRATOR = 15;     // slug: administrator
$ROLE_ACADEMIC_AFFAIRS = 17;  // slug: academic-affairs
$ROLE_IT_SUPPORT = 19;        // slug: it-support-technician

$modelType = 'App\User';  // Single backslash - this is what existing working entries use

// Show current state
$currentCount = DB::table('model_has_roles')->count();
echo "Current model_has_roles entries: {$currentCount}\n\n";

// ==============================
// 1. LECTURER ROLE ASSIGNMENTS
// ==============================
// All users who have class_routines assigned to them MUST have Lecturer role
$teacherIds = DB::table('class_routines')
    ->select('teacher_id')
    ->distinct()
    ->pluck('teacher_id')
    ->toArray();

echo "Users with class routines: " . implode(', ', $teacherIds) . "\n";

// Also add users with Lecturer designation (designation_id=7) even if no routines yet
$lecturerDesignationIds = DB::table('users')
    ->where('designation_id', 7)
    ->where('status', 1)
    ->pluck('id')
    ->toArray();

echo "Users with Lecturer designation: " . implode(', ', $lecturerDesignationIds) . "\n";

// Merge both lists
$needLecturerRole = array_unique(array_merge($teacherIds, $lecturerDesignationIds));
sort($needLecturerRole);

echo "All users needing Lecturer role: " . implode(', ', $needLecturerRole) . "\n\n";

$inserted = 0;
foreach ($needLecturerRole as $userId) {
    $exists = DB::table('model_has_roles')
        ->where('role_id', $ROLE_LECTURER)
        ->where('model_type', 'LIKE', '%User%')
        ->where('model_id', $userId)
        ->exists();
    
    if (!$exists) {
        DB::table('model_has_roles')->insert([
            'role_id' => $ROLE_LECTURER,
            'model_type' => $modelType,
            'model_id' => $userId,
        ]);
        $user = DB::table('users')->find($userId);
        echo "  [+] Lecturer role assigned to: {$user->first_name} {$user->last_name} (ID={$userId})\n";
        $inserted++;
    } else {
        $user = DB::table('users')->find($userId);
        echo "  [=] Already has Lecturer role: {$user->first_name} {$user->last_name} (ID={$userId})\n";
    }
}
echo "Lecturer role: {$inserted} new assignments\n\n";

// ==============================
// 2. DESIGNATION-BASED ROLE ASSIGNMENTS
// ==============================
echo "=== Designation-based roles ===\n";

// Mapping: designation_id => role_id
// 4 (Director) => 15 (Administrator)
// 5 (Accountant) => 10 (Accountant)
// 6 (Executive Assistant) => 16 (Executive Assistant)  
// 3 (Registrar) => 17 (Academic Affairs)
// 8 (IT Support Technician) => 19 (IT Support Technician)
// 2 (Vice Chancellor) => 15 (Administrator) - Vice Chancellor gets Administrator role
$designationToRole = [
    2 => [$ROLE_ADMINISTRATOR],           // Vice Chancellor => Administrator
    3 => [$ROLE_ACADEMIC_AFFAIRS],        // Registrar => Academic Affairs
    4 => [$ROLE_ADMINISTRATOR],           // Director => Administrator
    5 => [$ROLE_ACCOUNTANT],              // Accountant => Accountant
    6 => [$ROLE_EXECUTIVE_ASSISTANT],     // Executive Assistant => Executive Assistant
    8 => [$ROLE_IT_SUPPORT],             // IT Support => IT Support Technician
];

$designationInserted = 0;
foreach ($designationToRole as $desigId => $roleIds) {
    $users = DB::table('users')
        ->where('designation_id', $desigId)
        ->where('status', 1)
        ->get();
    
    foreach ($users as $user) {
        foreach ($roleIds as $roleId) {
            $exists = DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', 'LIKE', '%User%')
                ->where('model_id', $user->id)
                ->exists();
            
            if (!$exists) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => $modelType,
                    'model_id' => $user->id,
                ]);
                $roleName = DB::table('roles')->find($roleId)->name;
                echo "  [+] {$roleName} role assigned to: {$user->first_name} {$user->last_name} (ID={$user->id})\n";
                $designationInserted++;
            }
        }
    }
}
echo "Designation-based roles: {$designationInserted} new assignments\n\n";

// ==============================
// 3. VERIFICATION
// ==============================
echo "=== FINAL STATE ===\n";
$finalCount = DB::table('model_has_roles')->count();
echo "Total model_has_roles entries: {$finalCount} (was {$currentCount})\n\n";

$entries = DB::table('model_has_roles')
    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
    ->join('users', 'users.id', '=', 'model_has_roles.model_id')
    ->select('users.id', 'users.first_name', 'users.last_name', 'roles.name as role_name')
    ->orderBy('users.id')
    ->orderBy('roles.name')
    ->get();

$currentUser = null;
foreach ($entries as $e) {
    if ($currentUser !== $e->id) {
        echo "\nUser #{$e->id} ({$e->first_name} {$e->last_name}):\n";
        $currentUser = $e->id;
    }
    echo "  - {$e->role_name}\n";
}

echo "\n\n=== TEACHER DROPDOWN CHECK ===\n";
$teacherDropdown = DB::table('users')
    ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
    ->where('roles.slug', 'teacher')
    ->where('users.status', 1)
    ->select('users.id', 'users.first_name', 'users.last_name')
    ->distinct()
    ->orderBy('users.id')
    ->get();

echo "Teachers now visible in dropdown: " . count($teacherDropdown) . "\n";
foreach ($teacherDropdown as $t) {
    echo "  - {$t->first_name} {$t->last_name} (ID={$t->id})\n";
}

// Check if all class routine teachers are in the dropdown
$missingTeachers = DB::table('class_routines')
    ->select('teacher_id')
    ->distinct()
    ->whereNotIn('teacher_id', $teacherDropdown->pluck('id')->toArray())
    ->pluck('teacher_id')
    ->toArray();

if (empty($missingTeachers)) {
    echo "\n✓ All class routine teachers are now in the dropdown!\n";
} else {
    echo "\n✗ Still missing from dropdown: " . implode(', ', $missingTeachers) . "\n";
}

echo "\nDone!\n";
