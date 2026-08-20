<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for managing the rows of the Income & Expenditure sheet.
 *
 * Kept separate from budget-create / budget-edit on purpose: preparing a budget
 * is an everyday task, whereas changing the structure of the sheet itself
 * affects every budget ever produced and belongs to fewer people.
 */
return new class extends Migration
{
    protected array $names = [
        ['name' => 'budget-line-view', 'group' => 'Budget', 'title' => 'View Budget Lines'],
        ['name' => 'budget-line-create', 'group' => 'Budget', 'title' => 'Create Budget Line'],
        ['name' => 'budget-line-edit', 'group' => 'Budget', 'title' => 'Edit Budget Line'],
        ['name' => 'budget-line-delete', 'group' => 'Budget', 'title' => 'Delete Budget Line'],
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
