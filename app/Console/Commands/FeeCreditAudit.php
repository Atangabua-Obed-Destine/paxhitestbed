<?php

namespace App\Console\Commands;

use App\Services\FeeCreditReconciliation;
use Illuminate\Console\Command;

/**
 * The fee credit audit, for running on production.
 *
 *   php artisan fees:credit-audit                      report only; writes nothing
 *   php artisan fees:credit-audit --void-duplicates    cancel unbacked credit (asks first)
 *   php artisan fees:credit-audit --correct-ledger     repost fees at cash received (asks first)
 *
 * Everything is worked out from the data on the machine it runs on, so it finds
 * production's own figures rather than the testbed's. Take a database backup
 * before either correction. The same corrections are on Fees → Credit audit.
 */
class FeeCreditAudit extends Command
{
    protected $signature = 'fees:credit-audit
                            {--void-duplicates : Cancel unspent credit that no payment backs}
                            {--correct-ledger : Repost fee entries at the cash actually received}
                            {--force : Do not ask for confirmation}
                            {--show=20 : Rows to list for each finding}';

    protected $description = 'Report how credit moved between fees affects the fee figures, and optionally void duplicated credit and correct fee postings.';

    public function handle(FeeCreditReconciliation $audit): int
    {
        $show = max(1, (int) $this->option('show'));
        $money = fn ($amount) => number_format((float) $amount, 0);

        $this->line('');
        $this->info('Fee credit audit — ' . now()->format('d M Y H:i'));

        // 1 ------------------------------------------------------------------
        $collected = $audit->collected();
        $this->line('');
        $this->comment('1. Collected, net of credit moved between fees');
        $this->line('   Overpayment credit applied to another fee was never taken off the fee it came from,');
        $this->line('   so a plain sum of paid_amount counts it twice. The reports now use the net figure.');
        $this->table(
            ['Category', 'Fees', 'paid_amount', 'Moved to other fees', 'Net collected'],
            $collected['by_category']->map(fn ($r) => [$r->category ?? '(none)', $r->fees, $money($r->paid), $money($r->moved_out), $money($r->net_paid)])->all()
        );
        $this->line(sprintf('   Total paid_amount %s  −  moved %s  =  collected %s',
            $money($collected['paid']), $money($collected['moved_out']), $money($collected['net_paid'])));

        // 2 ------------------------------------------------------------------
        $duplicates = $audit->duplicateCredits();
        $this->line('');
        $this->comment('2. Credit raised twice for the same overpayment');

        if ($duplicates->isEmpty()) {
            $this->line('   None. Every credit is backed by a payment.');
        } else {
            $this->table(
                ['Fee', 'Student', 'Due', 'Paid', 'Really overpaid', 'Credited', 'Unbacked', 'Can void', 'Already spent'],
                $duplicates->take($show)->map(fn ($d) => [
                    '#' . $d['fee_id'], $d['student_id'], $money($d['due']), $money($d['paid']),
                    $money($d['genuine_overpayment']), $money($d['credited']), $money($d['excess']),
                    $money($d['voidable']), $money($d['already_spent']),
                ])->all()
            );
            $this->line(sprintf('   Unbacked credit %s, of which %s is unspent and can be voided with --void-duplicates.',
                $money($duplicates->sum('excess')), $money($duplicates->sum('voidable'))));

            if ($duplicates->sum('already_spent') > 0.009) {
                $this->warn(sprintf('   %s was already applied to other fees and needs manual review.', $money($duplicates->sum('already_spent'))));
            }
        }

        // 3 ------------------------------------------------------------------
        $ledger = $audit->ledgerExposure();
        $this->line('');
        $this->comment('3. Ledger — fee postings against cash actually received');
        $this->line(sprintf('   Fee postings %s against %s actually collected: overstated by %s.',
            $money($ledger['posted']), $money($ledger['net_paid']), $money($ledger['overstated'])));
        $this->line('   Each credit applied to a fee was posted as cash received (Dr cash/bank, Cr tuition income),');
        $this->line('   although the cash had already been posted on the fee it came from.');

        if ($ledger['by_month']->isNotEmpty()) {
            $this->table(['Month applied', 'Applications', 'Amount'],
                $ledger['by_month']->map(fn ($r) => [$r->month, $r->applications, $money($r->amount)])->all());
        }

        $corrections = $audit->ledgerCorrections();
        $this->line(sprintf('   %d fee posting(s) differ from cash received; --correct-ledger reposts them.', $corrections->count()));

        // 4 ------------------------------------------------------------------
        $unevidenced = $audit->unevidencedPayments();
        $this->line('');
        $this->comment('4. Paid with no receipt on file — confirm against the cash book');

        if ($unevidenced->isEmpty()) {
            $this->line('   None.');
        } else {
            $this->table(['Fee', 'Category', 'Student', 'Paid', 'Receipts + credit', 'No receipt'],
                $unevidenced->take($show)->map(fn ($u) => ['#' . $u['fee_id'], $u['category'], $u['student'], $money($u['paid']), $money($u['evidenced']), $money($u['unevidenced'])])->all());
            $this->line(sprintf('   %d fees, %s in total.', $unevidenced->count(), $money($unevidenced->sum('unevidenced'))));
        }

        // 5 ------------------------------------------------------------------
        $orphans = $audit->orphanApplications();
        $this->line('');
        $this->comment('5. Credit applications pointing at a deleted fee');
        $this->line($orphans->isEmpty()
            ? '   None.'
            : sprintf('   %d, totalling %s. Audit trail only if the money was re-transferred; check each student.',
                $orphans->count(), $money($orphans->sum('amount_applied'))));

        // Corrections -----------------------------------------------------

        if (!$this->option('void-duplicates') && !$this->option('correct-ledger')) {
            $this->line('');
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        if ($this->option('void-duplicates')) {
            $this->line('');

            if ($duplicates->sum('voidable') <= 0.009) {
                $this->info('There is no unspent duplicated credit to void.');
            } elseif ($this->option('force') || $this->confirm(sprintf(
                'Void %s of duplicated credit across %d fee(s)? Take a database backup first.',
                $money($duplicates->sum('voidable')), $duplicates->count()
            ))) {
                $result = $audit->voidDuplicateCredits(null, 'fees:credit-audit');
                $this->info(sprintf('Voided %s across %d credit(s).', $money($result['amount']), $result['credits']));

                if ($result['needs_review'] > 0.009) {
                    $this->warn(sprintf('%s of unbacked credit was already spent and still needs manual review.', $money($result['needs_review'])));
                }
            } else {
                $this->line('Credit was not voided.');
            }
        }

        if ($this->option('correct-ledger')) {
            $this->line('');

            if ($corrections->isEmpty()) {
                $this->info('Every fee posting already matches the cash received.');
            } elseif ($this->option('force') || $this->confirm(sprintf(
                'Repost %d fee(s)? Cash and tuition income each change by %s. Take a database backup first.',
                $corrections->count(), $money($corrections->sum('difference'))
            ))) {
                $result = $audit->correctLedger(null);
                $this->info(sprintf('Reposted %d fee(s); postings reduced by %s (%d dated today, their period being closed).',
                    $result['corrected'], $money($result['amount']), $result['landed_today']));

                foreach ($result['failed'] as $failure) {
                    $this->warn(sprintf('Fee #%d: %s', $failure['fee_id'], $failure['reason']));
                }
            } else {
                $this->line('The ledger was not changed.');
            }
        }

        return self::SUCCESS;
    }
}
