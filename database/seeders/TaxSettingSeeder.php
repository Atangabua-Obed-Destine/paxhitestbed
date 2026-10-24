<?php

namespace Database\Seeders;

use App\Support\Tax\CameroonTaxTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The payroll taxes a new school starts with.
 *
 * WHAT THIS REPLACED, and why it mattered. This seeder used to open with
 * `DB::table('tax_settings')->delete()` and insert four brackets:
 *
 *      0 - 5 000 at 0%,  5 001 - 10 000 at 5%,
 *      10 001 - 20 000 at 10%,  20 001 - 50 000 at 15%
 *
 * Those are the vendor template's demo figures. They have nothing to do with
 * Cameroon, they created no tax GROUPS at all — so no income tax, no CRTV, no
 * housing fund, no social insurance, no council surcharge — and the delete()
 * wiped whatever a school had already configured every time it ran. Every
 * installation started from that and was then hand-corrected, which is how one
 * school came to charge somebody on 100 000 the tax due on 90 000.
 *
 * Now it installs the verified Cameroon configuration: five income-tax
 * brackets derived from the DGI barème and checked against 11 972 rows parsed
 * out of it, eleven audio-visual bands, the housing fund with both shares, the
 * social contribution with its ceiling, the employment fund, and the council
 * surcharge as a percentage of the income tax. CameroonTaxTable holds the
 * numbers and the reasoning; this only writes them.
 *
 * TWO RULES IT KEEPS.
 *
 *  - It never deletes. A seeder that wipes a live table is how a school loses
 *    a configuration it spent a day getting right.
 *  - It never overwrites a school that has already configured its own rates.
 *    A fresh install gets the defaults; an established one is left alone and
 *    told so. Correcting an established school is a deliberate act, and
 *    `php artisan tax:configure-cameroon` is where it lives — that command
 *    checks for payroll history first, which a seeder cannot sensibly do.
 *
 * Needs the chart of accounts: each tax points at the liability account it
 * accrues to, 431 for the social funds and 443 for the State. DatabaseSeeder
 * runs OhadaChartOfAccountsSeeder before this for that reason. Where an
 * account is missing the tax is still written, with a warning, so the school
 * can point it somewhere later rather than losing the whole table.
 */
class TaxSettingSeeder extends Seeder
{
    /** A resit sitting pays nothing; these are the live liability accounts. */
    protected const ACCOUNT_STATE = '443';
    protected const ACCOUNT_SOCIAL = '431';

    protected ?int $state = null;
    protected ?int $social = null;

    public function run(): void
    {
        if ($this->hasPayrollHistory()) {
            $this->command?->warn('Tax settings: payroll has already been charged here — left alone.');
            $this->command?->line('  Rewriting the brackets would leave those payrolls pointing at rates that no');
            $this->command?->line('  longer say what they were charged under. Run: php artisan tax:inspect');

            return;
        }

        if ($this->alreadyConfigured()) {
            $this->command?->warn('Tax settings: this installation already has its own configuration — left alone.');
            $this->command?->line('  To replace it deliberately: php artisan tax:configure-cameroon --dry-run');

            return;
        }

        // Groups that exist but carry none of the known codes are an empty
        // shell, usually created by hand. Adopt them by code where the title
        // matches, so the school keeps its ids and anything already pointing
        // at them, rather than ending up with two of each.
        $this->adoptUncodedGroups();

        $this->state = $this->account(self::ACCOUNT_STATE);
        $this->social = $this->account(self::ACCOUNT_SOCIAL);

        if (!$this->state || !$this->social) {
            $this->command?->warn(sprintf(
                'Tax settings: liability account %s is missing, so some taxes will have none. Seed the chart of accounts and re-run tax:configure-cameroon.',
                $this->state ? self::ACCOUNT_SOCIAL : self::ACCOUNT_STATE
            ));
        }

        $effectiveFrom = now()->startOfMonth()->toDateString();

        DB::transaction(function () use ($effectiveFrom) {
            $this->incomeTax($effectiveFrom);
            $this->localDevelopment($effectiveFrom);
            $this->housingFund($effectiveFrom);
            $this->audioVisual($effectiveFrom);
            $this->industrialAccident();
            $this->socialInsurance($effectiveFrom);
            $this->employmentFund($effectiveFrom);
            $this->councilSurcharge($effectiveFrom);
        });

        $this->command?->info(sprintf(
            'Tax settings: Cameroon configuration installed, effective %s.', $effectiveFrom
        ));
        $this->command?->line('  Still to confirm with the school: the industrial-accident rate for their');
        $this->command?->line('  sector, and the local development bands against the published schedule.');
    }

