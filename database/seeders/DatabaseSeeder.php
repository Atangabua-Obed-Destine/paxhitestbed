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
        $this->call(TaxSettingSeeder::class);
        $this->call(WorkShiftSeeder::class);
        $this->call(GradeSeeder::class);
        $this->call(LeaveTypeSeeder::class);
        $this->call(ExamTypeSeeder::class);
        $this->call(ResultContributionSeeder::class);
        
        $this->call(FieldSeeder::class);
        $this->call(SocialSeeder::class);

        // The chart of accounts. Order matters: the classes and headings come
        // first, then the detail accounts that hang off them, then the ones
        // added later. Each is idempotent, so a re-seed of an existing
        // installation only fills gaps.
        $this->call(OhadaChartOfAccountsSeeder::class);
        $this->call(OhadaDetailAccountsSeeder::class);
        $this->call(OhadaMissingAccountsSeeder::class);
    }
}
