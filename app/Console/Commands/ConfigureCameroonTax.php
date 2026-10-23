<?php

namespace App\Console\Commands;

use App\Models\TaxGroup;
use App\Models\TaxSetting;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Configure the payroll taxes from the DGI barème.
 *
 * WHERE THESE NUMBERS COME FROM. The "Barème indicatif de liquidation de
 * l'IRPP et autres retenues salariales, hors taxe de développement local" is a
 * lookup table in 250 FCFA steps of gross monthly pay, 52 000 to 3 097 000,
 * printed over 70 pages. It is not, however, data: it is the statutory
 * computation rendered row by row --
 *
 *      CNPS        = min(gross x 4.2%, 75 000)        employee share, capped
 *      net taxable = gross x 70% - CNPS - 500 000/12  30% allowance, abatement
 *      IRPP        = 10 / 15 / 25 / 35% on the annualised net, by band
 *
 * Below the CNPS cap the net taxable is gross x (0.70 - 0.042) = gross x
 * 0.658, so WITHIN A BAND the tax is linear in gross pay:
 *
 *      IRPP = (gross - threshold) x marginal
 *
 * and every marginal is a statutory rate times the net factor:
 *
 *      6.58 = 10 x 0.658      16.45 = 25 x 0.658      24.50 = 35 x 0.70
 *      9.87 = 15 x 0.658      23.03 = 35 x 0.658
 *
 * That shape -- a rate on pay above a threshold -- is exactly one tax_settings
 * row, as percentange with max_no_taxable_amount, and
 * TaxGroup::calculateTax() already evaluates
 * max(0, salary - max_no_taxable_amount) x percentange / 100 after picking the
 * single bracket with the highest min_amount at or below the salary. So five
 * rows stand in for 12 276, with no new engine, no new table, and no schema
 * change.
 *
 * The five brackets were checked against 11 972 rows parsed independently out
 * of the PDF: 98.18% reproduce exactly and 100% fall within one franc, which
 * is the rounding the printed table itself carries.
 *
 * WHAT WAS THERE BEFORE. Six brackets holding isolated sample points with
 * holes between them -- 89 900-90 000, 109 900-110 000, and so on. Because
 * bracket selection ignores max_amount and takes the band below, every salary
 * that did not land exactly on a sample point was charged the previous point's
 * figure, and nothing above 180 000 was charged more than 7 929. On this
 * institution's own payroll that over-withheld from most staff and
 * under-withheld from one by 532 a month.
 *
 * NOT TOUCHED HERE. The Local Development Tax is excluded from this barème by
 * its own title and its group is already contiguous, so it is left alone and
 * reported. The Industrial Accident group needs the institution's sector
 * classification before it can carry a rate. Both are named in the summary.
 */
class ConfigureCameroonTax extends Command
{
    protected $signature = 'tax:configure-cameroon
        {--dry-run : Show what would change and roll it back}
        {--effective-from= : The date these rates take effect (default: the 1st of this month)}';

    protected $description = 'Configure payroll taxes from the DGI barème, and reconcile against the previous rates';

    /**
     * Personal income tax, as five brackets.
     *
     * percent is the marginal rate on pay above threshold. Derived and
     * verified as described in the class docblock; `note` records which
     * statutory rate each one is, so a future reader can re-derive it.
     */
    protected const PIT = [
        ['order' => 1, 'min' => 0,         'max' => 316616,   'percent' => 6.58,  'threshold' => 63323.20,  'note' => '10% statutory x 0.658'],
        ['order' => 2, 'min' => 316617,    'max' => 443262,   'percent' => 9.87,  'threshold' => 147754.14, 'note' => '15% statutory x 0.658'],
        ['order' => 3, 'min' => 443263,    'max' => 696555,   'percent' => 16.45, 'threshold' => 265957.45, 'note' => '25% statutory x 0.658'],
        ['order' => 4, 'min' => 696556,    'max' => 1785714,  'percent' => 23.03, 'threshold' => 388985.38, 'note' => '35% statutory x 0.658'],
        ['order' => 5, 'min' => 1785715,   'max' => 50000000, 'percent' => 24.50, 'threshold' => 472789.12, 'note' => '35% statutory x 0.70, past the CNPS cap'],
    ];

