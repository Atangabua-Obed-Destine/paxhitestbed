<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\TransactionMapping;
use App\Services\BudgetReconciliationService;
use App\Services\LedgerSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Would syncing every unposted transaction make a budget sheet agree with the
 * ledger? Answered by doing it, then undoing it.
 *
 * A model of the outcome would have to predict where each posting lands, which
 * account class, which sheet line, whether the category has a line at all —
 * and would be wrong exactly where it matters. So this runs the real sync, with
 * the real rule and the real posting code, inside a transaction, reconciles the
 * sheet against the result, and rolls the whole thing back. What it reports is
 * what the button would do, not an estimate of it.
 *
 * Nothing is written. Two side effects are worth knowing about on production:
 *   - while it runs it holds the journal-numbering row, so a payment recorded
 *     in those few seconds waits for it to finish;
 *   - database auto-increment ids used during the simulation are not reused,
 *     so the next real journal entry's internal id skips a few. Entry numbers
 *     are not affected; they are rolled back with everything else.
 */
class LedgerSyncPreview extends Command
{
    protected $signature = 'ledger:sync-preview
                            {budget : Budget sheet id, as in admin/budget-sheet/{id}}
                            {--show=20 : How many refused transactions to list}';

    protected $description = 'Simulate syncing every unposted transaction and say whether a budget sheet would then agree with the ledger. Writes nothing.';

    public function handle(LedgerSyncService $sync, BudgetReconciliationService $reconciliation): int
    {
        $budget = Budget::find($this->argument('budget'));

        if (!$budget) {
            $this->error('No budget sheet with id ' . $this->argument('budget') . '.');

            return self::FAILURE;
        }

        // The same window the sheet itself reconciles over.
        $from = $budget->start_date?->format('Y-m-d');
        $to = $budget->end_date?->format('Y-m-d');

        $this->line('');
        $this->info(sprintf('Budget sheet %d — %s  (%s → %s)', $budget->id, $budget->title, $from ?: 'start', $to ?: 'today'));
        $this->line('Simulation only. Nothing will be written.');

        $before = $reconciliation->reconcile($from, $to);
        $this->section('Agreement with the accounts, now', $before);

        [$items, $amounts] = $this->unposted($from, $to);

        $this->line('');
        $this->line(sprintf('  Unposted paid transactions in range: %d, worth %s', count($items), $this->money(array_sum($amounts))));

        $result = ['posted' => [], 'refused' => [], 'failed' => [], 'totals' => ['posted' => 0, 'refused' => 0, 'failed' => 0, 'amount' => 0.0]];

        // The simulation's postings log "auto-mapped successfully" as they go.
        // Those lines would describe work that is about to be undone, so the
        // log is muted for the duration rather than left saying otherwise.
        $channel = config('logging.default');
        $muted = config('logging.channels.null') !== null;

        if ($muted) {
            Log::setDefaultDriver('null');
        }

        DB::beginTransaction();

        try {
            foreach (array_chunk($items, LedgerSyncService::MAX_PER_REQUEST) as $chunk) {
                $part = $sync->sync($chunk);

                foreach (['posted', 'refused', 'failed'] as $bucket) {
                    $result[$bucket] = array_merge($result[$bucket], $part[$bucket]);
                    $result['totals'][$bucket] += $part['totals'][$bucket];
                }

                $result['totals']['amount'] += $part['totals']['amount'];
            }

            $after = $reconciliation->reconcile($from, $to);
        } finally {
            DB::rollBack();

            if ($muted) {
                Log::setDefaultDriver($channel);
            }
        }

        $this->line('');
        $this->line(sprintf('  Syncing would post:   %d, worth %s', $result['totals']['posted'], $this->money($result['totals']['amount'])));
        $this->line(sprintf('  It would refuse:      %d', $result['totals']['refused']));

        if ($result['totals']['failed']) {
            $this->warn(sprintf('  It would FAIL on:     %d  (errors, not refusals — see below)', $result['totals']['failed']));
        }

        $this->listRefusals($result, $amounts);

        $this->section('Agreement with the accounts, after syncing', $after);

        $this->verdict($budget, $after, $result, $amounts);

        $this->line('');
        $this->line('Nothing was written. The simulation was rolled back.');

        return self::SUCCESS;
    }

