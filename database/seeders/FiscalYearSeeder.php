<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Statutory fiscal years, on the calendar clock.
 *
 * Deliberately January–December, not the October–July academic year. OHADA
 * reporting in Cameroon is calendar-based, while the budget sheet runs with the
 * academic session — `budgets` carries its own start and end dates for that, so
 * the two clocks coexist without either distorting the other.
 *
 * Covers 2025 as well as 2026 because recorded spending begins October 2025;
 * without it those entries would fall outside every period.
 *
 * Idempotent: safe to re-run.
 */
class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $activeYear = 2026;

        foreach ([2025, 2026] as $year) {
            $fiscalYear = FiscalYear::updateOrCreate(
                ['name' => (string) $year],
                [
                    'start_date' => Carbon::create($year, 1, 1)->startOfDay(),
                    'end_date' => Carbon::create($year, 12, 31)->endOfDay(),
                    // The model deactivates the others on save, so only the
                    // current year is ever flagged active.
                    'is_active' => $year === $activeYear,
                    'is_closed' => false,
                ]
            );

            $this->createMonthlyPeriods($fiscalYear);
        }

        $this->command?->info(sprintf(
            'Fiscal years: %d years, %d accounting periods, active = %s.',
            FiscalYear::count(),
            AccountingPeriod::count(),
            optional(FiscalYear::where('is_active', true)->first())->name ?? 'none'
        ));
    }

    /**
     * One period per calendar month. Mirrors
     * FiscalYearController::createMonthlyPeriods so a year created here and one
     * created through the screen are indistinguishable.
     */
    protected function createMonthlyPeriods(FiscalYear $fiscalYear): void
    {
        $english = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
        $french = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        $cursor = Carbon::parse($fiscalYear->start_date)->startOfMonth();
        $end = Carbon::parse($fiscalYear->end_date);
        $periodNumber = 1;

        while ($cursor <= $end) {
            $month = (int) $cursor->format('n');

            AccountingPeriod::updateOrCreate(
                [
                    'fiscal_year_id' => $fiscalYear->id,
                    'period_number' => $periodNumber,
                ],
                [
                    'name' => $english[$month] . ' ' . $cursor->format('Y'),
                    'french_name' => $french[$month] . ' ' . $cursor->format('Y'),
                    'start_date' => $cursor->copy()->startOfMonth(),
                    'end_date' => $cursor->copy()->endOfMonth(),
                    'is_closed' => false,
                ]
            );

            $cursor->addMonth();
            $periodNumber++;
        }
    }
}
