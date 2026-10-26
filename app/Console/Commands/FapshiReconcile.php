<?php

namespace App\Console\Commands;

use App\Models\FapshiTransaction;
use App\Services\Payment\FapshiClient;
use App\Services\Payment\PlatformFeeGatewayPayment;
use Illuminate\Console\Command;

/**
 * Catch up on payments whose notification never arrived.
 *
 * Fapshi sends each webhook exactly once and does not retry, whatever this
 * server answers. A deploy, a restart, a dropped connection or a few seconds of
 * downtime is therefore enough to lose the only notice that a student paid —
 * and the student would be left looking at a portal that still wants money they
 * have already handed over.
 *
 * So the webhook is a convenience and this is the guarantee. It asks the
 * gateway about every payment still in flight and acts on the answer, through
 * the same code path the webhook uses.
 *
 * It also reports what the gateway actually charged. The school passes the
 * commission on to students as a configured percentage; comparing that against
 * the difference between what each student paid and what reached the school is
 * the only way to find out whether the configured figure is right.
 */
class FapshiReconcile extends Command
{
    protected $signature = 'fapshi:reconcile
                            {--minutes=3 : Leave a payment alone until it is at least this old}
                            {--limit=200 : Most payments to check in one run}
                            {--dry-run : Report what would be checked, change nothing}';

    protected $description = 'Confirm Fapshi platform-fee payments whose webhook never arrived';

