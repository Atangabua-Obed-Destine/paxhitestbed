<?php

namespace App\Console\Commands;

use Database\Seeders\BudgetLineSeeder;
use Database\Seeders\DefaultBudgetMappingSeeder;
use Database\Seeders\OhadaChartOfAccountsSeeder;
use Database\Seeders\OhadaDetailAccountsSeeder;
use Database\Seeders\OhadaMissingAccountsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Installs the accounting defaults: the chart of accounts, the budget sheet
 * structure, and the mappings that connect a category to both.
 *
 * The seeders are create-only, so they fill gaps and never touch a row that
 * already exists. This command exists so that installing them on a live system
 * is something you can look at before you do it: without --commit it runs the
 * real seeders inside a transaction and rolls it back, so the report is not a
 * prediction of what would happen — it is what did happen, undone.
 *
 * That matters more than it sounds. A preview built from separate "what if"
 * code drifts from the code that actually writes; this one cannot, because it
 * is the same code path.
 *
 * `db:seed` deliberately does not run these. It would drag in PermissionSeeder,
 * AdminSeeder and SettingSeeder alongside them, which is not what anyone means
 * when they say they want a chart of accounts.
 */
class DefaultsInstall extends Command
{
    protected $signature = 'defaults:install
        {--only= : chart, budget-lines, mappings — default all}
        {--commit : Actually write. Without this the command only reports}
        {--refresh-names : Also correct names and French translations on existing rows. Never touches structure}';

    protected $description = 'Install the chart of accounts, budget lines and default mappings';

    /**
     * Seeders in dependency order. Mappings resolve both accounts and budget
     * lines by lookup and silently skip whatever is missing, so they must run
     * last or they quietly do half a job.
     */
    protected const GROUPS = [
        'chart' => [
            OhadaChartOfAccountsSeeder::class,
            OhadaDetailAccountsSeeder::class,
            OhadaMissingAccountsSeeder::class,
        ],
        'budget-lines' => [
            BudgetLineSeeder::class,
        ],
        'mappings' => [
            DefaultBudgetMappingSeeder::class,
        ],
    ];

    public function handle(): int
    {
        $only = $this->option('only');

        if ($only !== null && !isset(self::GROUPS[$only])) {
            $this->error("Unknown group '{$only}'. Choose one of: " . implode(', ', array_keys(self::GROUPS)) . '.');

            return self::FAILURE;
        }

        $groups = $only === null ? self::GROUPS : [$only => self::GROUPS[$only]];
        $commit = (bool) $this->option('commit');

        $this->line('');
        $this->line($commit
            ? '<fg=yellow>Writing.</> Rows that already exist are left untouched.'
            : '<fg=cyan>Reporting only.</> Nothing will be written. Re-run with --commit to apply.');
        $this->line('');

        DB::beginTransaction();

        try {
            $created = 0;
            $skipped = 0;
            $unresolved = [];

            foreach ($groups as $name => $seeders) {
                $this->line("<options=bold>{$name}</>");

                foreach ($seeders as $class) {
                    $seeder = $this->laravel->make($class);
                    $seeder->setContainer($this->laravel);

                    // Kept quiet: each seeder's own summary line would double up
                    // on the totals this command prints.
                    $seeder->run();

                    $created += $seeder->createdCount;
                    $skipped += $seeder->skippedCount;
                    $unresolved = array_merge($unresolved, $seeder->unresolved);

                    $this->report($class, $seeder);
                }

                $this->line('');
            }

            if ($this->option('refresh-names')) {
                $this->refreshNames();
            }

            $this->summarise($created, $skipped, $unresolved, $commit);

            if ($commit) {
                DB::commit();
                $this->line('<fg=green>Committed.</>');
            } else {
                DB::rollBack();
                $this->line('<fg=cyan>Rolled back — nothing was written.</>');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Rolled back: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function report(string $class, $seeder): void
    {
        $short = class_basename($class);

        $this->line(sprintf(
            '  %-34s %s created, %d already present',
            $short,
            $seeder->createdCount > 0
                ? "<fg=green>{$seeder->createdCount}</>"
                : (string) $seeder->createdCount,
            $seeder->skippedCount
        ));

        // The first few are worth naming. A full list of a fresh install's 121
        // accounts would bury the part that needs reading.
        foreach (array_slice($seeder->createdItems, 0, 8) as $item) {
            $this->line("      + {$item}");
        }

        if (count($seeder->createdItems) > 8) {
            $this->line('      + … ' . (count($seeder->createdItems) - 8) . ' more');
        }
    }

    /**
     * Correct labels only, on rows that already exist.
     *
     * Deliberately narrow. Names are cosmetic — a wrong one is confusing but
     * harmless — whereas sort_order, parent_id, budget_line_id,
     * account_category and is_active all carry decisions somebody made, and
     * two of them decide whether money appears on a report at all.
     */
    protected function refreshNames(): void
    {
        $this->line('<options=bold>names</>');

        $updated = 0;

        foreach ((new OhadaChartOfAccountsSeeder())->definedAccounts() as $account) {
            $row = \App\Models\ChartOfAccount::where('account_code', $account['account_code'])->first();

            if (!$row) {
                continue;
            }

            $wanted = array_filter([
                'account_name' => $account['account_name'] ?? null,
                'account_name_fr' => $account['account_name_fr'] ?? null,
            ]);

            $changed = array_filter($wanted, fn ($value, $key) => $row->{$key} !== $value, ARRAY_FILTER_USE_BOTH);

            if ($changed === []) {
                continue;
            }

            $this->line("  ~ {$row->account_code}: " . implode(', ', array_map(
                fn ($k) => "{$row->{$k}} → {$changed[$k]}",
                array_keys($changed)
            )));

            $row->update($changed);
            $updated++;
        }

        $this->line("  {$updated} name(s) corrected");
        $this->line('');
    }

    protected function summarise(int $created, int $skipped, array $unresolved, bool $commit): void
    {
        $this->line(sprintf(
            '<options=bold>%d</> to create, <options=bold>%d</> already present and left untouched.',
            $created,
            $skipped
        ));

        if ($unresolved !== []) {
            $this->line('');
            $this->warn('Could not be placed (' . count($unresolved) . '):');

            foreach ($unresolved as $item) {
                $this->line("  ! {$item}");
            }

            $this->line('  These are defaults whose category, line or account is absent — not rows that already exist.');
        }

        $this->line('');
    }
}