    /**
     * Paid transactions in range with no active posting.
     *
     * Unpaid fees are left out: they are bills, not money, and carry no amount
     * the sheet or the ledger would count.
     *
     * @return array{0:array<int,array{type:string,id:int}>, 1:array<string,float>}
     */
    private function unposted(?string $from, ?string $to): array
    {
        $posted = TransactionMapping::where('status', 'active')
            ->get(['transaction_type', 'transaction_id'])
            ->map(fn ($m) => $m->transaction_type . ':' . $m->transaction_id)
            ->flip();

        $sources = [
            ['fee', 'fees', 'pay_date', 'paid_amount'],
            ['income', 'incomes', 'date', 'amount'],
            ['expense', 'expenses', 'date', 'amount'],
            ['payment_plan_payment', 'payment_plan_payments', 'payment_date', 'amount'],
        ];

        $items = [];
        $amounts = [];

        foreach ($sources as [$type, $table, $dateColumn, $amountColumn]) {
            $rows = DB::table($table)
                ->where($amountColumn, '>', 0)
                ->whereNotNull($dateColumn)
                ->when($from, fn ($q) => $q->whereDate($dateColumn, '>=', $from))
                ->when($to, fn ($q) => $q->whereDate($dateColumn, '<=', $to))
                ->get(['id', $amountColumn . ' as amount']);

            foreach ($rows as $row) {
                $key = $type . ':' . $row->id;

                if (!isset($posted[$key])) {
                    $items[] = ['type' => $type, 'id' => (int) $row->id];
                    $amounts[$key] = (float) $row->amount;
                }
            }
        }

        return [$items, $amounts];
    }

    private function section(string $title, array $reconciliation): void
    {
        $this->line('');
        $this->line('  ' . $title . ': ' . ($reconciliation['agrees'] ? 'AGREES' : 'DOES NOT AGREE'));

        $this->table(
            ['Section', 'This sheet', 'Ledger', 'Difference', ''],
            array_map(fn ($s) => [
                $s['label'] . ' (class ' . $s['class'] . ')',
                $this->money($s['sheet']),
                $this->money($s['ledger']),
                $this->money($s['difference']),
                $s['agrees'] ? 'ok' : 'OFF',
            ], $reconciliation['sections'])
        );

        foreach ($reconciliation['issues'] as $issue) {
            $this->warn('  issue: ' . $issue['detail']);
        }
    }

    private function listRefusals(array $result, array $amounts): void
    {
        $problems = array_merge(
            array_map(fn ($r) => $r + ['kind' => 'refused'], $result['refused']),
            array_map(fn ($r) => $r + ['kind' => 'failed', 'code' => 'failed'], $result['failed'])
        );

        if (!$problems) {
            return;
        }

        // Grouped by reason, so fifty rows in one unmapped category read as one
        // problem with one fix, not fifty.
        $groups = [];

        foreach ($problems as $p) {
            $key = $p['code'] . '|' . $p['reason'];
            $groups[$key] ??= ['code' => $p['code'], 'reason' => $p['reason'], 'count' => 0, 'amount' => 0.0, 'examples' => []];
            $groups[$key]['count']++;
            $groups[$key]['amount'] += $amounts[$p['type'] . ':' . $p['id']] ?? 0;

            if (count($groups[$key]['examples']) < 3) {
                $groups[$key]['examples'][] = $p['type'] . ' #' . $p['id'];
            }
        }

        uasort($groups, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $this->line('');
        $this->line('  Not posted, by reason:');

        foreach (array_slice($groups, 0, (int) $this->option('show')) as $g) {
            $note = $g['code'] === 'payment_plan'
                ? '  (fine — its instalments post on their own)'
                : '';

            $this->line(sprintf('    %4d  %14s  %s%s', $g['count'], $this->money($g['amount']), $g['reason'], $note));
            $this->line('                          e.g. ' . implode(', ', $g['examples']));
        }
    }

    private function verdict(Budget $budget, array $after, array $result, array $amounts): void
    {
        $this->line('');

        if ($after['agrees']) {
            $this->info(sprintf('VERDICT: WILL AGREE. After syncing, budget sheet %d agrees with the ledger in every section, with no open issues.', $budget->id));

            return;
        }

        $this->error(sprintf('VERDICT: WILL NOT AGREE. Syncing alone will not make budget sheet %d agree. What will still be wrong:', $budget->id));

        foreach ($after['sections'] as $s) {
            if (!$s['agrees']) {
                $this->line(sprintf('  - %s is off by %s (sheet %s, ledger %s)',
                    $s['label'], $this->money($s['difference']), $this->money($s['sheet']), $this->money($s['ledger'])));
            }
        }

        foreach ($after['issues'] as $issue) {
            $this->line('  - ' . $issue['detail']);
        }

        $blocking = array_filter($result['refused'], fn ($r) => !in_array($r['code'], ['payment_plan'], true));

        if ($blocking) {
            $this->line(sprintf('  - %d transaction(s) would be refused (listed above). Fix those first — most often by adding the missing default mapping in Mapping Settings — then run this again.', count($blocking)));
        }

        if ($result['failed']) {
            $this->line(sprintf('  - %d transaction(s) would fail with an error. Check the log after a real sync, or report it.', count($result['failed'])));
        }
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, '.', ',');
    }
}
