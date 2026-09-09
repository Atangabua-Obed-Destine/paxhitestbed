<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Services\PayrollAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Reverse a posted payroll's journal entry and post it again.
 *
 * Used when the posting RULES changed after a payroll was paid — as when
 * withheld tax stopped going to one lump account and started being split by
 * the body it is owed to. The payroll itself is untouched: same gross, same
 * net, same pay date. Only where the money sits in the ledger changes.
 *
 * It drives the existing reverse-and-repost path rather than editing lines in
 * place, for the same reason unpay() does: the ledger stays balanced by
 * construction, and the correction leaves a trail instead of quietly rewriting
 * history. An entry edited in place would also disagree with the payroll's own
 * transaction mapping.
 *
 * Dry run unless --commit.
 */
class PayrollRepost extends Command
{
    protected $signature = 'payroll:repost
                            {payroll? : Payroll id. Omit for every posted payroll.}
                            {--commit : Actually reverse and re-post.}
                            {--user= : Act as this user id; defaults to the first admin.}';

    protected $description = 'Reverse and re-post a paid payroll so it follows the current posting rules';

    public function handle(PayrollAccountingService $accounting): int
    {
        $commit = (bool) $this->option('commit');

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

        $payrolls = Payroll::where('status', 1)
            ->when($this->argument('payroll'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id')
            ->get();

        if ($payrolls->isEmpty()) {
            $this->info('No posted payroll to re-post.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line($commit
            ? '  Reversing and re-posting ' . $payrolls->count() . ' payroll(s) as ' . $actor->id . '.'
            : '  DRY RUN — the ledger will not be changed. Add --commit to apply.');

        $before = $this->trialBalance();

        DB::beginTransaction();

        try {
            foreach ($payrolls as $payroll) {
                $this->line('');
                $this->line(sprintf('  payroll #%d — %s', $payroll->id, substr((string) $payroll->salary_month, 0, 7)));
                $this->showEntry($payroll, '    before: ');

                $reversal = $accounting->reversePayrollJournalEntry($payroll, $actor->id);

                if (!$reversal) {
                    $this->warn('    nothing to reverse — no journal entry is mapped to this payroll; skipped');
                    continue;
                }

                $entry = $accounting->createPayrollJournalEntry($payroll, $actor->id);

                if (!$entry) {
                    throw new \RuntimeException("payroll #{$payroll->id} reversed but could not be re-posted; rolling back");
                }

                $this->showEntry($payroll, '    after:  ');
            }

            $after = $this->trialBalance();

            $this->line('');
            $this->line(sprintf('  trial balance before: debits %s  credits %s',
                number_format($before['debit'], 2), number_format($before['credit'], 2)));
            $this->line(sprintf('  trial balance after:  debits %s  credits %s',
                number_format($after['debit'], 2), number_format($after['credit'], 2)));

            // A reversal and a re-post both balance, so the totals move by the
            // same amount on each side. If they do not, something posted
            // one-sided and the whole thing is abandoned.
            if (abs(($after['debit'] - $after['credit']) - ($before['debit'] - $before['credit'])) > 0.01) {
                throw new \RuntimeException('the ledger no longer balances; rolling back');
            }

            if ($commit) {
                DB::commit();
                $this->info('  Committed.');
            } else {
                DB::rollBack();
                $this->info('  Dry run — nothing changed. Re-run with --commit to apply.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('  Nothing was changed: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** The current payroll entry's credit lines, so the change is visible. */
    private function showEntry(Payroll $payroll, string $prefix): void
    {
        $entry = JournalEntry::where('reference_type', 'payroll')
            ->where('reference_id', $payroll->id)
            ->where('is_posted', 1)
            ->orderBy('id', 'desc')
            ->first();

        if (!$entry) {
            $this->line($prefix . 'no posted entry');

            return;
        }

        $credits = DB::table('journal_entry_lines as l')
            ->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.journal_entry_id', $entry->id)
            ->where('l.credit', '>', 0)
            ->orderBy('l.line_number')
            ->get(['a.account_code', 'a.account_name', 'l.credit']);

        $summary = $credits->map(fn ($c) => $c->account_code . ' ' . number_format((float) $c->credit, 2))
            ->implode('   ');

        $this->line($prefix . $entry->entry_number . '   ' . $summary);
    }

    /** Total posted debits and credits across the whole ledger. */
    private function trialBalance(): array
    {
        $row = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.is_posted', 1)
            ->whereNull('e.deleted_at')
            ->selectRaw('COALESCE(SUM(l.debit),0) as debit, COALESCE(SUM(l.credit),0) as credit')
            ->first();

        return ['debit' => (float) $row->debit, 'credit' => (float) $row->credit];
    }
}
