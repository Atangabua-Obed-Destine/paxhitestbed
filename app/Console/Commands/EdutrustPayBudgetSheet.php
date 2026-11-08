<?php

namespace App\Console\Commands;

use App\Models\EdutrustPayOutbox;
use App\Services\EdutrustPay\BudgetSheetExporter;
use App\Services\EdutrustPay\OutboxService;
use App\Services\EdutrustPay\SettingsResolver;
use Carbon\Carbon;
use EdutrustPay\Contract\BudgetSheet;
use Illuminate\Console\Command;

/**
 * Sends the budget sheet as it stood at the end of each month. A month no budget
 * covers is skipped and said so, rather than sent as an empty sheet.
 */
class EdutrustPayBudgetSheet extends Command
{
    protected $signature = 'edutrustpay:budget-sheet
        {period? : Calendar month YYYY-MM. Defaults to last month.}
        {--months= : The last N complete months, oldest first}
        {--dry-run : Build the sheets without queueing anything}
        {--send : Attempt delivery immediately rather than waiting for the flush}';

    protected $description = 'Queue the budget sheet at the end of each month for the EdutrustPay console.';

    public function handle(BudgetSheetExporter $exporter, OutboxService $outbox): int
    {
        if (! $this->option('dry-run') && ! app(SettingsResolver::class)->isReporting()) {
            $this->error('EdutrustPay reporting is switched off, or no credentials are configured.');

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        foreach ($this->periods() as $period) {
            try {
                $latest = $outbox->latestBudgetSheetFor($period);
                $document = $exporter->document($period, $latest?->sequence ?? 1);

                if ($document === null) {
                    $rows[] = [$period, '—', '—', 'no budget covers this month'];

                    continue;
                }

                if ($latest?->status === EdutrustPayOutbox::DELIVERED) {
                    if (! BudgetSheet::changed($latest->payload, $document)) {
                        $rows[] = [$period, $document['budget']['title'], count($document['lines']), 'unchanged'];

                        continue;
                    }

                    $document = $exporter->document($period, $latest->sequence + 1);
                }

                if ($errors = BudgetSheet::errors($document)) {
                    $this->error("  $period is not valid: ".implode('; ', array_slice($errors, 0, 3)));
                    $failed++;

                    continue;
                }
            } catch (\Throwable $e) {
                $this->error("  $period could not be built: ".$e->getMessage());
                $failed++;

                continue;
            }

            $queued = $this->option('dry-run') ? null : $outbox->enqueue($document, 'budget_sheet');

            if ($queued && $this->option('send')) {
                $outbox->deliver($queued);
                $queued = $outbox->latestBudgetSheetFor($period);
            }

            $rows[] = [$period, $document['budget']['title'], count($document['lines']), $queued?->status ?? 'not queued'];
        }

        $this->table(['Period', 'Budget', 'Lines', 'Outbox'], $rows);

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
        $cursor = Carbon::now()->startOfMonth()->subMonths($count + 1);

        return collect(range(1, $count))
            ->map(fn () => $cursor->addMonth()->format('Y-m'))
            ->all();
    }
}
