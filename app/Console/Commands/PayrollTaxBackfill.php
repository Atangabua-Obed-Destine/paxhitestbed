<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollTaxLine;
use App\Services\PayrollTaxBreakdownService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Record the itemised taxes for payrolls that were paid before this existed.
 *
 * The parts are recomputed from the configuration effective for each payroll's
 * own salary month, and written ONLY when they add up to the total that
 * payroll actually charged. A payroll that does not reconcile is reported and
 * skipped: a breakdown that is merely plausible is worse than none, because
 * nothing downstream could tell it from a correct one, and it would be read
 * straight onto a tax declaration.
 *
 * Dry run unless --commit, like defaults:install.
 */
class PayrollTaxBackfill extends Command
{
    protected $signature = 'payroll:backfill-tax-lines
                            {--commit : Write the lines. Without this, nothing is saved.}
                            {--payroll= : Only this payroll id.}
                            {--force : Rewrite payrolls that already have lines.}';

    protected $description = 'Record the per-tax breakdown for payrolls already paid';

    public function handle(PayrollTaxBreakdownService $breakdown): int
    {
        $commit = (bool) $this->option('commit');

        $payrolls = Payroll::where('status', 1)
            ->when($this->option('payroll'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id')
            ->get();

        if ($payrolls->isEmpty()) {
            $this->info('No paid payrolls to record.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line($commit
            ? '  Writing the per-tax breakdown for ' . $payrolls->count() . ' paid payroll(s).'
            : '  DRY RUN — nothing will be saved. Add --commit to write.');
        $this->line('');

        $written = 0;
        $skipped = 0;
        $refused = [];

        DB::beginTransaction();

        try {
            foreach ($payrolls as $payroll) {
                $existing = PayrollTaxLine::where('payroll_id', $payroll->id)->count();

                if ($existing > 0 && !$this->option('force')) {
                    $this->line(sprintf('  payroll #%-4d  already recorded (%d lines) — left alone', $payroll->id, $existing));
                    $skipped++;
                    continue;
                }

                $lines = $breakdown->forPayroll($payroll);
                $totals = $breakdown->totals($lines);

                if (!$breakdown->reconciles($payroll, $lines)) {
                    $refused[] = sprintf(
                        'payroll #%d: itemised to %s / %s but the payroll charged %s / %s',
                        $payroll->id,
                        number_format($totals['employee'], 2),
                        number_format($totals['employer'], 2),
                        number_format((float) $payroll->tax, 2),
                        number_format((float) ($payroll->employer_tax ?? 0), 2)
                    );
                    continue;
                }

                $this->line(sprintf(
                    '  payroll #%-4d  %s  %d taxes  employee %s  employer %s',
                    $payroll->id,
                    substr((string) $payroll->salary_month, 0, 7),
                    count($lines),
                    number_format($totals['employee'], 2),
                    number_format($totals['employer'], 2)
                ));

                foreach ($lines as $line) {
                    $this->line(sprintf('                  %-34s %12s %12s',
                        substr($line['label'], 0, 34),
                        number_format($line['employee'], 2),
                        number_format($line['employer'], 2)));
                }

                $breakdown->record($payroll);
                $written++;
            }

            if ($commit) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('  Nothing was written: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->line(sprintf('  %d recorded, %d already held, %d refused.', $written, $skipped, count($refused)));

        foreach ($refused as $reason) {
            $this->warn('  REFUSED  ' . $reason);
        }

        if ($refused) {
            $this->line('');
            $this->warn('  A refused payroll is not a failure of this command. It means the tax');
            $this->warn('  configuration has changed since that payroll was paid, so the parts no');
            $this->warn('  longer add up to the total charged. Recording them would put figures on');
            $this->warn('  a declaration that nobody was ever paid against.');
        }

        if (!$commit) {
            $this->line('');
            $this->info('  Dry run — nothing saved. Re-run with --commit to write.');
        }

        return self::SUCCESS;
    }
}