    /**
     * Audio-visual tax (CRTV): a flat amount per 100 000 of pay.
     *
     * Read straight off the barème's CRTV column. The boundary sits at every
     * round 100 000 and the amount rises by 1 300 each time, except the first
     * step (1 200) and the last band, which is flat at 13 000.
     *
     * The floor is 52 000 because that is where the barème starts, and its
     * first row already carries 750 -- so CRTV bites below the income-tax
     * threshold. What happens under 52 000 the document does not say, and this
     * command does not guess: see the warning it prints.
     */
    protected const CRTV = [
        ['order' => 1,  'min' => self::BAREME_FLOOR,    'max' => 100000,   'amount' => 750],
        ['order' => 2,  'min' => 100250,   'max' => 200000,   'amount' => 1950],
        ['order' => 3,  'min' => 200250,   'max' => 300000,   'amount' => 3250],
        ['order' => 4,  'min' => 300250,   'max' => 400000,   'amount' => 4550],
        ['order' => 5,  'min' => 400250,   'max' => 500000,   'amount' => 5850],
        ['order' => 6,  'min' => 500250,   'max' => 600000,   'amount' => 7150],
        ['order' => 7,  'min' => 600250,   'max' => 700000,   'amount' => 8450],
        ['order' => 8,  'min' => 700250,   'max' => 800000,   'amount' => 9750],
        ['order' => 9,  'min' => 800250,   'max' => 900000,   'amount' => 11050],
        ['order' => 10, 'min' => 900250,   'max' => 1000000,  'amount' => 12350],
        ['order' => 11, 'min' => 1000250,  'max' => 50000000, 'amount' => 13000],
    ];

    /**
     * The lowest gross pay the barème covers.
     *
     * Its first row is 52 000, and that row already carries CCF 520 and CRTV
     * 750 -- so both bite below the income-tax threshold. What is due under
     * 52 000 the document does not say, so nothing is charged there rather
     * than a figure being assumed.
     */
    protected const BAREME_FLOOR = 52000;

    /** Gross pay at which the CNPS employee contribution stops growing. */
    protected const CNPS_CAP_BASE = 1785715;
    protected const CNPS_CAP_AMOUNT = 75000;
    protected const CNPS_RATE = 4.2;

    /** Group ids, as installed. */
    protected const GROUP_PIT = 1;
    protected const GROUP_TDL = 2;
    protected const GROUP_CFC = 4;
    protected const GROUP_CRTV = 5;
    protected const GROUP_ACCIDENT = 9;

    /** 443 Etat - Retenue, and 431 CNPS. */
    protected const ACCOUNT_STATE = 145;
    protected const ACCOUNT_SOCIAL = 142;

    protected string $effectiveFrom;

    public function handle(): int
    {
        $this->effectiveFrom = $this->option('effective-from')
            ?: Carbon::now()->startOfMonth()->toDateString();

        if (!$this->preflight()) {
            return self::FAILURE;
        }

        $before = $this->snapshot();

        DB::beginTransaction();

        try {
            $this->configurePit();
            $this->configureCrtv();
            $this->configureCfc();
            $this->configureCnps();
            $this->configureCouncilTax();
            $this->configureEmploymentFund();
            $this->dateTheRest();
            $this->labelProgressiveGroups();

            $this->line('');
            $this->info('Verifying against the barème');
            $this->line(str_repeat('=', 78));

            if (!$this->verify()) {
                DB::rollBack();
                $this->line('');
                $this->error('The configuration does not reproduce the barème. Nothing was changed.');

                return self::FAILURE;
            }

            $this->reconcile($before);
            $this->notTouched();

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->line('');
                $this->warn('Dry run — everything above was rolled back. Re-run without --dry-run to apply.');

                return self::SUCCESS;
            }

            DB::commit();
            $this->line('');
            $this->info('Applied. Rates take effect from ' . $this->effectiveFrom . '.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing was changed: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());

            return self::FAILURE;
        }
    }

