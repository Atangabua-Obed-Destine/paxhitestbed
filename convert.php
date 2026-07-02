<?php

$files = [
    'assign_catholic_permissions.php' => 'CatholicPermissionSeeder',
    'assign_cms_permissions.php' => 'CmsPermissionSeeder',
    'assign_dynamic_popup_permissions.php' => 'DynamicPopupPermissionSeeder',
    'assign_fee_assignments_history_permission.php' => 'FeeAssignmentsHistoryPermissionSeeder',
    'assign_new_permissions.php' => 'NewPermissionsSeeder',
    'assign_program_semester_fee_permissions.php' => 'ProgramSemesterFeePermissionSeeder',
    'assign_resource_permissions.php' => 'ResourcePermissionSeeder',
    'assign_student_form_a2_permission.php' => 'StudentFormA2PermissionSeeder',
    'create_missing_permissions.php' => 'AdditionalMissingPermissionsSeeder',
    'create_remaining_permissions.php' => 'RemainingPermissionsSeeder',
    'create_security_permissions.php' => 'SecurityPermissionSeeder',
    'create_seeder_permissions.php' => 'SeederPermissionsSeeder',
    'create_sidebar_permissions.php' => 'SidebarPermissionSeeder',
];

$seederTemplate = <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class {CLASS_NAME} extends Seeder
{
    public function run()
    {
        \$permissions = {PERMISSIONS_ARRAY};

        \$permNames = [];
        foreach (\$permissions as \$perm) {
            if (is_array(\$perm)) {
                \$name = \$perm['name'] ?? \$perm[0] ?? null;
                if (!\$name) continue;
                
                \$guard = \$perm['guard_name'] ?? 'web';
                \$title = \$perm['title'] ?? null;
                \$group = \$perm['group'] ?? null;
                
                \$updateData = [];
                if (\$title) \$updateData['title'] = \$title;
                if (\$group) \$updateData['group'] = \$group;

                Permission::updateOrCreate(
                    ['name' => \$name, 'guard_name' => \$guard],
                    \$updateData
                );
                \$permNames[] = \$name;
            } else {
                Permission::firstOrCreate(['name' => \$perm, 'guard_name' => 'web']);
                \$permNames[] = \$perm;
            }
        }

        // Try to assign to sensible roles
        \$adminRole = Role::where('name', 'Admin')->first();
        if (\$adminRole) \$adminRole->givePermissionTo(\$permNames);
        
        \$superAdminRole = Role::where('name', 'Super Admin')->first();
        if (\$superAdminRole) \$superAdminRole->givePermissionTo(\$permNames);
    }
}

PHP;

foreach ($files as $file => $className) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    // Find any array assignment that looks like a permissions array (contains strings like '-view', '-create', or 'name' =>)
    if (preg_match('/\$[a-zA-Z_]+\s*=\s*(\[\s*[^;]*?(?:\'name\'|\'[\w-]+(?:view|create|edit|delete)\')[^;]*?\])\s*;/s', $content, $matches)) {
        $permissionsArrayStr = $matches[1];
        
        $seederCode = str_replace(
            ['{CLASS_NAME}', '{PERMISSIONS_ARRAY}'],
            [$className, $permissionsArrayStr],
            $seederTemplate
        );
        
        file_put_contents(__DIR__ . '/database/seeders/' . $className . '.php', $seederCode);
        echo "Created seeder for {$file}\n";
    } else {
        echo "Could not parse permissions array in {$file}\n";
    }
}
