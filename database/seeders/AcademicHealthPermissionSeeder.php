<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permission for the Academic Configuration Health report.
 *
 * The page was reachable by anyone with an account — its only middleware was
 * auth:web — so a lecturer, a receptionist or a cleaner could read the school's
 * staffing gaps, fee coverage and enrolment figures. Every other admin module
 * is permission-gated; this one was missed.
 *
 * Usage: php artisan db:seed --class=AcademicHealthPermissionSeeder
 *
 * Safe to re-run.
 */
class AcademicHealthPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'academic-health-view', 'guard_name' => 'web'],
            [
                'name' => 'academic-health-view',
                'guard_name' => 'web',
                'group' => 'Academic',
                'title' => 'View Academic Health Report',
            ]
        );

        $this->command?->info($permission->wasRecentlyCreated
            ? 'academic-health-view created.'
            : 'academic-health-view already present.');

        // Given to whoever already administers the academic structure, since
        // the report is about that structure and links straight into the
        // screens that change it. A hardcoded role list would be wrong on any
        // installation that named its roles differently.
        $source = Permission::where('name', 'academic-department-view')
            ->where('guard_name', 'web')->first();

        if (!$source) {
            $this->command?->warn(
                'academic-department-view does not exist, so nobody was granted the report. '
                . 'Grant academic-health-view by hand.'
            );

            return;
        }

        $roles = Role::whereHas('permissions', fn ($q) => $q->where('id', $source->id))->get();

        if ($roles->isEmpty()) {
            $this->command?->warn('No role holds academic-department-view either — grant it by hand.');

            return;
        }

        $granted = [];

        foreach ($roles as $role) {
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
                $granted[] = $role->name;
            }
        }

        $this->command?->info($granted
            ? 'Granted to ' . implode(', ', $granted) . '.'
            : 'Already held by everyone with academic-department-view.');

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