    /** Refuse to start unless the groups and accounts this writes to exist. */
    protected function preflight(): bool
    {
        $this->line('');
        $this->info('Configuring payroll taxes from the DGI barème');
        $this->line(str_repeat('=', 78));
        $this->line('Effective from : ' . $this->effectiveFrom);
        $this->line('');

        $ok = true;

        foreach ([
            self::GROUP_PIT => 'Personal Income Tax',
            self::GROUP_CFC => 'Housing Loan Fund',
            self::GROUP_CRTV => 'Audio Visual Tax',
        ] as $id => $what) {
            if (!TaxGroup::find($id)) {
                $this->error(sprintf('Tax group %d (%s) is missing.', $id, $what));
                $ok = false;
            }
        }

        foreach ([self::ACCOUNT_STATE => '443 Etat - Retenue', self::ACCOUNT_SOCIAL => '431 CNPS'] as $id => $what) {
            if (!DB::table('chart_of_accounts')->where('id', $id)->exists()) {
                $this->error(sprintf('Liability account %d (%s) is missing.', $id, $what));
                $ok = false;
            }
        }

        return $ok;
    }

    /** What every active member of staff is charged today, before any change. */
    protected function snapshot(): array
    {
        $rows = [];

        foreach ($this->staff() as $user) {
            $rows[$user->id] = $this->chargeFor((float) $user->basic_salary);
        }

        return $rows;
    }

    protected function staff()
    {
        return User::where('status', '1')->where('basic_salary', '>', 0)
            ->orderBy('basic_salary')->get();
    }

    /** Employee and employer totals for a salary, through the real engine. */
    protected function chargeFor(float $salary): array
    {
        $breakdown = app(\App\Services\PayrollTaxBreakdownService::class);
        $lines = $breakdown->forSalary($salary, Carbon::parse($this->effectiveFrom));

        $employee = 0.0;
        $employer = 0.0;
        $byLabel = [];

        foreach ($lines as $line) {
            $employee += $line['employee'];
            $employer += $line['employer'];
            $byLabel[$line['label']] = $line['employee'] + $line['employer'];
        }

        return ['employee' => $employee, 'employer' => $employer, 'lines' => $byLabel];
    }

    // -----------------------------------------------------------------------

    protected function configurePit(): void
    {
        $this->info('Personal Income Tax — five brackets replacing six sample points');

        $kept = [];

        foreach (self::PIT as $b) {
            $kept[] = $this->writeBracket(self::GROUP_PIT, [
                'title' => sprintf('IRPP band %d — %s%% above %s', $b['order'],
                    number_format($b['percent'], 2), number_format($b['threshold'])),
                'bracket_order' => $b['order'],
                'tax_type' => 1,
                'paid_by' => 'employee',
                'min_amount' => $b['min'],
                'max_amount' => $b['max'],
                'percentange' => $b['percent'],
                'employer_percentage' => 0,
                'fixed_amount' => 0,
                'employer_fixed_amount' => 0,
                'max_no_taxable_amount' => $b['threshold'],
                'liability_account_id' => self::ACCOUNT_STATE,
            ]);

            $this->line(sprintf('   %-9s - %-11s  %5s%% above %-12s  %s',
                number_format($b['min']), number_format($b['max']),
                number_format($b['percent'], 2), number_format($b['threshold'], 2), $b['note']));
        }

        $this->pruneBrackets(self::GROUP_PIT, $kept);
        TaxGroup::where('id', self::GROUP_PIT)->update(['status' => 1]);
    }