    /**
     * Has somebody already configured this school, in a way worth keeping?
     *
     * "Any tax group exists" was the first answer and it was too blunt. A
     * school can have groups that were created by hand and carry none of the
     * codes this configuration keys on — in which case
     * `tax:configure-cameroon` refuses for want of a code AND this seeder
     * skipped for the presence of a group, leaving no way forward at all. That
     * is exactly what SAHIK turned out to be.
     *
     * So the test is whether anything is really configured HERE: a group that
     * carries one of the known codes, or a tax pointing at a liability
     * account. Groups that are neither are an empty shell, and filling them in
     * is the whole job.
     *
     * Payroll history is a separate and stronger reason to stop, and it is
     * checked on its own below.
     */
    protected function alreadyConfigured(): bool
    {
        return DB::table('tax_groups')->whereIn('code', array_keys(CameroonTaxTable::GROUPS))->exists()
            || DB::table('tax_settings')->whereNotNull('liability_account_id')->exists();
    }

    /**
     * Anything charged under the current rates?
     *
     * A seeder has no business rewriting the brackets a paid payroll was
     * calculated from. It stops rather than asking, because a seeder runs
     * unattended as part of `db:seed` and there is nobody there to answer.
     */
    protected function hasPayrollHistory(): bool
    {
        return DB::table('payroll_tax_lines')->exists()
            || DB::table('payrolls')->where('status', 1)->exists();
    }

    protected function account(string $code): ?int
    {
        return DB::table('chart_of_accounts')->where('account_code', $code)->value('id');
    }

    /**
     * Give a code to a group that plainly already is one of these.
     *
     * A school whose groups were created by hand has the right titles and no
     * codes. Creating fresh groups beside them would leave two "Personal
     * Income Tax" rows, one of them the one the screens already show. Matching
     * on title and backfilling the code adopts what is there instead, so ids
     * and anything referencing them survive.
     *
     * Only ever fills a code in; never renames a group, and never touches one
     * that already carries a code.
     */
    protected function adoptUncodedGroups(): void
    {
        foreach (CameroonTaxTable::GROUPS as $code => $title) {
            $match = DB::table('tax_groups')
                ->where(function ($query) {
                    $query->whereNull('code')->orWhere('code', '');
                })
                ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower(trim($title))])
                ->first();

            if (!$match) {
                continue;
            }

            DB::table('tax_groups')->where('id', $match->id)
                ->update(['code' => $code, 'updated_at' => now()]);

