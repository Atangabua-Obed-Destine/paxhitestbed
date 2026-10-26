<?php

namespace App\Console\Commands;

use App\Services\Accounting\LedgerHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Does every transaction still line up with its posting?
 *
 * Run this before closing a month, before a board meeting, and after any bulk
 * edit. It compares the three records of the same money — the source row, its
 * transaction mapping, and the journal entry — and reports every place they
 * have stopped agreeing.
 *
 * Read-only unless --repair is passed, and even then it touches only the one
 * fault that needs no decision: a mapping whose transaction has been deleted
 * and which never posted anything. Every other finding names the command that
 * owns its repair, because every other repair turns on a judgement this
 * cannot make.
 */
class LedgerDoctor extends Command
{
    protected $signature = 'accounting:ledger-doctor
                            {--details : List every affected row rather than a sample}
                            {--group= : Only one group — Mappings, Entries, Agreement or Placement}
                            {--repair : Retire mappings whose transaction is gone and which never posted}';

    protected $description = 'Check that every transaction, its mapping and its journal entry still agree';

    public function handle(LedgerHealth $health): int
    {
        $this->line('');
        $this->info('Ledger health');
        $this->line(str_repeat('=', 92));
        $this->line('  database : ' . DB::getDatabaseName());
        $this->line(sprintf('  %s transactions mapped, %s journal entries, %s of them live',
            number_format(DB::table('transaction_mappings')->where('status', 'active')->count()),
            number_format(DB::table('journal_entries')->whereNull('deleted_at')->count()),
            number_format(DB::table('journal_entries')->whereNull('deleted_at')->where('is_reversed', 0)->count())));
        $this->line('');

        // One filter, used for both the listing and the re-read after a
        // repair — otherwise --repair ends with a verdict counting checks the
        // run was told not to look at.
        $only = $this->option('group');
        $narrow = fn (array $all) => $only
            ? array_values(array_filter($all, fn ($c) => strcasecmp($c['group'], $only) === 0))
            : $all;

        $checks = $narrow($health->checks());

        if ($checks === []) {
            $this->error('  No such group. Try Mappings, Entries, Agreement or Placement.');

            return self::FAILURE;
        }

        /*
         * Findings first, then what passed.
         *
         * Grouping and severity pull in opposite directions: sorting worst
         * first scattered the group headings through the output, and grouping
         * first buried a critical finding under a page of ticks. So the
         * findings are listed on their own, worst first and tagged with their
         * group, and the passing checks follow, grouped, as the record that
         * they were actually run.
         */
        $findings = array_values(array_filter($checks, fn ($c) => $c['state'] !== LedgerHealth::OK));
        $passing = array_values(array_filter($checks, fn ($c) => $c['state'] === LedgerHealth::OK));

        if ($findings !== []) {
            $this->info(sprintf('Found something (%d)', count($findings)));
            $this->line(str_repeat('-', 92));

            foreach ($findings as $check) {
                $this->renderCheck($check);
            }
        }

        if ($passing !== []) {
            $this->info(sprintf('Passing (%d)', count($passing)));
            $this->line(str_repeat('-', 92));

            $group = null;

            foreach ($passing as $check) {
                if ($check['group'] !== $group) {
                    $group = $check['group'];
                    $this->line('  ' . $group);
                }

                $this->renderCheck($check);
            }
        }

        $this->line('');
        $this->line(str_repeat('=', 92));

        if ($this->option('repair')) {
            $this->repair($health);

            // Re-read, so the verdict describes the books as they now are
            // rather than as they were before the repair ran.
            $health->forget();
            $checks = $narrow($health->checks());
        }

        return $this->verdict($health, $checks);
    }

    /**
     * The one repair this command owns.
     *
     * It is offered rather than performed: a write to the mappings table is
     * not something to discover afterwards in a log, even when the write
     * cannot change a total.
     */
    protected function repair(LedgerHealth $health): void
    {
        $stale = array_values(array_filter($health->checks(),
            fn ($c) => $c['key'] === 'mapping_orphan_unposted'));

        $rows = $stale === [] ? [] : $stale[0]['rows'];

        if ($rows === []) {
            $this->line('');
            $this->info('  --repair: nothing to retire.');
            $this->line('');

            return;
        }

        $this->line('');
        $this->warn(sprintf('  --repair would retire %d stale mapping(s):', count($rows)));

        foreach ($rows as $row) {
            $this->line('           ' . $this->describe((array) $row));
        }

        $this->line('');
        $this->line('  Nothing is posted for these, so no ledger total changes.');

        if (!$this->confirm('  Retire them?', false)) {
            $this->line('  Left as they were.');
            $this->line('');

            return;
        }

        $retired = $health->retireUnpostedOrphans();

        $this->info(sprintf('  Retired %d mapping(s).', count($retired)));
        $this->line('');
    }

    protected function renderCheck(array $check): void
    {
        $mark = match ($check['state']) {
            LedgerHealth::OK => '  OK   ',
            LedgerHealth::WARNING => '  CHECK',
            default => '  FIX  ',
        };

        $figure = $check['count'] === 0
            ? '—'
            : number_format($check['count']) . ($check['value'] > 0
                ? ' · ' . number_format($check['value'], 2)
                : '');

        // The group travels with the finding, since findings are not grouped.
        $line = $check['state'] === LedgerHealth::OK
            ? sprintf('%s    %-50s %s', $mark, $check['label'], $figure)
            : sprintf('%s  [%s] %-45s %s', $mark, $check['group'], $check['label'], $figure);

        match ($check['state']) {
            LedgerHealth::OK => $this->line($line),
            LedgerHealth::WARNING => $this->warn($line),
            default => $this->error($line),
        };

        if ($check['state'] === LedgerHealth::OK) {
            return;
        }

        $this->line('         ' . $check['detail']);

        if ($check['action']) {
            $this->line('         → ' . $check['action']);
        }

        $rows = $check['rows'];
        $show = $this->option('details') ? $rows : array_slice($rows, 0, 5);

        foreach ($show as $row) {
            $this->line('           ' . $this->describe((array) $row));
        }

        if (!$this->option('details') && count($rows) > count($show)) {
            $this->line(sprintf('           … and %d more (--details to list them)',
                count($rows) - count($show)));
        }

        $this->line('');
    }

    /** One affected row, in whatever shape its check produced. */
    protected function describe(array $row): string
    {
        $parts = [];

        foreach ($row as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value) && str_contains($key, 'amount') || str_contains($key, 'debit')
                || str_contains($key, 'credit') || str_contains($key, 'total')) {
                $value = number_format((float) $value, 2);
            }

            $parts[] = $key . '=' . $value;
        }

        return implode('  ', $parts);
    }

    protected function verdict(LedgerHealth $health, array $checks): int
    {
        $worst = $health->worstState($checks);
        $concerns = $health->concerns($checks);

        if ($worst === LedgerHealth::OK) {
            $this->info('  Every transaction, mapping and journal entry agrees. Nothing to do.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->line(sprintf('  %d of %d checks found something.', $concerns, count($checks)));

        if ($worst === LedgerHealth::CRITICAL) {
            $this->error('  Some of it affects what the reports say. Settle those before presenting figures.');
            $this->line('');

            // Non-zero so a scheduled run or a deploy check can notice.
            return self::FAILURE;
        }

        $this->warn('  Nothing that makes a report wrong, but worth knowing about.');
        $this->line('');

        return self::SUCCESS;
    }
}
