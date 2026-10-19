<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The permission that lets an administrator correct a published mark in place.
 *
 * Correcting one wrong mark normally means unpublishing the result, unlocking
 * the exam rows and asking the lecturer to type it again. This permission is the
 * deliberate shortcut: the holder corrects it on the subject-marking screen,
 * with a reason, recorded in the same history the school already reads.
 *
 * Granted to nobody by default beyond Super Admin, which MissingPermissionsSeeder
 * gives every permission to. Who else holds it is a decision for the school, made
 * on the roles screen - which is the point of it being a permission rather than a
 * role check in the code.
 *
 * Discovered automatically: PermissionSync matches seeders named *PermissionSeeder,
 * and permissions:sync runs after every migrate. Adds only, never removes.
 */
class MarkingOverridePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'subject-marking-direct-edit',
                'title' => 'Correct Marks Directly',
                'group' => 'Course Final',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                $permission + ['guard_name' => 'web']
            );
        }

        // Super Admin holds everything anyway, but saying so here means a fresh
        // install does not depend on the order seeders happen to run in.
        $superAdmin = Role::where('name', 'Super Admin')->first();

        if ($superAdmin) {
            $superAdmin->givePermissionTo(collect($permissions)->pluck('name')->all());
        }
    }
}