    protected function configureCrtv(): void
    {
        $this->line('');
        $this->info('Audio Visual Tax (CRTV) — eleven bands replacing three');

        $kept = [];

        foreach (self::CRTV as $b) {
            $kept[] = $this->writeBracket(self::GROUP_CRTV, [
                'title' => sprintf('CRTV band %d', $b['order']),
                'bracket_order' => $b['order'],
                'tax_type' => 2,
                'paid_by' => 'employee',
                'min_amount' => $b['min'],
                'max_amount' => $b['max'],
                'percentange' => 0,
                'employer_percentage' => 0,
                'fixed_amount' => $b['amount'],
                'employer_fixed_amount' => 0,
                'max_no_taxable_amount' => 0,
                'liability_account_id' => self::ACCOUNT_STATE,
            ]);
        }

        $this->line(sprintf('   %s bands, %s to %s, boundary every 100,000',
            count(self::CRTV), number_format(self::CRTV[0]['amount']),
            number_format(self::CRTV[count(self::CRTV) - 1]['amount'])));

        $this->pruneBrackets(self::GROUP_CRTV, $kept);
        TaxGroup::where('id', self::GROUP_CRTV)->update(['status' => 1]);
    }

    /**
     * Housing loan fund: 1% from the employee, 1.5% from the employer.
     *
     * The barème's employer column is 2.5% of gross, which is this 1.5% plus
     * the employment fund's 1%. Only the employee half was configured before,
     * so the institution's own cost was understated.
     *
     * The floor is the barème's own first row, 52 000, which already carries
     * CCF 520 -- so this bites below the income-tax threshold, as CRTV does.
     * It is deliberately NOT lowered to zero: the document says nothing about
     * pay under 52 000, and widening it would have started charging 1% to the
     * staff paid 2 000 to 50 000 on the strength of an assumption.
     */
    protected function configureCfc(): void
    {
        $this->line('');
        $this->info('Housing Loan Fund (CFC) — adding the 1.5% employer share');

        $kept = [$this->writeBracket(self::GROUP_CFC, [
            'title' => 'Housing Loan Fund',
            'bracket_order' => 1,
            'tax_type' => 1,
            'paid_by' => 'both',
            'min_amount' => self::BAREME_FLOOR,
            'max_amount' => 50000000,
            'percentange' => 1,
            'employer_percentage' => 1.5,
            'fixed_amount' => 0,
            'employer_fixed_amount' => 0,
            'max_no_taxable_amount' => 0,
            'liability_account_id' => self::ACCOUNT_STATE,
        ])];

        $this->line('   1.00% employee + 1.50% employer on the whole of pay');
        $this->pruneBrackets(self::GROUP_CFC, $kept);
        TaxGroup::where('id', self::GROUP_CFC)->update(['status' => 1]);
    }

