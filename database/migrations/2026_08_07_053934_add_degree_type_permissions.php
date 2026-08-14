<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            ['name' => 'degree-type-view', 'group' => 'Degree Type', 'title' => 'View'],
            ['name' => 'degree-type-create', 'group' => 'Degree Type', 'title' => 'Create'],
            ['name' => 'degree-type-edit', 'group' => 'Degree Type', 'title' => 'Edit'],
            ['name' => 'degree-type-delete', 'group' => 'Degree Type', 'title' => 'Delete'],
        ];

        foreach ($permissions as $permission) {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'degree-type-view',
            'degree-type-create',
            'degree-type-edit',
            'degree-type-delete',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};
