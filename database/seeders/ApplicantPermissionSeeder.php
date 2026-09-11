<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the Applicants screen (Admission → Applicants).
 *
 * Each goes to whoever already holds the matching application permission, so
 * the people who work with applications can work with the accounts behind
 * them — and changing an applicant's password follows editing applications,
 * the same way the student password change follows the student screen.
 *
 * Found and run by `php artisan permissions:sync`, which also runs after every
 * migrate. Safe to re-run: creates only what is missing, only ever adds grants.
 */
class ApplicantPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'applicant-view', 'guard_name' => 'web', 'group' => 'Applicant', 'title' => 'View Applicants', 'mirrors' => 'application-view'],
            ['name' => 'applicant-edit', 'guard_name' => 'web', 'group' => 'Applicant', 'title' => 'Edit, Disable and Send Reset Links', 'mirrors' => 'application-edit'],
            ['name' => 'applicant-password-change', 'guard_name' => 'web', 'group' => 'Applicant', 'title' => 'Change Applicant Password', 'mirrors' => 'application-edit'],
        ];

        foreach ($permissions as $permission) {
            $mirrors = $permission['mirrors'];
            unset($permission['mirrors']);

            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );

            $source = Permission::where('name', $mirrors)->where('guard_name', 'web')->first();

            if (!$source) {
                $this->command?->warn("{$row->name}: '{$mirrors}' does not exist, so nobody was granted it. Grant it by hand.");
                continue;
            }

            foreach (Role::whereHas('permissions', fn ($q) => $q->where('id', $source->id))->get() as $role) {
                if (!$role->hasPermissionTo($row)) {
                    $role->givePermissionTo($row);
                }
            }
        }

        // Signing in as an applicant is the most powerful thing on the screen,
        // so it does not follow the application permissions like the rest.
        // Super Admin only; anybody else is given it deliberately, by hand.
        $impersonate = Permission::firstOrCreate(
            ['name' => 'applicant-impersonate', 'guard_name' => 'web'],
            ['group' => 'Applicant', 'title' => 'Sign In As An Applicant']
        );

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();

        if (!$superAdmin) {
            $this->command?->warn('applicant-impersonate: there is no Super Admin role yet, so nobody was granted it.');
        } elseif (!$superAdmin->hasPermissionTo($impersonate)) {
            $superAdmin->givePermissionTo($impersonate);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