    /**
     * Social insurance, with the ceiling the barème implies.
     *
     * The barème's marginal rate steps from 23.03% to 24.50% at gross
     * 1 785 714, which is where the net factor changes from 0.658 to 0.70 --
     * that is, where the 4.2% stops accruing. 75 000 / 0.042 = 1 785 714.29,
     * so the EMPLOYEE CONTRIBUTION is capped at 75 000 a month, not the base
     * at 750 000 as is often assumed. Testing a 750 000 base against the table
     * disagrees from 750 250 onward; this cap matches every row.
     *
     * There is no ceiling column, so the cap is expressed as two ungrouped
     * rows: a percentage up to the ceiling, a fixed amount above it. Ungrouped
     * rows are selected on BOTH bounds, so the two never both apply.
     */
    protected function configureCnps(): void
    {
        $this->line('');
        $this->info('Social Insurance (CNPS) — capped at ' . number_format(self::CNPS_CAP_AMOUNT) . ' a month');

        // Matched on "Insurance" alone because the installed row is titled
        // "National Socail Insurance Fund" -- the typo is in the data, and a
        // tidier pattern silently matches nothing. The title is corrected below.
        $below = TaxSetting::whereNull('tax_group_id')
            ->where('is_dependent', 0)
            ->where('title', 'LIKE', '%Insurance%')
            ->orderBy('id')->first();

        if (!$below) {
            throw new \RuntimeException('The social insurance row is missing; not inventing one.');
        }

        $below->fill([
            'title' => 'National Social Insurance Fund',
            'tax_type' => 1,
            'paid_by' => 'both',
            'min_amount' => 0,
            'max_amount' => self::CNPS_CAP_BASE - 1,
            'percentange' => self::CNPS_RATE,
            'employer_percentage' => self::CNPS_RATE,
            'fixed_amount' => 0,
            'employer_fixed_amount' => 0,
            'max_no_taxable_amount' => 0,
            'status' => 1,
            'liability_account_id' => self::ACCOUNT_SOCIAL,
            'effective_from' => $this->effectiveFrom,
        ])->save();

        $above = TaxSetting::whereNull('tax_group_id')
            ->where('title', 'National Social Insurance Fund — at the ceiling')->first()
            ?: new TaxSetting();

        $above->fill([
            'tax_group_id' => null,
            'is_dependent' => 0,
            'title' => 'National Social Insurance Fund — at the ceiling',
            'bracket_order' => 2,
            'tax_type' => 2,
            'paid_by' => 'both',
            'min_amount' => self::CNPS_CAP_BASE,
            'max_amount' => 50000000,
            'percentange' => 0,
            'employer_percentage' => 0,
            'fixed_amount' => self::CNPS_CAP_AMOUNT,
            'employer_fixed_amount' => self::CNPS_CAP_AMOUNT,
            'max_no_taxable_amount' => 0,
            'status' => 1,
            'liability_account_id' => self::ACCOUNT_SOCIAL,
            'effective_from' => $this->effectiveFrom,
        ])->save();

        $this->line(sprintf('   up to %-11s  %s%% employee + %s%% employer',
            number_format(self::CNPS_CAP_BASE - 1), self::CNPS_RATE, self::CNPS_RATE));
        $this->line(sprintf('   above that     flat %s each side',
            number_format(self::CNPS_CAP_AMOUNT)));
    }

    /**
     * Additional council tax: 10% of the income tax, as it already was.
     *
     * The rate and the dependency were right. The bounds were not: they were
     * tested against SALARY (62 000 - 5 000 000), so a salary above five
     * million escaped the surcharge altogether. The income tax it derives
     * from is already zero below the threshold, so no floor is needed.
     */
    protected function configureCouncilTax(): void
    {
        $this->line('');
        $this->info('Additional Council Tax — 10% of income tax, no longer gated on salary');

        $cac = TaxSetting::whereNull('tax_group_id')->where('is_dependent', 1)
            ->where('depends_on_type', 'tax_group')
            ->where('depends_on_id', self::GROUP_PIT)->first();

        if (!$cac) {
            throw new \RuntimeException('The council tax row is missing; not inventing one.');
        }

        $was = sprintf('%s - %s', number_format($cac->min_amount), number_format($cac->max_amount));

        $cac->fill([
            'tax_type' => 1,
            'paid_by' => 'employee',
            'min_amount' => 0,
            'max_amount' => 50000000,
            'percentange' => 10,
            'employer_percentage' => 0,
            'status' => 1,
            'liability_account_id' => self::ACCOUNT_STATE,
            'effective_from' => $this->effectiveFrom,
        ])->save();

        $this->line('   salary bounds ' . $was . '  ->  0 - 50,000,000');
    }

