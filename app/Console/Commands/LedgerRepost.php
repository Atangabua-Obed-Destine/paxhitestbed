<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\Income;
use App\Services\Accounting\LedgerHealth;
use App\Services\TransactionAutoMapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Re-post transactions whose journal entry has stopped agreeing with them.
 *
 * This exists because the obvious advice does not work. The ledger doctor used
 * to say "re-save the transaction", on the reasoning that the observers now
 * re-post on a date change. They do — but only on a CHANGE. Eloquent fires
 * `updated` when something is dirty, so saving a transaction whose values are
 * already correct does nothing at all, and the drifted entry stays exactly as
 * it was. Measured, not assumed: a plain save() and an update() with identical
 * values both left the entry on the wrong day.
 *
 * The only way through the UI was to change the date to something else and
 * change it back, which reverses and re-posts twice and leaves two corrections
 * in the ledger where the books needed one. So the repair belongs here, where
 * it can drive remap() once.
 *
 * It uses remap() rather than editing the entry in place, for the reason
 * payroll:repost gives: the ledger stays balanced by construction, the
 * correction leaves a trail instead of quietly rewriting history, and the
 * transaction mapping is carried along with it.
 *
 * Dry run unless --commit.
 */
class LedgerRepost extends Command
{
    protected $signature = 'accounting:repost
                            {--type= : income or expense. Omit for both.}
                            {--id= : One transaction id. Requires --type.}
                            {--commit : Actually reverse and re-post.}
                            {--user= : Act as this user id; defaults to the first admin.}';

    protected $description = 'Reverse and re-post transactions whose journal entry disagrees with them';

    /** Only these two post through TransactionAutoMapService. */
    protected const TYPES = ['income' => Income::class, 'expense' => Expense::class];

    public function handle(LedgerHealth $health, TransactionAutoMapService $mapper): int
    {
        $type = $this->option('type');

        if ($type !== null && !isset(self::TYPES[$type])) {
            $this->error('  --type must be income or expense.');

            return self::FAILURE;
        }

        if ($this->option('id') && $type === null) {
            $this->error('  --id needs --type, since the two tables number their rows separately.');

            return self::FAILURE;
        }

        $actor = $this->option('user')
            ? \App\User::find($this->option('user'))
            : \App\User::where('is_admin', 1)->orderBy('id')->first();

        if (!$actor) {
            $this->error('  No user to act as. Pass --user=<id>.');

            return self::FAILURE;
        }

        // The journal entry records who posted it, and some of the accounting
        // code reads the authenticated user rather than being passed one.
        Auth::guard('web')->login($actor);

        $targets = $this->targets($health, $type, $this->option('id'));

        $this->line('');
        $this->info('Re-posting drifted transactions');
        $this->line(str_repeat('=', 78));
        $this->line('  database : ' . DB::getDatabaseName());
        $this->line('  acting as: ' . $actor->name . ' (#' . $actor->id . ')');
        $this->line('');

        if ($targets === []) {
            $this->info('  Nothing has drifted. Nothing to re-post.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->line(sprintf('  %d transaction(s) whose entry disagrees with them:', count($targets)));
        $this->line('');

        foreach ($targets as $t) {
            $this->line(sprintf('    %-8s #%-6s entry %-6s dated %s, transaction says %s',
                $t['kind'], $t['id'], $t['entry_id'],
                substr((string) $t['entry_date'], 0, 10), substr((string) $t['source_date'], 0, 10)));
        }

        $this->line('');

        if (!$this->option('commit')) {
            $this->warn('  Dry run. Nothing was changed. Add --commit to re-post.');
            $this->line('');

            return self::SUCCESS;
        }

        return $this->repost($mapper, $targets);
    }

    /**
     * What to re-post.
     *
     * Read from LedgerHealth rather than from a query written here, so this
     * command and the screen can never disagree about what has drifted. A
     * named --id is taken on trust: re-posting something already in agreement
     * is a no-op that costs a reversal, and refusing it would make the escape
     * hatch useless for the case it exists for.
     *
     * @return array<int, array{kind:string,id:int,entry_id:int,entry_date:string,source_date:string}>
     */
    protected function targets(LedgerHealth $health, ?string $type, $id): array
    {
        $drift = [];

        foreach ($health->checks() as $check) {
            if ($check['key'] !== 'date_drift') {
                continue;
            }

            foreach ($check['rows'] as $row) {
                $drift[] = [
                    'kind' => $row->kind,
                    'id' => (int) $row->reference_id,
                    'entry_id' => (int) $row->id,
                    'entry_date' => $row->entry_date,
                    'source_date' => $row->source_date,
                ];
            }
        }

        // Payroll and fee drift is not re-postable from here — they post
        // through their own services, and payroll:repost owns one of them.
        $drift = array_values(array_filter($drift, fn ($d) => isset(self::TYPES[$d['kind']])));

        if ($type !== null) {
            $drift = array_values(array_filter($drift, fn ($d) => $d['kind'] === $type));
        }

        if ($id) {
            $named = array_values(array_filter($drift, fn ($d) => $d['id'] === (int) $id));

            if ($named === []) {
                $model = self::TYPES[$type];
                $row = $model::find($id);

                $named = $row
                    ? [['kind' => $type, 'id' => (int) $id, 'entry_id' => 0,
                        'entry_date' => 'none', 'source_date' => $row->date]]
                    : [];
            }

            $drift = $named;
        }

        return $drift;
    }

    protected function repost(TransactionAutoMapService $mapper, array $targets): int
    {
        $done = 0;
        $failed = [];

        foreach ($targets as $t) {
            $model = self::TYPES[$t['kind']];
            $row = $model::find($t['id']);

            if (!$row) {
                $failed[] = sprintf('%s #%s no longer exists', $t['kind'], $t['id']);
                continue;
            }

            /*
             * remap() is already atomic — it reverses and re-posts inside one
             * transaction and throws rather than leave a transaction with its
             * posting destroyed and nothing put back. Each row gets its own,
             * so one failure does not undo the corrections before it.
             */
            try {
                $result = $mapper->remap($t['kind'], $row->id, $row->category_id, [
                    'amount' => $row->amount,
                    'date' => $row->date,
                    'description' => ($row->category->title ?? ucfirst($t['kind'])) . ' - ' . ($row->title ?? ''),
                ]);

                if ($result === false) {
                    $failed[] = sprintf('%s #%s could not be re-posted — check its category has an account mapping',
                        $t['kind'], $t['id']);
                    continue;
                }

                $done++;
            } catch (\Throwable $e) {
                $failed[] = sprintf('%s #%s: %s', $t['kind'], $t['id'], $e->getMessage());
            }
        }

        $this->line(sprintf('  re-posted : %d', $done));

        if ($failed !== []) {
            $this->warn(sprintf('  left alone: %d', count($failed)));

            foreach ($failed as $why) {
                $this->line('      ' . $why);
            }

            $this->line('');
            $this->line('  Nothing was half-done: each re-post is reversed and replaced in one');
            $this->line('  transaction, so a failure leaves that posting exactly as it was.');
        }

        $this->line('');

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
