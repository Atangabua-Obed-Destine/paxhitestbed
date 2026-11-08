<?php

namespace App\Console\Commands;

use App\Models\EdutrustPayOutbox;
use App\Services\EdutrustPay\DaybookExporter;
use App\Services\EdutrustPay\OutboxService;
use App\Services\EdutrustPay\SettingsResolver;
use Carbon\Carbon;
use EdutrustPay\Contract\Daybook;
use Illuminate\Console\Command;

/**
 * Sends each month's daybook to the console, separately from the monthly report
 * so that a large daybook failing can never hold the report back.
 *
 * Unchanged months are skipped, and a month that has changed is filed again at the
 * next sequence, where the console keeps both versions.
 */
class EdutrustPayDaybook extends Command
{
    protected $signature = 'edutrustpay:daybook
        {period? : Calendar month YYYY-MM. Defaults to last month.}
        {--months= : The last N complete months, oldest first}
        {--dry-run : Build and count the lines without queueing anything}
        {--send : Attempt delivery immediately rather than waiting for the flush}';

    protected $description = 'Queue each month\'s daybook for the EdutrustPay console. These lines name people.';

    public function handle(DaybookExporter $exporter, OutboxService $outbox): int
    {
        if (! $this->option('dry-run') && ! app(SettingsResolver::class)->isReporting()) {
            $this->error('EdutrustPay reporting is switched off, or no credentials are configured.');

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        foreach ($this->periods() as $period) {
            try {
                $latest = $outbox->latestDaybookFor($period);
                $document = $exporter->document($period, $latest?->sequence ?? 1);

                if ($latest?->status === EdutrustPayOutbox::DELIVERED) {
                    if (! Daybook::changed($latest->payload, $document)) {
                        $rows[] = [$period, count($document['lines']), $document['money_in'], $document['money_out'], 'unchanged'];

                        continue;
                    }

                    $document = $exporter->document($period, $latest->sequence + 1);
                }

                if ($errors = Daybook::errors($document)) {
                    $this->error("  $period is not valid: ".implode('; ', array_slice($errors, 0, 3)));
                    $failed++;

                    continue;
                }
            } catch (\Throwable $e) {
                $this->error("  $period could not be built: ".$e->getMessage());
                $failed++;

                continue;
            }

            $queued = $this->option('dry-run') ? null : $outbox->enqueue($document, 'daybook');

            if ($queued && $this->option('send')) {
                $outbox->deliver($queued);
                $queued = $outbox->latestDaybookFor($period);
            }

            $rows[] = [$period, count($document['lines']), $document['money_in'], $document['money_out'], $queued?->status ?? 'not queued'];
        }

        $this->table(['Period', 'Lines', 'Money in', 'Money out', 'Outbox'], $rows);

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
        // Ends at the last COMPLETE month, as the report does.
        $cursor = Carbon::now()->startOfMonth()->subMonths($count + 1);

        return collect(range(1, $count))
            ->map(fn () => $cursor->addMonth()->format('Y-m'))
            ->all();
    }
}
