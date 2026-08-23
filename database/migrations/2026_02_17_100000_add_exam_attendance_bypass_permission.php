<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if permission already exists
        $exists = DB::table('permissions')->where('name', 'exam-attendance-bypass')->exists();
        
        if (!$exists) {
            DB::table('permissions')->insert([
                'name' => 'exam-attendance-bypass',
                'group' => 'Exam',
                'title' => 'Bypass Exam Attendance',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Get the newly created permission
            $permission = DB::table('permissions')->where('name', 'exam-attendance-bypass')->first();
            
            // Assign to Super Admin role
            $superAdmin = DB::table('roles')->where('name', 'Super Admin')->first();
            if ($superAdmin && $permission) {
                $alreadyAssigned = DB::table('role_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->where('role_id', $superAdmin->id)
                    ->exists();
                    
                if (!$alreadyAssigned) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permission->id,
                        'role_id' => $superAdmin->id,
                    ]);
                }
            }
            
            // Assign to Admin role
            $admin = DB::table('roles')->where('name', 'Admin')->first();
            if ($admin && $permission) {
                $alreadyAssigned = DB::table('role_has_permissions')
                    ->where('permission_id', $permission->id)
                    ->where('role_id', $admin->id)
                    ->exists();
                    
                if (!$alreadyAssigned) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permission->id,
                        'role_id' => $admin->id,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = DB::table('permissions')->where('name', 'exam-attendance-bypass')->first();
        
        if ($permission) {
            // Remove role assignments
            DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
            
            // Remove permission
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
