<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the four admission approvals.
 *
 * These replace the signatures on the printed "For Official Use Only" block, so
 * they are meant to be held by DIFFERENT people — that is the whole point of a
 * chain.
 *
 * They are granted to Super Admin and to nobody else. The obvious shortcut was
 * to mirror application-edit, which is how the other seeders here distribute a
 * new permission, but eight roles hold that — Human Resource, IT Administrator
 * and Student Affairs among them — and mirroring it would have handed the final
 * say on admissions to all eight in one migration. An approval that everyone
 * holds is not an approval.
 *
 * So the school assigns them, on Settings → Roles, to the people who actually
 * sign: typically Registery for receipt and documents, the board for the
 * decision, the Registrar or VC for the final approval. Until then a Super
 * Admin can work the screen, and nothing is blocked.
 *
 * Found and run by `php artisan permissions:sync`, which also runs after every
 * migrate. Safe to re-run: creates only what is missing, only ever adds grants.
 */
class ApplicationApprovalPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'application-approve-receipt',
                'group' => 'Admission Approval',
                'title' => 'Approve: Application Received',
            ],
            [
                'name' => 'application-approve-documents',
                'group' => 'Admission Approval',
                'title' => 'Approve: Documents Verified',
            ],
            [
                'name' => 'application-approve-board',
                'group' => 'Admission Approval',
                'title' => 'Approve: Admissions Board Decision',
            ],
            [
                'name' => 'application-approve-final',
                'group' => 'Admission Approval',
                'title' => 'Approve: Final Approval',
            ],
        ];

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();

        foreach ($permissions as $permission) {
            $permission['guard_name'] = 'web';

            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                $permission
            );

            if ($superAdmin && !$superAdmin->hasPermissionTo($row)) {
                $superAdmin->givePermissionTo($row);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(
            'Admission approval permissions synced to Super Admin only. '
            . 'Assign the four steps to the roles that actually sign on Settings → Roles.'
        );
    }
}
