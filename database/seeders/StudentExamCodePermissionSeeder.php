<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for HND exam codes (Admission → HND Exam Codes).
 *
 * Viewing shows the codes and prints the commission's list; managing records and
 * removes them. A wrong code means a student sits the national exam under
 * someone else's registration, so both start with Super Admin only — the school
 * passes them to the Registrar, who deals with CNOENC, on Settings → Roles.
 *
 * Found and run by `php artisan permissions:sync`, which also runs after every
 * migrate. Safe to re-run: creates only what is missing, only ever adds grants.
 */
class StudentExamCodePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'student-exam-code-view', 'group' => 'Student', 'title' => 'View HND Exam Codes'],
            ['name' => 'student-exam-code-manage', 'group' => 'Student', 'title' => 'Record HND Exam Codes'],
        ];

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();

        foreach ($permissions as $permission) {
            $permission['guard_name'] = 'web';

            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                $permission
            );

            if ($superAdmin && !$superAdmin->hasPermissionTo($row)) {
                $superAdmin->givePermissionTo($row);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('HND exam code permissions synced to Super Admin only.');
    }
}
