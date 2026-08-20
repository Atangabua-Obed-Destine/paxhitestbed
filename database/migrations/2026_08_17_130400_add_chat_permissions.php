<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the chat module, following the same shape as
 * 2026_08_07_053934_add_degree_type_permissions.
 */
return new class extends Migration
{
    protected array $names = [
        ['name' => 'chat-setting-view', 'group' => 'Chat', 'title' => 'View Settings'],
        ['name' => 'chat-setting-edit', 'group' => 'Chat', 'title' => 'Edit Settings'],
        ['name' => 'chat-conversation-view', 'group' => 'Chat', 'title' => 'View Conversations'],
        ['name' => 'chat-conversation-delete', 'group' => 'Chat', 'title' => 'Delete Conversations'],
        ['name' => 'chat-knowledge-view', 'group' => 'Chat', 'title' => 'View Knowledge Base'],
        ['name' => 'chat-knowledge-create', 'group' => 'Chat', 'title' => 'Create Knowledge Entry'],
        ['name' => 'chat-knowledge-edit', 'group' => 'Chat', 'title' => 'Edit Knowledge Entry'],
        ['name' => 'chat-knowledge-delete', 'group' => 'Chat', 'title' => 'Delete Knowledge Entry'],
    ];

    public function up(): void
    {
        foreach ($this->names as $permission) {
            $permission['guard_name'] = 'web';
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );
        }

        $role = Role::where('name', 'Super Admin')->first();
        if ($role) {
            $role->syncPermissions(Permission::all());
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', array_column($this->names, 'name'))->delete();
    }
};
