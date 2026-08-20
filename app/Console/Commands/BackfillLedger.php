<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\Fee;
use App\Models\Income;
use App\Models\TransactionMapping;
use App\Services\TransactionAutoMapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Posts historical income, expenses and fee receipts to the ledger.
 *
 * New transactions post themselves through the model observers. Everything
 * recorded before the chart of accounts and mappings existed never did, so the
 * ledger starts empty and every statutory report with it.
 *
 * Reports what it would do and changes nothing unless --commit is given.
 * Posting hundreds of real financial records is not something to trigger by
 * accident, and the preview is the point: it names every category that has no
 * mapping, so gaps are found before rows are written rather than after.
 */
class BackfillLedger extends Command
{
    protected $signature = 'ledger:backfill
        {--from= : Only transactions on or after this date (Y-m-d)}
        {--to= : Only transactions on or before this date (Y-m-d)}
        {--type=all : all, expense, income or fee}
        {--commit : Actually write. Without this the command only reports}
        {--chunk=100 : Rows loaded at a time}';

    protected $description = 'Post historical transactions to the double-entry ledger';

    protected TransactionAutoMapService $mapper;

    /** @var array<string,array{count:int,amount:float}> */
    protected array $skipped = [];

    public function __construct(TransactionAutoMapService $mapper)
    {
        parent::__construct();
        $this->mapper = $mapper;
    }

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $type = $this->option('type');
        $commit = (bool) $this->option('commit');

        if (!in_array($type, ['all', 'expense', 'income', 'fee'], true)) {
            $this->error('--type must be one of: all, expense, income, fee');
            return self::FAILURE;
        }

        // A closed period must not gain new entries: its figures have already
        // been reported.
        $closed = DB::table('accounting_periods')->where('is_closed', true)->count();
        if ($closed > 0) {
            $this->warn("{$closed} accounting period(s) are closed. Entries dated inside them will be skipped by the posting service.");
        }

        $this->line('');
        $this->info($commit ? 'BACKFILL — writing to the ledger' : 'BACKFILL — preview only, nothing will be written');
        $this->line(sprintf('  range: %s to %s   type: %s', $from ?: 'the beginning', $to ?: 'today', $type));
        $this->line('');

        $totals = ['posted' => 0, 'already' => 0, 'skipped' => 0, 'failed' => 0, 'amount' => 0.0];

        if ($type === 'all' || $type === 'expense') {
            $this->backfillType('expense', Expense::query(), 'date', 'amount', $from, $to, $commit, $totals);
        }
        if ($type === 'all' || $type === 'income') {
            $this->backfillType('income', Income::query(), 'date', 'amount', $from, $to, $commit, $totals);
        }
        if ($type === 'all' || $type === 'fee') {
            $this->backfillType('fee', Fee::query()->where('paid_amount', '>', 0), 'pay_date', 'paid_amount', $from, $to, $commit, $totals);
        }

        $this->line('');
        $this->table(
            ['Posted', 'Already posted', 'Skipped (no mapping)', 'Failed', 'Value posted'],
            [[
                $totals['posted'],
                $totals['already'],
                $totals['skipped'],
                $totals['failed'],
                number_format($totals['amount']),
            ]]
        );

        if ($this->skipped) {
            $this->line('');
            $this->warn('These have no account mapping and were not posted:');
            foreach ($this->skipped as $label => $info) {
                $this->line(sprintf('  %-52s %4d  %12s', $label, $info['count'], number_format($info['amount'])));
            }
            $this->line('  Map them under Accounting → Transaction Mappings, then run again.');
        }

