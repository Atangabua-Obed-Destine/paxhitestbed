<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SyncAllPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Run main PermissionSeeder first
        $this->call(PermissionSeeder::class);

        // 2. Run all custom modules and converted root script seeders
        $seeders = [
            AccountingPermissionSeeder::class,
            AdditionalMissingPermissionsSeeder::class,
            AnnouncementPermissionSeeder::class,
            AttendanceSettingPermissionSeeder::class,
            AuditPermissionSeeder::class,
            BudgetPermissionSeeder::class,
            ClassHubPermissionSeeder::class,
            ClassSessionPermissionSeeder::class,
            CmsPermissionSeeder::class,
            DynamicPopupPermissionSeeder::class,
            ExamPublishingPermissionSeeder::class,
            FeeAssignmentsHistoryPermissionSeeder::class,
            PaymentPlanPermissionSeeder::class,
            PaymentReceiptPermissionSeeder::class,
            ProgramSemesterFeePermissionSeeder::class,
            RemainingPermissionsSeeder::class,
            ResitPermissionSeeder::class,
            ResourcePermissionSeeder::class,
            ResultsSummaryPermissionSeeder::class,
            SectorPermissionSeeder::class,
            SecurityPermissionSeeder::class,
            SeederPermissionsSeeder::class,
            SenateDeliberationPermissionSeeder::class,
            SidebarPermissionSeeder::class,
            StaffAssignmentPermissionsSeeder::class,
            StudentArchivePermissionSeeder::class,
            UndocumentedPermissionsSeeder::class,
            WebCMSPermissionSeeder::class,
            
            // 3. Run fixers and consolidators last
            MissingPermissionsSeeder::class,
            FixPermissionNamesSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            } else {
                $this->command->warn("Seeder not found: " . $seeder);
            }
        }

        // Clear cache again to ensure system uses new permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
