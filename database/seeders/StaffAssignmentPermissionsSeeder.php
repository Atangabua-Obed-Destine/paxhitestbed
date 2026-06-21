<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StaffAssignmentPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'staff-assignment-index',
                'group' => 'Staff Assignment',
                'title' => 'View',
                'guard_name' => 'web',
            ],
            [
                'name' => 'staff-assignment-create',
                'group' => 'Staff Assignment',
                'title' => 'Create',
                'guard_name' => 'web',
            ],
            [
                'name' => 'staff-assignment-edit',
                'group' => 'Staff Assignment',
                'title' => 'Edit',
                'guard_name' => 'web',
            ],
            [
                'name' => 'staff-assignment-delete',
                'group' => 'Staff Assignment',
                'title' => 'Delete',
                'guard_name' => 'web',
            ],
        ];

        foreach ($permissions as $permission) {
            // Check if permission already exists
            $exists = DB::table('permissions')
                ->where('name', $permission['name'])
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert($permission);
                $this->command->info("✓ Created permission: {$permission['name']}");
            } else {
                $this->command->warn("⚠ Permission already exists: {$permission['name']}");
            }
        }

        $this->command->info("\n✅ Staff Assignment permissions seeded successfully!");
    }
}
