<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The permission to move a student to another programme.
 *
 * Separate from student-enroll-single, which gates ordinary enrolment, because
 * a programme change is a different kind of act: it reissues the student's
 * matricule and rewrites their assigned fees. Whoever may enrol a student for
 * next semester should not automatically be able to do that.
 *
 * The filename is what makes it discoverable - PermissionSync matches
 * /Permissions?Seeder$/ and runs after every migrate.
 */
class ProgrammeChangePermissionSeeder extends Seeder
{
    public const PERMISSION = 'student-enroll-programme-change';

    public function run(): void
    {
        // The columns this table actually has are group and title, matching
        // every other permission row.
        $permission = Permission::updateOrCreate(
            ['name' => static::PERMISSION, 'guard_name' => 'web'],
            ['title' => 'Change Student Programme', 'group' => 'Enrollments']
        );

        $superAdmin = Role::where('name', 'Super Admin')->first();

        if ($superAdmin && !$superAdmin->hasPermissionTo($permission)) {
            $superAdmin->givePermissionTo($permission);
        }
    }
}