        if (!$commit) {
            $this->line('');
            $this->info('Nothing was written. Re-run with --commit to post.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string,mixed> $totals passed by reference
     */
    protected function backfillType(string $type, $query, string $dateColumn, string $amountColumn, ?string $from, ?string $to, bool $commit, array &$totals): void
    {
        // No orderBy here: chunkById walks the primary key and a different sort
        // breaks its cursor, so rows fall between chunks and never post. Date
        // order would only be cosmetic anyway — each entry carries its own date.
        $query = (clone $query)
            ->when($from, fn ($q) => $q->whereDate($dateColumn, '>=', $from))
            ->when($to, fn ($q) => $q->whereDate($dateColumn, '<=', $to));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->line("  {$type}: nothing in range");
            return;
        }

        // Already-posted rows are known up front, so a re-run is cheap and the
        // count of "already posted" is honest rather than inferred per row.
        $posted = TransactionMapping::where('transaction_type', $type)
            ->where('status', 'active')
            ->pluck('transaction_id')
            ->flip();

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat("  {$type}: %current%/%max% [%bar%] %message%");
        $bar->setMessage('');
        $bar->start();

        $seen = ['posted' => 0, 'already' => 0, 'skipped' => 0, 'failed' => 0];

        $query->chunkById((int) $this->option('chunk'), function ($rows) use ($type, $dateColumn, $amountColumn, $commit, &$totals, &$seen, $posted, $bar) {
            foreach ($rows as $row) {
                $bar->advance();

                if ($posted->has($row->id)) {
                    $totals['already']++;
                    $seen['already']++;
                    continue;
                }

                $amount = (float) $row->{$amountColumn};
                $categoryId = $row->category_id ?? null;

                if (!$this->hasMapping($type, $categoryId)) {
                    $totals['skipped']++;
                    $seen['skipped']++;
                    $this->noteSkipped($type, $categoryId, $amount);
                    continue;
                }

                if (!$commit) {
                    $totals['posted']++;
                    $seen['posted']++;
                    $totals['amount'] += $amount;
                    continue;
                }

                $result = $this->mapper->autoMap($type, $row->id, $categoryId, [
                    'amount' => $amount,
                    'date' => $row->{$dateColumn},
                    'description' => $row->title ?? ($type . ' #' . $row->id),
                ]);

                if ($result) {
                    $totals['posted']++;
                    $seen['posted']++;
                    $totals['amount'] += $amount;
                } else {
                    $totals['failed']++;
                    $seen['failed']++;
                }
            }
        });

        $bar->finish();
        $this->line('');

        // Every row in range must end up in exactly one bucket. A mismatch means
        // rows were skipped by the chunking itself, which would silently
        // under-post the ledger — invisible once the command has finished.
        $accounted = $seen['posted'] + $seen['already'] + $seen['skipped'] + $seen['failed'];
        if ($accounted !== $total) {
            $this->error(sprintf(
                '  %s: %d rows in range but %d accounted for — %d unexplained. Nothing further will be posted for this type.',
                $type, $total, $accounted, $total - $accounted
            ));
            throw new \RuntimeException("Backfill of {$type} did not account for every row.");
        }
    }

    protected function hasMapping(string $type, $categoryId): bool
    {
        static $cache = [];

        $mappingType = $type === 'fee' ? 'fee_category' : ($type . '_category');
        $key = $mappingType . ':' . ($categoryId ?? 'null');

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = DB::table('default_account_mappings')
                ->where('mapping_type', $mappingType)
                ->where('status', 'active')
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->when(!$categoryId, fn ($q) => $q->whereNull('category_id'))
                ->exists();
        }

        return $cache[$key];
    }

    protected function noteSkipped(string $type, $categoryId, float $amount): void
    {
        $table = $type === 'fee' ? 'fees_categories' : ($type === 'income' ? 'income_categories' : 'expense_categories');
        $name = $categoryId
            ? (DB::table($table)->where('id', $categoryId)->value('title') ?? "category {$categoryId}")
            : 'no category';

        $label = $type . ' / ' . $name;
        $this->skipped[$label] ??= ['count' => 0, 'amount' => 0.0];
        $this->skipped[$label]['count']++;
        $this->skipped[$label]['amount'] += $amount;
    }
}
