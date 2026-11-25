<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for course groups (Academic > Course groups).
 *
 * Usage: php artisan db:seed --class=CourseGroupPermissionSeeder
 * (permissions:sync, run after every migrate, picks it up by its name.)
 *
 * Safe to re-run: keyed on name and guard, and it only ever adds a grant.
 * Each permission goes to whoever already holds the matching course
 * permission, so the people who manage courses manage their groups.
 */
class CourseGroupPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'course-group-view', 'title' => 'View', 'mirrors' => 'subject-view'],
            ['name' => 'course-group-create', 'title' => 'Create', 'mirrors' => 'subject-create'],
            ['name' => 'course-group-edit', 'title' => 'Edit', 'mirrors' => 'subject-edit'],
            ['name' => 'course-group-delete', 'title' => 'Delete', 'mirrors' => 'subject-delete'],
        ];

        $created = 0;
        foreach ($permissions as $permission) {
            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                ['name' => $permission['name'], 'guard_name' => 'web', 'group' => 'Course Group', 'title' => $permission['title']]
            );
            if ($row->wasRecentlyCreated) {
                $created++;
            }
            $this->grantLike($row, $permission['mirrors']);
        }

        $this->command?->info(sprintf('Course group permissions: %d created, %d already present.', $created, count($permissions) - $created));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Give a permission to whoever already holds the one it mirrors. */
    protected function grantLike(Permission $permission, string $mirrors): void
    {
        $source = Permission::where('name', $mirrors)->where('guard_name', $permission->guard_name)->first();
        if (!$source) {
            $this->command?->warn("{$permission->name}: '{$mirrors}' does not exist, so nobody was granted it. Grant it by hand.");

            return;
        }
        foreach (Role::whereHas('permissions', fn ($q) => $q->where('id', $source->id))->get() as $role) {
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }
}
