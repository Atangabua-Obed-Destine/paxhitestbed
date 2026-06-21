<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use Carbon\Carbon;
use DB;

class InitialFiscalYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates the 2024-2025 fiscal year with 12 monthly periods
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Create the 2024-2025 fiscal year
            $fiscalYear = FiscalYear::create([
                'name' => '2024-2025',
                'start_date' => '2024-09-01', // September 1, 2024 (typical school year start in Cameroon)
                'end_date' => '2025-08-31',   // August 31, 2025
                'is_active' => true,
                'is_closed' => false,
            ]);

            // Generate 12 monthly periods
            $periods = [
                ['name' => 'September 2024', 'name_fr' => 'Septembre 2024', 'start' => '2024-09-01', 'end' => '2024-09-30'],
                ['name' => 'October 2024', 'name_fr' => 'Octobre 2024', 'start' => '2024-10-01', 'end' => '2024-10-31'],
                ['name' => 'November 2024', 'name_fr' => 'Novembre 2024', 'start' => '2024-11-01', 'end' => '2024-11-30'],
                ['name' => 'December 2024', 'name_fr' => 'Décembre 2024', 'start' => '2024-12-01', 'end' => '2024-12-31'],
                ['name' => 'January 2025', 'name_fr' => 'Janvier 2025', 'start' => '2025-01-01', 'end' => '2025-01-31'],
                ['name' => 'February 2025', 'name_fr' => 'Février 2025', 'start' => '2025-02-01', 'end' => '2025-02-28'],
                ['name' => 'March 2025', 'name_fr' => 'Mars 2025', 'start' => '2025-03-01', 'end' => '2025-03-31'],
                ['name' => 'April 2025', 'name_fr' => 'Avril 2025', 'start' => '2025-04-01', 'end' => '2025-04-30'],
                ['name' => 'May 2025', 'name_fr' => 'Mai 2025', 'start' => '2025-05-01', 'end' => '2025-05-31'],
                ['name' => 'June 2025', 'name_fr' => 'Juin 2025', 'start' => '2025-06-01', 'end' => '2025-06-30'],
                ['name' => 'July 2025', 'name_fr' => 'Juillet 2025', 'start' => '2025-07-01', 'end' => '2025-07-31'],
                ['name' => 'August 2025', 'name_fr' => 'Août 2025', 'start' => '2025-08-01', 'end' => '2025-08-31'],
            ];

            foreach ($periods as $index => $period) {
                AccountingPeriod::create([
                    'fiscal_year_id' => $fiscalYear->id,
                    'name' => $period['name'],
                    'french_name' => $period['name_fr'],
                    'period_number' => $index + 1,
                    'start_date' => $period['start'],
                    'end_date' => $period['end'],
                    'is_closed' => false,
                ]);
            }

            DB::commit();

            $this->command->info('✓ Initial fiscal year 2024-2025 created successfully with 12 monthly periods!');
            $this->command->info('✓ Fiscal year is set as active and ready to use.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error creating fiscal year: ' . $e->getMessage());
        }
    }
}
