<?php

namespace App\Console\Commands;

use App\Support\Tax\CameroonTaxTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What tax configuration does this installation actually have?
 *
 * Read-only. Written because `tax:configure-cameroon` can only say what it
 * could not find — "no tax group with code PIT" — and that leaves the obvious
 * question unanswered: so what IS here? A school may have no groups at all, or
 * groups somebody created by hand without the codes the configuration keys on,
 * and those two need opposite fixes.
 *
 * Run this first on any server before changing anything.
 */
class TaxInspect extends Command
{
    protected $signature = 'tax:inspect';

    protected $description = 'Report this installation\'s payroll tax configuration, without changing anything';

    public function handle(): int
    {
        $this->line('');
        $this->info('Payroll tax configuration on this installation');
        $this->line(str_repeat('=', 78));
        $this->line('  database : ' . DB::getDatabaseName());
        $this->line('');

        foreach (['tax_groups', 'tax_settings', 'chart_of_accounts', 'payrolls', 'payroll_tax_lines'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error(sprintf('  Table %s does not exist — this installation is behind on migrations.', $table));

                return self::FAILURE;
            }
        }

        $this->groups();
        $this->standalone();
        $this->accounts();
        $this->history();
        $this->verdict();

        return self::SUCCESS;
    }

    protected function groups(): void
    {
        $groups = DB::table('tax_groups')->orderBy('id')->get();

        $this->info(sprintf('Tax groups (%d)', $groups->count()));
        $this->line(str_repeat('-', 78));

        if ($groups->isEmpty()) {
            $this->line('  none — nothing has been configured here yet.');
            $this->line('');

            return;
        }

        $this->line(sprintf('  %-5s %-8s %-32s %-7s %-6s', 'id', 'code', 'title', 'status', 'bands'));

        foreach ($groups as $group) {
            $bands = DB::table('tax_settings')->where('tax_group_id', $group->id)->count();

            $this->line(sprintf('  %-5s %-8s %-32s %-7s %-6s',
                $group->id,
                $group->code ?: '(none)',
                mb_substr($group->title ?? '', 0, 32),
                $group->status ? 'on' : 'off',
                $bands));
        }

        $this->line('');
    }

    protected function standalone(): void
    {
        $rows = DB::table('tax_settings')->whereNull('tax_group_id')->orderBy('id')->get();

        $this->info(sprintf('Ungrouped taxes (%d)', $rows->count()));
        $this->line(str_repeat('-', 78));

        if ($rows->isEmpty()) {
            $this->line('  none');
            $this->line('');

            return;
        }

        foreach ($rows as $row) {
            $this->line(sprintf('  #%-5s %-40s %s%s %s',
                $row->id,
                mb_substr($row->title ?? '', 0, 40),
                $row->tax_type == 2
                    ? 'fixed ' . number_format($row->fixed_amount ?? 0, 2)
                    : $row->percentange . '% / ' . $row->employer_percentage . '% employer',
                $row->is_dependent ? '  [depends on ' . $row->depends_on_type . ' ' . $row->depends_on_id . ']' : '',
                $row->status ? '' : '(off)'));
        }

        $this->line('');
    }

    protected function accounts(): void
    {
        $this->info('Liability accounts the taxes need');
        $this->line(str_repeat('-', 78));

        foreach (['443' => 'Etat - Retenue (State withholdings)', '431' => 'CNPS (social funds)'] as $code => $what) {
            $account = DB::table('chart_of_accounts')->where('account_code', $code)->first();

            $this->line(sprintf('  %-5s %-40s %s', $code, $what,
                $account ? 'present as #' . $account->id : 'MISSING — seed the chart of accounts'));
        }

        $this->line(sprintf('  %-5s %-40s %d accounts in total', '', '', DB::table('chart_of_accounts')->count()));
        $this->line('');
    }

    protected function history(): void
    {
        $this->info('Payroll history');
        $this->line(str_repeat('-', 78));
        $this->line(sprintf('  payrolls, total / paid         : %d / %d',
            DB::table('payrolls')->count(), DB::table('payrolls')->where('status', 1)->count()));
        $this->line(sprintf('  payroll tax lines recorded     : %d', DB::table('payroll_tax_lines')->count()));
        $this->line(sprintf('  payroll journal entries posted : %d',
            DB::table('journal_entries')->where('reference_type', 'payroll')->count()));
        $this->line(sprintf('  tax remittances recorded       : %d',
            Schema::hasTable('tax_remittances') ? DB::table('tax_remittances')->count() : 0));
        $this->line('');
    }

    /** What to do next, said plainly, because that is the whole point of running this. */
    protected function verdict(): void
    {
        $groups = DB::table('tax_groups')->count();
        $coded = DB::table('tax_groups')->whereIn('code', array_keys(CameroonTaxTable::GROUPS))->count();
        $paid = DB::table('payrolls')->where('status', 1)->count();
        $lines = DB::table('payroll_tax_lines')->count();
        $hasAccounts = DB::table('chart_of_accounts')->whereIn('account_code', ['443', '431'])->count() === 2;

        $this->info('What to do next');
        $this->line(str_repeat('-', 78));

        if (!$hasAccounts) {
            $this->warn('  The chart of accounts is incomplete. Seed it first:');
            $this->line('      php artisan db:seed --class=OhadaChartOfAccountsSeeder');
            $this->line('');
        }

        if ($paid > 0 || $lines > 0) {
            $this->warn('  This school has charged payroll already.');
            $this->line('      Replacing the brackets would leave those payrolls pointing at rates that');
            $this->line('      no longer say what they were charged under. Do not run the command');
            $this->line('      without deciding what happens to that history first.');
            $this->line('');

            return;
        }

        if ($groups === 0) {
            $this->line('  Nothing is configured. Install the defaults:');
            $this->line('      php artisan db:seed --class=TaxSettingSeeder');
            $this->line('      php artisan tax:configure-cameroon --dry-run');
            $this->line('');

            return;
        }

        if ($coded === 0) {
            $this->warn('  There are groups here, but none carries a code the configuration knows.');
            $this->line('      They were most likely created by hand. Nothing has been charged yet, so');
            $this->line('      the seeder will adopt any whose title matches and fill in the rest:');
            $this->line('          php artisan db:seed --class=TaxSettingSeeder');
            $this->line('          php artisan tax:inspect            # confirm the codes are now set');
            $this->line('          php artisan tax:configure-cameroon --dry-run');
            $this->line('');

            return;
        }

        if ($coded < count(CameroonTaxTable::GROUPS)) {
            $this->line(sprintf('  %d of %d expected groups are present by code. Fill in the rest:',
                $coded, count(CameroonTaxTable::GROUPS)));
            $this->line('      php artisan db:seed --class=TaxSettingSeeder');
            $this->line('      php artisan tax:configure-cameroon --dry-run');
            $this->line('');

            return;
        }

        $this->line('  The groups are all present. Review and apply:');
        $this->line('      php artisan tax:configure-cameroon --dry-run');
        $this->line('');
    }
}
