<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the admission fees report.
 *
 * The screen had none at all: neither its controller nor its routes carried a
 * permission check, so anyone who could reach the admin area could see every
 * applicant's fee, take a walk-in cash payment against it, and — since the
 * delete was added — remove a fee outright. None of that appeared in the role
 * editor either, so there was nothing to grant or withhold.
 *
 * Split three ways because they are genuinely different acts: reading the
 * report, taking money, and removing a charge.
 */
return new class extends Migration
{
    protected array $names = [
        ['name' => 'admission-fees-report-view', 'group' => 'Admission Fees', 'title' => 'View Admission Fees Report'],
        ['name' => 'admission-fees-report-walk-in', 'group' => 'Admission Fees', 'title' => 'Record Walk-in Payment'],
        ['name' => 'admission-fees-report-delete', 'group' => 'Admission Fees', 'title' => 'Remove an Admission Fee'],
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