            $this->command?->line(sprintf('  adopted existing group #%d "%s" as %s',
                $match->id, $match->title, $code));
        }
    }

    // -----------------------------------------------------------------------

    protected function incomeTax(string $effectiveFrom): void
    {
        $group = $this->group('PIT', CameroonTaxTable::GROUPS['PIT'], true);

        foreach (CameroonTaxTable::INCOME_TAX as $band) {
            $this->bracket($group, [
                'title' => sprintf('IRPP band %d — %s%% above %s', $band['order'],
                    number_format($band['percent'], 2), number_format($band['threshold'])),
                'bracket_order' => $band['order'],
                'tax_type' => 1,
                'paid_by' => 'employee',
                'min_amount' => $band['min'],
                'max_amount' => $band['max'],
                'percentange' => $band['percent'],
                'max_no_taxable_amount' => $band['threshold'],
                'liability_account_id' => $this->state,
            ], $effectiveFrom);
        }
    }

    protected function localDevelopment(string $effectiveFrom): void
    {
        $group = $this->group('LDT', CameroonTaxTable::GROUPS['LDT'], false);

        foreach (CameroonTaxTable::LOCAL_DEVELOPMENT as $band) {
            $this->bracket($group, [
                'title' => sprintf('Local Development Tax — band %d', $band['order']),
                'bracket_order' => $band['order'],
                'tax_type' => 2,
                'paid_by' => 'employee',
                'min_amount' => $band['min'],
                'max_amount' => $band['max'],
                'fixed_amount' => $band['amount'],
                'liability_account_id' => $this->state,
            ], $effectiveFrom);
        }
    }

    /** 1% from the employee, 1.5% from the employer — the barème's 2.5% less the employment fund. */
    protected function housingFund(string $effectiveFrom): void
    {
        $group = $this->group('HLF', CameroonTaxTable::GROUPS['HLF'], false);

        $this->bracket($group, [
            'title' => 'Housing Loan Fund',
            'bracket_order' => 1,
            'tax_type' => 1,
            'paid_by' => 'both',
            'min_amount' => CameroonTaxTable::FLOOR,
            'max_amount' => CameroonTaxTable::OPEN_TOP,
            'percentange' => CameroonTaxTable::CFC_EMPLOYEE_RATE,
            'employer_percentage' => CameroonTaxTable::CFC_EMPLOYER_RATE,
            'liability_account_id' => $this->state,
        ], $effectiveFrom);
    }

    protected function audioVisual(string $effectiveFrom): void
    {
        $group = $this->group('CRTV', CameroonTaxTable::GROUPS['CRTV'], false);

        foreach (CameroonTaxTable::AUDIO_VISUAL as $band) {
            $this->bracket($group, [
                'title' => sprintf('CRTV band %d', $band['order']),
                'bracket_order' => $band['order'],
                'tax_type' => 2,
                'paid_by' => 'employee',
                'min_amount' => $band['min'],
                'max_amount' => $band['max'],
                'fixed_amount' => $band['amount'],
                'liability_account_id' => $this->state,
            ], $effectiveFrom);
        }
    }

    /**
     * Created switched off, with no bands.
     *
     * A real employer obligation whose rate depends on the school's sector
     * classification, so there is no honest default. The group exists so the
     * school finds it on the settings screen and fills it in, rather than not
     * knowing it is owed.
     */
    protected function industrialAccident(): void
    {
        $this->group('IA', CameroonTaxTable::GROUPS['IA'], false, 0);
    }

    /**
     * Social insurance, with the ceiling the barème implies.
     *
     * The marginal rate steps from 23.03% to 24.50% at gross 1 785 714, which
     * is where the 4.2% stops accruing: 75 000 / 0.042 = 1 785 714.29. So the
     * EMPLOYEE CONTRIBUTION is capped at 75 000 a month, not the base at
     * 750 000 as is often assumed — testing a 750 000 base against the printed
     * table disagrees from 750 250 onward.
     *
     * There is no ceiling column, so the cap is two ungrouped rows: a
     * percentage up to the ceiling, a fixed amount above it. Ungrouped rows
     * are selected on BOTH bounds, so the two never both apply.
     */
    protected function socialInsurance(string $effectiveFrom): void
    {
        $this->standalone('National Social Insurance Fund', [
            'bracket_order' => 1,
            'tax_type' => 1,
            'paid_by' => 'both',
            'min_amount' => 0,
            'max_amount' => CameroonTaxTable::CNPS_CAP_BASE - 1,
            'percentange' => CameroonTaxTable::CNPS_RATE,
            'employer_percentage' => CameroonTaxTable::CNPS_RATE,
            'liability_account_id' => $this->social,
        ], $effectiveFrom);

        $this->standalone('National Social Insurance Fund — at the ceiling', [
            'bracket_order' => 2,
            'tax_type' => 2,
            'paid_by' => 'both',
            'min_amount' => CameroonTaxTable::CNPS_CAP_BASE,
            'max_amount' => CameroonTaxTable::OPEN_TOP,
            'fixed_amount' => CameroonTaxTable::CNPS_CAP_AMOUNT,
            'employer_fixed_amount' => CameroonTaxTable::CNPS_CAP_AMOUNT,
            'liability_account_id' => $this->social,
        ], $effectiveFrom);
    }

    protected function employmentFund(string $effectiveFrom): void
    {
        $this->standalone('National Employment Fund', [
            'bracket_order' => 3,
            'tax_type' => 1,
            'paid_by' => 'employer',
            'min_amount' => 0,
            'max_amount' => CameroonTaxTable::OPEN_TOP,
            'percentange' => 0,
            'employer_percentage' => CameroonTaxTable::FNE_EMPLOYER_RATE,
            'liability_account_id' => $this->social,
        ], $effectiveFrom);
    }

    /**
     * Ten percent of the income tax, not of pay.
     *
     * Dependent on the income-tax GROUP, so it follows whatever that group
     * charges. Its bounds are deliberately wide: they are tested against the
     * salary, and a narrow range meant a high earner escaped the surcharge
     * entirely. The income tax is already zero below its own threshold, so no
     * floor is needed here.
     */
    protected function councilSurcharge(string $effectiveFrom): void
    {
        $pit = DB::table('tax_groups')->where('code', 'PIT')->value('id');

        $this->standalone('Additional Council Tax', [
            'bracket_order' => 4,
            'is_dependent' => 1,
            'depends_on_type' => 'tax_group',
            'depends_on_id' => $pit,
            'tax_type' => 1,
            'paid_by' => 'employee',
            'min_amount' => 0,
            'max_amount' => CameroonTaxTable::OPEN_TOP,
            'percentange' => CameroonTaxTable::CAC_RATE,
            'liability_account_id' => $this->state,
        ], $effectiveFrom);
    }

    // -----------------------------------------------------------------------

    /** Find or create a group by its code, which is the stable key. */
    protected function group(string $code, string $title, bool $progressive, int $status = 1): int
    {
        $existing = DB::table('tax_groups')->where('code', $code)->first();

        if ($existing) {
            DB::table('tax_groups')->where('id', $existing->id)->update([
                'is_progressive' => $progressive ? 1 : 0,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('tax_groups')->insertGetId([
            'title' => $title,
            'code' => $code,
            'is_progressive' => $progressive ? 1 : 0,
            'status' => $status,
            'liability_account_id' => $this->state,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function bracket(int $groupId, array $attributes, string $effectiveFrom): void
    {
        $this->write($attributes + [
            'tax_group_id' => $groupId,
            'is_dependent' => 0,
        ], $effectiveFrom, ['tax_group_id' => $groupId, 'bracket_order' => $attributes['bracket_order']]);
    }

    protected function standalone(string $title, array $attributes, string $effectiveFrom): void
    {
        $this->write($attributes + [
            'title' => $title,
            'tax_group_id' => null,
        ], $effectiveFrom, ['title' => $title]);
    }

    /** One row, with the columns this table requires rather than only the ones given. */
    protected function write(array $attributes, string $effectiveFrom, array $key): void
    {
        $row = $attributes + [
            'is_dependent' => 0,
            'depends_on_type' => null,
            'depends_on_id' => null,
            'is_shared' => 0,
            'percentange' => 0,
            'employer_percentage' => 0,
            'fixed_amount' => 0,
            'employer_fixed_amount' => 0,
            'max_no_taxable_amount' => 0,
            'status' => 1,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'updated_at' => now(),
        ];

        $query = DB::table('tax_settings');

        foreach ($key as $column => $value) {
            $query = $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        $existing = $query->first();

        if ($existing) {
            DB::table('tax_settings')->where('id', $existing->id)->update($row);

            return;
        }

        DB::table('tax_settings')->insert($row + ['created_at' => now()]);
    }
}