    /** Employment fund: 1% from the employer. Already right; dated and checked. */
    protected function configureEmploymentFund(): void
    {
        $this->line('');
        $this->info('National Employment Fund — 1% employer');

        $fne = TaxSetting::whereNull('tax_group_id')
            ->where('title', 'LIKE', '%Employment Fund%')->first();

        if (!$fne) {
            throw new \RuntimeException('The employment fund row is missing; not inventing one.');
        }

        $fne->fill([
            'tax_type' => 1,
            'paid_by' => 'employer',
            'min_amount' => 0,
            'max_amount' => 50000000,
            'percentange' => 0,
            'employer_percentage' => 1,
            'max_no_taxable_amount' => 0,
            'status' => 1,
            'liability_account_id' => self::ACCOUNT_SOCIAL,
            'effective_from' => $this->effectiveFrom,
        ])->save();

        $this->line('   1.00% employer on the whole of pay');
    }

    /**
     * Say which groups are actually progressive, since the payslip prints it.
     *
     * is_progressive is shown on the payroll screen, the payslip and the Excel
     * export, and every group was flagged as progressive whatever it did. On
     * the old configuration that was simply untrue: the income tax was a table
     * of flat amounts per band, charged one band at a time, and a payslip
     * calling it progressive was a claim a member of staff could have queried.
     *
     * It is true of the income tax NOW. Each band carries its own marginal
     * rate with a threshold chosen so the charge is continuous across the
     * boundary, which reproduces the statutory progressive tax exactly -- that
     * is what the 11 972 rows of the barème were checked against. The other
     * groups charge a flat amount per band or a single rate, so they are
     * labelled for what they are.
     */
    protected function labelProgressiveGroups(): void
    {
        $this->line('');
        $this->info('Labelling which groups are progressive');

        $labels = [
            self::GROUP_PIT => [1, 'marginal rates by band — genuinely progressive'],
            self::GROUP_CRTV => [0, 'a flat amount per band'],
            self::GROUP_CFC => [0, 'one rate on the whole of pay'],
            self::GROUP_TDL => [0, 'a flat amount per band'],
        ];

        foreach ($labels as $id => [$progressive, $why]) {
            $group = TaxGroup::find($id);

            if (!$group) {
                continue;
            }

            $was = (int) $group->is_progressive;
            $group->is_progressive = $progressive;
            $group->save();

            $this->line(sprintf('   %-38s %-14s %s',
                $group->title,
                $progressive ? 'progressive' : 'not progressive',
                $was === $progressive ? '(unchanged)' : '— ' . $why));
        }
    }

    /**
     * Give a start date to the brackets this command does not otherwise touch.
     *
     * An undated bracket is effective on every date there has ever been. That
     * is not merely untidy: it defeats the check that refuses a payroll for a
     * month with no rates in force, because one undated group left over from
     * an earlier setup answers for every month. It also means a payroll can
     * never be re-explained -- which is how one on this installation came to
     * hold a figure nothing can re-derive.
     *
     * Their amounts are left exactly as they are. Only the date is added.
     */
    protected function dateTheRest(): void
    {
        $this->line('');
        $this->info('Dating the brackets left otherwise untouched');

        $undated = TaxSetting::where('status', 1)->whereNull('effective_from')->get();

        if ($undated->isEmpty()) {
            $this->line('   none — every active bracket already carries a start date');

            return;
        }

        foreach ($undated->groupBy('tax_group_id') as $groupId => $rows) {
            $group = $groupId ? TaxGroup::find($groupId) : null;

            $this->line(sprintf('   %-38s %d bracket(s) dated %s',
                $group->title ?? 'ungrouped', $rows->count(), $this->effectiveFrom));
        }

        TaxSetting::whereIn('id', $undated->pluck('id'))
            ->update(['effective_from' => $this->effectiveFrom]);
    }

    // -----------------------------------------------------------------------

