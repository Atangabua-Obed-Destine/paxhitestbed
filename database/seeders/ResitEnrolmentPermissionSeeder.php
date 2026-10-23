<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The permission to raise a resit enrolment for a carried-over course.
 *
 * Separate from student-enroll-single and from student-enroll-adddrop, because
 * it is a different kind of act: it creates an enrolment in a semester the
 * ordinary enrolment screens deliberately refuse to offer, and it overrides
 * resit requests the student themselves declined. Whoever may register a
 * course for next semester should not automatically be able to do that.
 *
 * The filename is what makes it discoverable - PermissionSync matches
 * /Permissions?Seeder$/ and runs after every migrate.
 */
class ResitEnrolmentPermissionSeeder extends Seeder
{
    public const PERMISSION = 'student-enroll-resit';

    public function run(): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => static::PERMISSION, 'guard_name' => 'web'],
            ['title' => 'Raise Resit Enrolments', 'group' => 'Enrollments']
        );

        $superAdmin = Role::where('name', 'Super Admin')->first();

        if ($superAdmin && !$superAdmin->hasPermissionTo($permission)) {
            $superAdmin->givePermissionTo($permission);
        }
    }
}
