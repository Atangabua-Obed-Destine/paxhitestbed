<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the staff ID card artwork screen, following the same shape as
 * 2026_08_17_130400_add_chat_permissions.
 *
 * The student equivalent already ships as `id-card-setting-view`; this is its
 * counterpart on the Human Resource side.
 */
return new class extends Migration
{
    protected array $names = [
        ['name' => 'staff-id-card-setting-view', 'group' => 'Human Resource', 'title' => 'View Staff ID Card Setting'],
        ['name' => 'staff-id-card-setting-edit', 'group' => 'Human Resource', 'title' => 'Edit Staff ID Card Setting'],
    ];

    public function up(): void
    {
        foreach ($this->names as $permission) {
            $permission['guard_name'] = 'web';
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );
        }

        $role = Role::where('name', 'Super Admin')->first();
        if ($role) {
            $role->syncPermissions(Permission::all());
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', array_column($this->names, 'name'))->delete();
    }
};