    /** Write one bracket of a group, keyed on its order so this is re-runnable. */
    protected function writeBracket(int $groupId, array $attributes): int
    {
        $bracket = TaxSetting::where('tax_group_id', $groupId)
            ->where('bracket_order', $attributes['bracket_order'])->first() ?: new TaxSetting();

        $bracket->fill($attributes + [
            'tax_group_id' => $groupId,
            'is_dependent' => 0,
            'depends_on_type' => null,
            'depends_on_id' => null,
            'status' => 1,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => null,
        ]);

        $bracket->tax_group_id = $groupId;
        $bracket->save();

        return (int) $bracket->id;
    }

    /**
     * Retire the brackets of a group that this configuration no longer defines.
     *
     * A bracket that a payroll was once charged under is NOT deleted, even
     * though it is wrong: payroll_tax_lines records which setting produced
     * each line, and deleting the row would leave a paid payroll pointing at
     * nothing, so the ledger could no longer be explained. Those are closed
     * off with effective_to and switched off instead, which takes them out of
     * every future calculation while leaving the history readable.
     *
     * Only a bracket nothing has ever been charged under is deleted.
     */
    protected function pruneBrackets(int $groupId, array $keep): void
    {
        $stale = TaxSetting::where('tax_group_id', $groupId)->whereNotIn('id', $keep)->get();

        if ($stale->isEmpty()) {
            return;
        }

        $closedYesterday = Carbon::parse($this->effectiveFrom)->subDay()->toDateString();

        foreach ($stale as $row) {
            $used = DB::table('payroll_tax_lines')->where('tax_setting_id', $row->id)->count();

            if ($used > 0) {
                $row->status = 0;
                $row->effective_to = $closedYesterday;
                $row->save();

                $this->line(sprintf('   retired bracket #%d "%s" (%s - %s) — %d payroll line(s) refer to it,',
                    $row->id, $row->title, number_format($row->min_amount),
                    number_format($row->max_amount), $used));
                $this->line(sprintf('      so it is closed off at %s rather than deleted', $closedYesterday));

                continue;
            }

            $this->line(sprintf('   removed unused bracket #%d "%s" (%s - %s)',
                $row->id, $row->title, number_format($row->min_amount), number_format($row->max_amount)));

            $row->delete();
        }
    }

    // -----------------------------------------------------------------------

    /**
     * Does the configuration reproduce the barème?
     *
     * Values read off the printed table, evaluated through the live engine.
     * The barème rounds to the franc, so one franc either way is agreement.
     */
    protected function verify(): bool
    {
        // Taken from the barème itself, not recomputed from the model, and
        // chosen to sit inside every one of the five brackets and immediately
        // either side of all four boundaries -- which is where a wrong
        // threshold or a wrong edge would show up first.
        $expected = [
            63250 => 0,            // below the threshold: nothing is due
            63500 => 12,           // the first franc of tax
            90000 => 1755,         // bracket 1, the institution's own range
            100000 => 2413,
            110000 => 3071,
            120000 => 3729,
            170000 => 7019,
            180000 => 7677,        // the institution's highest salary
            316500 => 16659,       // bracket 1, just below the edge
            316750 => 16680,       // bracket 2, just above it
            443250 => 29165,       // bracket 2, top
            500000 => 38500,       // bracket 3
            696500 => 70824,       // bracket 3, top
            1000000 => 140717,     // bracket 4
            1785750 => 321675,     // bracket 5, just past the CNPS cap
            3000000 => 619167,     // bracket 5, near the top of the table
        ];

        $group = TaxGroup::find(self::GROUP_PIT);
        $ok = true;

        $this->line(sprintf('   %-14s %-12s %-12s %s', 'gross', 'barème', 'configured', ''));

        foreach ($expected as $salary => $want) {
            $got = (int) round($group->calculateTax($salary)['amount']);
            $agrees = abs($got - $want) <= 1;
            $ok = $ok && $agrees;

            $this->line(sprintf('   %-14s %-12s %-12s %s',
                number_format($salary), number_format($want), number_format($got),
                $agrees ? 'ok' : '*** differs by ' . ($got - $want)));
        }

        // Every salary must land in a band: a hole is what caused the original
        // error, and it must now be impossible rather than merely absent.
        $holes = [];

        foreach ($this->staff() as $user) {
            if ($group->gapFor((float) $user->basic_salary) !== null) {
                $holes[] = $user->name . ' on ' . number_format($user->basic_salary);
            }
        }

        $this->line('');
        $this->line('   salaries falling in a hole: ' . ($holes === [] ? 'none' : count($holes)));

        foreach ($holes as $h) {
            $this->line('      ' . $h);
        }

        return $ok && $holes === [];
    }

