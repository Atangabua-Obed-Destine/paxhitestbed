<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Un-posting a journal entry was open to anyone who could reach the route.
 * It now needs its own permission, given to the roles that can already post
 * (posting and un-posting are the same authority over the books).
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $permission = Permission::firstOrCreate(['name' => 'journal-entry-unpost', 'guard_name' => 'web']);

            foreach (Role::all() as $role) {
                $give = $role->name === 'Super Admin';
                if (!$give) {
                    try {
                        $give = $role->hasPermissionTo('journal-entry-post');
                    } catch (\Throwable $e) {
                        $give = false;
                    }
                }
                if ($give) {
                    $role->givePermissionTo($permission);
                }
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Non-fatal: the permission can be created from the roles screen.
        }
    }

    public function down(): void
    {
        try {
            Permission::where('name', 'journal-entry-unpost')->delete();
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }
};