    public function handle(FapshiClient $client, PlatformFeeGatewayPayment $gateway): int
    {
        if (!$client->isEnabled() || !$client->isConfigured()) {
            // Scheduled on every install, configured on few. Saying nothing
            // and succeeding keeps an unconfigured school's logs clean.
            $this->line('Fapshi is not configured here — nothing to reconcile.');

            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $open = FapshiTransaction::whereIn('status', FapshiTransaction::OPEN_STATUSES)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $this->info(sprintf('%d payment(s) in flight and older than %s minutes.',
            $open->count(), $this->option('minutes')));

        if ($this->option('dry-run')) {
            foreach ($open as $transaction) {
                $this->line(sprintf('  would check %-14s %-10s %s',
                    $transaction->external_id, $transaction->status, $transaction->created_at));
            }

            $this->costReport();

            return self::SUCCESS;
        }

        $counts = ['credited' => 0, 'failed' => 0, 'expired' => 0, 'still open' => 0, 'flagged' => 0, 'deferred' => 0];

        foreach ($open as $transaction) {
            // A link past its 24 hours cannot be paid, so there is nothing to
            // ask about — and asking anyway spends one of six polls a minute.
            if ($transaction->linkHasExpired() && !filled($transaction->trans_id)) {
                $transaction->status = 'expired';
                $transaction->failure_reason = 'The payment link passed its 24-hour life without being paid.';
                $transaction->save();
                $counts['expired']++;

                continue;
            }

            $result = $gateway->syncFromProvider($transaction);

            if (!empty($result['rate_limited'])) {
                // Six per minute, per transaction. Stopping the run is the
                // right response: the rest keep until the next tick.
                $this->warn('  rate-limited by the gateway — stopping, the rest will be picked up next run.');
                $counts['deferred'] += 1;
                break;
            }

            $key = match ($result['status']) {
                'successful' => 'credited',
                'failed' => 'failed',
                'expired' => 'expired',
                'mismatched', 'refundable' => 'flagged',
                default => 'still open',
            };

            $counts[$key]++;

            $this->line(sprintf('  %-14s %-11s %s',
                $transaction->external_id, $result['status'], $result['message']));
        }

        $this->line('');

        foreach ($counts as $label => $count) {
            if ($count > 0) {
                $this->line(sprintf('  %-11s %d', $label, $count));
            }
        }

        $this->attentionReport();
        $this->costReport();

        return self::SUCCESS;
    }

    /**
     * Payments where money arrived and access did not follow.
     *
     * These cannot be resolved by software — somebody has to refund a student
     * or chase a short payment — so the only useful thing to do is make sure
     * they are never quiet.
     */
    protected function attentionReport(): void
    {
        $needing = FapshiTransaction::whereIn('status', FapshiTransaction::NEEDS_ATTENTION_STATUSES)->get();

        if ($needing->isEmpty()) {
            return;
        }

        $this->line('');
        $this->warn(sprintf('%d payment(s) need a person to look at them:', $needing->count()));

        foreach ($needing as $transaction) {
            $this->line(sprintf('  %-14s %-11s %s %s — %s',
                $transaction->external_id,
                $transaction->status,
                number_format((float) ($transaction->amount_received ?? 0), 2),
                $transaction->currency,
                $transaction->failure_reason));
        }
    }

    /**
     * What the gateway really costs, against what the school assumed.
     *
     * Fapshi does not publish its rate in the API documentation, so the
     * surcharge students are charged starts as an estimate. This turns it into
     * a measurement as soon as real payments exist.
     */
    protected function costReport(): void
    {
        $confirmed = FapshiTransaction::where('status', 'successful')
            ->whereNotNull('revenue')
            ->whereNotNull('amount_received')
            ->get();

        if ($confirmed->isEmpty()) {
            return;
        }

        $setting = app(\App\Services\PlatformFeeAccess::class)->setting();

        $this->line('');
        $this->info('What the gateway charged, against what students were charged for it');

        $totalTaken = 0.0;
        $totalSurcharge = 0.0;

        foreach ($confirmed as $transaction) {
            $taken = (float) $transaction->commissionTaken();
            $surcharge = (float) $transaction->charged_amount - (float) $transaction->fee_amount;

            $totalTaken += $taken;
            $totalSurcharge += $surcharge;
        }

        $this->line(sprintf('  payments confirmed        : %d', $confirmed->count()));
        $this->line(sprintf('  gateway took, in total    : %s', number_format($totalTaken, 2)));
        $this->line(sprintf('  collected for it in total : %s', number_format($totalSurcharge, 2)));

        $difference = round($totalSurcharge - $totalTaken, 2);

        if (abs($difference) < 1) {
            $this->line('  the configured surcharge matches what the gateway takes.');

            return;
        }

        if ($difference < 0) {
            $this->warn(sprintf('  the school is absorbing %s — the configured surcharge is too low.',
                number_format(abs($difference), 2)));
        } else {
            $this->warn(sprintf('  students have been overcharged by %s in total — the configured surcharge is too high.',
                number_format($difference, 2)));
        }

        if (!$setting) {
            return;
        }

        /*
         * The gateway's cut is a percentage of what the STUDENT PAID, not of
         * the fee behind it — measured at 65 on a 2,163 charge, which is 3.0%
         * of the charge and 3.25% of the fee. Dividing by the fee overstates
         * the rate and would have the school raise the surcharge it is already
         * charging too much of.
         */
        $totalCharged = $confirmed->sum(fn ($t) => (float) $t->amount_received);
        $rate = $totalCharged > 0 ? $totalTaken / $totalCharged : 0.0;

        $this->line(sprintf('  configured  : %s%% of the fee, plus %s',
            rtrim(rtrim(number_format((float) $setting->gateway_fee_percent, 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format((float) $setting->gateway_fee_fixed, 2, '.', ''), '0'), '.')));
        $this->line(sprintf('  measured    : %.2f%% of the amount charged', $rate * 100));

        /*
         * What to configure, derived rather than guessed.
         *
         * The gateway takes r of the total, so to be left with the fee the
         * total must be fee / (1 - r) — which is a markup on the fee of
         * r / (1 - r), not of r. At 3% that is 3.093%, so 3.1% is the honest
         * figure and anything above it overcharges the student.
         */
        if ($rate > 0 && $rate < 1) {
            $needed = ceil($rate / (1 - $rate) * 10000) / 100;

            $this->line(sprintf('  to break even, set the percentage to %.2f%% and the fixed amount to 0',
                $needed));
        }
    }
}