    /** Old charge against new, for every member of staff. */
    protected function reconcile(array $before): void
    {
        $this->line('');
        $this->info('What changes for each member of staff');
        $this->line(str_repeat('=', 78));
        $this->line(sprintf('   %-30s %-10s %-11s %-11s %-11s',
            'staff', 'salary', 'was', 'now', 'difference'));

        $totalWas = 0.0;
        $totalNow = 0.0;

        foreach ($this->staff() as $user) {
            $was = $before[$user->id]['employee'] ?? 0.0;
            $now = $this->chargeFor((float) $user->basic_salary)['employee'];

            $totalWas += $was;
            $totalNow += $now;

            $this->line(sprintf('   %-30s %-10s %-11s %-11s %+11s',
                mb_substr($user->name ?? ('user ' . $user->id), 0, 30),
                number_format($user->basic_salary),
                number_format($was, 0), number_format($now, 0),
                number_format($now - $was, 0)));
        }

        $this->line('   ' . str_repeat('-', 75));
        $this->line(sprintf('   %-30s %-10s %-11s %-11s %+11s',
            'total withheld each month', '',
            number_format($totalWas, 0), number_format($totalNow, 0),
            number_format($totalNow - $totalWas, 0)));
    }

    /** What this command deliberately leaves for someone to decide. */
    protected function notTouched(): void
    {
        $this->line('');
        $this->warn('Left alone, and why');
        $this->line(str_repeat('-', 78));

        $tdl = TaxGroup::find(self::GROUP_TDL);
        $this->line(sprintf('  %s (group %d, %d bands)',
            $tdl->title ?? 'Local Development Tax', self::GROUP_TDL, $tdl ? $tdl->brackets()->count() : 0));
        $this->line('     This barème excludes it by its own title ("hors taxe de developpement');
        $this->line('     local"). The group is already contiguous from 0, so it is left as it');
        $this->line('     stands — but it has not been checked against the TDL schedule.');
        $this->line('');

        $accident = TaxGroup::find(self::GROUP_ACCIDENT);

        if ($accident) {
            $this->line(sprintf('  %s (group %d, %d bands, %s)',
                $accident->title, self::GROUP_ACCIDENT, $accident->brackets()->count(),
                $accident->status ? 'on' : 'off'));
            $this->line('     An employer obligation whose rate depends on the sector');
            $this->line('     classification. It needs a number from the institution, so it is');
            $this->line('     left off rather than guessed at.');
            $this->line('');
        }

        $this->line('  Pay below ' . number_format(self::BAREME_FLOOR) . ', for CRTV and the housing fund');
        $this->line('     The barème starts at 52,000 and that first row already carries CCF 520');
        $this->line('     and CRTV 750, so both bite below the income-tax threshold. What is due');
        $this->line('     under 52,000 the document does not say, so nothing is charged there.');
        $this->line('');
        $this->line('     Two consequences worth confirming before payroll is run:');
        $this->line('       - staff paid 52,000 to 62,000 will now be charged CRTV and the');
        $this->line('         housing fund, where the old floor of 62,000 charged them nothing;');
        $this->line('       - staff paid under 52,000 are charged neither, which is an absence');
        $this->line('         of evidence rather than an exemption.');
    }
}
