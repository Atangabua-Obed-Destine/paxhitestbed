<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the mailbox.
 *
 * Every member of staff has a mailbox and can write to any person; that needs
 * no permission. These two cover what reaches many people or changes who
 * students can write to:
 *
 *   mailbox-broadcast  send one message to a group (a programme, a
 *                      department, all staff, any course). Lecturers can
 *                      always write to the classes they teach without it.
 *   mailbox-office     set up the offices students can write to.
 *
 * Each goes to whoever can already send bulk email (email-notify-create).
 * Safe to re-run; permissions:sync picks it up by its name after migrate.
 */
class MailboxPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'mailbox-broadcast', 'title' => 'Send to groups', 'mirrors' => 'email-notify-create'],
            ['name' => 'mailbox-office', 'title' => 'Manage offices', 'mirrors' => 'email-notify-create'],
        ];

        foreach ($permissions as $permission) {
            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                ['name' => $permission['name'], 'guard_name' => 'web', 'group' => 'Mailbox', 'title' => $permission['title']]
            );
            $source = Permission::where('name', $permission['mirrors'])->where('guard_name', 'web')->first();
            if (!$source) {
                $this->command?->warn("{$permission['name']}: '{$permission['mirrors']}' does not exist; grant it by hand.");
                continue;
            }
            foreach (Role::whereHas('permissions', fn ($q) => $q->where('id', $source->id))->get() as $role) {
                if (!$role->hasPermissionTo($row)) {
                    $role->givePermissionTo($row);
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
