<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run() {
        $this->call(PermissionSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(LanguageSeeder::class);
        $this->call(SMSSettingSeeder::class);
        $this->call(MailSettingSeeder::class);
        
        $this->call(SessionSeeder::class);
        $this->call(FeesCategorySeeder::class);
        $this->call(StatusTypeSeeder::class);

        // The chart of accounts has to exist before the taxes, because each tax
        // points at the liability account it accrues to — 431 for the social
        // funds, 443 for the State. It was never called from here, so a fresh
        // install had no chart at all and nothing for a tax to point at.
        // The OHADA seeder never truncates, so running it on an established
        // school adds what is missing and leaves the rest alone.
        $this->call(OhadaChartOfAccountsSeeder::class);
        $this->call(TaxSettingSeeder::class);
        $this->call(WorkShiftSeeder::class);
        $this->call(GradeSeeder::class);
        $this->call(LeaveTypeSeeder::class);
        $this->call(ExamTypeSeeder::class);
        $this->call(ResultContributionSeeder::class);
        
        $this->call(FieldSeeder::class);
        $this->call(SocialSeeder::class);

        // Every other permission the code checks. PermissionSeeder above holds
        // only the original set; the modules added since each carry their own
        // seeder, and a fresh install used to run none of them — a new school
        // started with about 573 of the 762 permissions. This runs them all,
        // found by name, and runs last so the roles they grant to exist.
        $this->call(SyncAllPermissionsSeeder::class);
    }
}
