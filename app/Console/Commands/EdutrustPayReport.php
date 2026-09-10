<?php

namespace App\Console\Commands;

use App\Models\EdutrustPayOutbox;
use App\Services\EdutrustPay\OutboxService;
use App\Services\EdutrustPay\SettingsResolver;
use App\Services\EdutrustPay\PeriodReportBuilder;
use Carbon\Carbon;
use EdutrustPay\Contract\Canonical;
use EdutrustPay\Contract\Validator;
use Illuminate\Console\Command;

/**
 * Builds this institution's monthly report and queues it for delivery.
 *
 * The report is generated from the ledger, never authored. There is no screen
 * for editing it before it goes.
 */
class EdutrustPayReport extends Command
{
    protected $signature = 'edutrustpay:report
        {period? : Calendar month YYYY-MM. Defaults to last month.}
        {--months= : Build this many months back, oldest first}
        {--sequence=1 : Use 2+ to restate a month already filed}
        {--status= : Force provisional or final instead of deciding from the accounting period}
        {--dry-run : Build and show the figures without queueing anything}
        {--send : Attempt delivery immediately rather than waiting for the flush}';

    protected $description = 'Build the monthly EdutrustPay report from the ledger and queue it.';

    public function handle(PeriodReportBuilder $builder, OutboxService $outbox, Validator $validator): int
    {
        if (! $this->option('dry-run') && ! app(SettingsResolver::class)->isReporting()) {
            $this->error('EdutrustPay reporting is switched off, or no credentials are configured. See Settings, EdutrustPay Reporting.');

            return self::FAILURE;
        }

        $periods = $this->periods();
        $rows = [];
        $failed = 0;

        foreach ($periods as $period) {
            try {
                $payload = $builder->build(
                    $period,
                    (int) $this->option('sequence'),
                    $this->option('status') ?: null
                );
            } catch (\Throwable $e) {
                $this->error("  $period  could not be built: ".$e->getMessage());
                $failed++;

                continue;
            }

            /*
             * Validate before queueing, not on arrival.
             *
             * A malformed payload discovered at the other end costs a round trip
             * over a hotspot and a rejection nobody here sees. Discovered now it
             * is a message on this screen.
             *
             * Warnings are NOT errors: an unbalanced ledger is exactly the kind
             * of thing the console needs to be told about, so it is sent.
             */
            $result = $validator->validate($payload);

            if (! $result['valid']) {
                $this->error("  $period  is not a valid report:");
                foreach ($result['errors'] as $error) {
                    $this->line('    · '.$error);
                }
                $failed++;

                continue;
            }

            $queued = $this->option('dry-run')
                ? null
                : $outbox->enqueue($payload);

            if ($queued && $this->option('send')) {
                $outbox->deliver($queued);
                $queued->refresh();
            }

            $rows[] = [
                $period,
                $payload['status'],
                implode(', ', $payload['capabilities']),
                $this->total($payload, 'income_by_group'),
                $this->total($payload, 'expenditure_by_group'),
                implode(',', array_column($result['warnings'], 'code')) ?: '—',
                $queued?->status ?? 'not queued',
            ];
        }

        $this->table(
            ['Period', 'Status', 'Capabilities', 'Income', 'Expenditure', 'Warnings', 'Outbox'],
            $rows
        );

        if ($this->option('dry-run')) {
            $this->comment('Nothing was queued.');
        } elseif (! $this->option('send')) {
            $this->comment('Queued. Delivery happens on the next `edutrustpay:flush`, or run it now.');
        }

        $this->warnAboutUndeclared();

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function periods(): array
    {
        if ($period = $this->argument('period')) {
            return [$period];
        }

        $count = max(1, (int) ($this->option('months') ?: 1));
        $cursor = Carbon::now()->startOfMonth()->subMonths($count);

        return collect(range(1, $count))
            ->map(fn () => $cursor->addMonth()->format('Y-m'))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function total(array $payload, string $key): string
    {
        $groups = (array) ($payload['financial'][$key] ?? []);

        $total = \EdutrustPay\Contract\Money::sum(array_values($groups));

        return number_format((float) $total->value(), 0, '.', ' ');
    }

    /**
     * Say plainly what is not being reported.
     *
     * Somebody reading the console will see "not yet reporting" against these
     * and should be able to find out why from this end without guessing.
     */
    private function warnAboutUndeclared(): void
    {
        $declared = (array) config('edutrustpay.capabilities', []);
        $all = \EdutrustPay\Contract\Capability::all();
        $missing = array_diff($all, $declared);

        if ($missing === []) {
            return;
        }

        $this->newLine();
        $this->line('<comment>Not declared:</comment> '.implode(', ', $missing));
        $this->line('The console will show these as "not yet reporting" rather than as zero, which is the intent.');

        if (in_array('budget', $missing, true)) {
            $this->line('  budget — this system has no monthly budget; see config/edutrustpay.php for why a twelfth of the annual one is not a substitute.');
        }

        if (in_array('enrolment', $missing, true)) {
            $this->line('  enrolment — needs fee-structure templates pushed down by the body before expected fees mean anything.');
        }
    }
}
