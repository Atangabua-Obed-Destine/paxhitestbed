<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The exam-attendance-bypass permission was referenced in the UI (@can) but never
 * seeded, so only Super Admin could use the final-exam bypass. Create it and grant
 * it to Super Admin and any role that already manages exam attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $perm = Permission::firstOrCreate(['name' => 'exam-attendance-bypass', 'guard_name' => 'web']);

            foreach (Role::all() as $role) {
                $grant = $role->name === 'Super Admin';
                if (!$grant) {
                    try { $grant = $role->hasPermissionTo('exam-attendance'); } catch (\Throwable $e) { $grant = false; }
                }
                if ($grant) {
                    $role->givePermissionTo($perm);
                }
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Non-fatal: a fresh install seeds this via PermissionSeeder instead.
        }
    }

    public function down(): void
    {
        try {
            $perm = Permission::where('name', 'exam-attendance-bypass')->where('guard_name', 'web')->first();
            if ($perm) {
                $perm->delete();
            }
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
