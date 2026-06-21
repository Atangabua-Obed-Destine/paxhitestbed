<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignCmsPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:assign-permissions {role=Admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign CMS permissions (Projects, Leadership, Accreditations, etc.) to a role';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $roleName = $this->argument('role');
        
        $this->info("Assigning CMS permissions to role: {$roleName}");
        $this->newLine();
        
        // Find the role
        $role = Role::where('name', $roleName)->orWhere('id', $roleName)->first();
        
        if (!$role) {
            $this->error("❌ Role '{$roleName}' not found!");
            return 1;
        }
        
        $this->info("✅ Found role: {$role->name} (ID: {$role->id})");
        $this->newLine();
        
        // CMS permissions to assign
        $cmsPermissions = [
            'project-view',
            'project-create',
            'project-edit',
            'project-delete',
            'leadership-team-view',
            'leadership-team-create',
            'leadership-team-edit',
            'leadership-team-delete',
            'accreditation-view',
            'accreditation-create',
            'accreditation-edit',
            'accreditation-delete',
            'history-timeline-view',
            'history-timeline-create',
            'history-timeline-edit',
            'history-timeline-delete',
            'support-service-view',
            'support-service-create',
            'support-service-edit',
            'support-service-delete',
            'admission-date-view',
            'admission-date-create',
            'admission-date-edit',
            'admission-date-delete',
        ];
        
        $assigned = 0;
        $alreadyHad = 0;
        $notFound = [];
        
        $this->withProgressBar($cmsPermissions, function ($permissionName) use ($role, &$assigned, &$alreadyHad, &$notFound) {
            $permission = Permission::where('name', $permissionName)->first();
            
            if ($permission) {
                if (!$role->hasPermissionTo($permissionName)) {
                    $role->givePermissionTo($permissionName);
                    $assigned++;
                } else {
                    $alreadyHad++;
                }
            } else {
                $notFound[] = $permissionName;
            }
        });
        
        $this->newLine(2);
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Summary:");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("✅ Newly assigned: <fg=green>{$assigned}</> permissions");
        $this->line("ℹ️  Already had: <fg=blue>{$alreadyHad}</> permissions");
        $this->line("⚠️  Not found: <fg=yellow>".count($notFound)."</> permissions");
        
        if (count($notFound) > 0) {
            $this->newLine();
            $this->warn("Missing permissions: " . implode(', ', $notFound));
            $this->warn("You may need to run: php artisan db:seed --class=PermissionSeeder");
        }
        
        $this->newLine();
        $this->info("✅ Done! CMS menu items should now be visible in the admin sidebar:");
        $this->line("  • Projects");
        $this->line("  • Leadership Team");
        $this->line("  • Accreditations");
        $this->line("  • History Timeline");
        $this->line("  • Support Services");
        $this->line("  • Admission Dates");
        
        return 0;
    }
}
