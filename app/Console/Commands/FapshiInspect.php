<?php

namespace App\Console\Commands;

use App\Models\FapshiTransaction;
use App\Models\PlatformFeePayment;
use App\Services\Payment\FapshiClient;
use App\Services\PlatformFeeAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What state is online payment of the platform fee actually in?
 *
 * Read-only. Written for the same reason as tax:inspect: the thing that
 * refuses to work can only tell you what it could not find, and that leaves the
 * obvious question — so what IS configured here? — unanswered. Three separate
 * switches have to be on and they live in two different places, so "the button
 * is not showing" has several causes that need different fixes.
 *
 * Run this first on any server before changing anything.
 */
class FapshiInspect extends Command
{
    protected $signature = 'fapshi:inspect';

    protected $description = 'Report this installation\'s Fapshi platform-fee configuration, without changing anything';

    public function handle(FapshiClient $client, PlatformFeeAccess $access): int
    {
        $this->line('');
        $this->info('Online platform-fee payment on this installation');
        $this->line(str_repeat('=', 78));
        $this->line('  database : ' . DB::getDatabaseName());
        $this->line('');

        if (!Schema::hasTable('fapshi_transactions')) {
            $this->error('  Table fapshi_transactions does not exist — this installation is behind on migrations.');

            return self::FAILURE;
        }

        $this->gateway($client);
        $this->fee($access);
        $this->verdict($access);
        $this->transactions();
        $this->attention();

        return self::SUCCESS;
    }

    protected function gateway(FapshiClient $client): void
    {
        $this->info('The gateway (.env)');
        $this->line(str_repeat('-', 78));
        $this->line(sprintf('  %-26s %s', 'enabled', $client->isEnabled() ? 'yes' : 'no'));
        $this->line(sprintf('  %-26s %s', 'environment', $client->environment()));
        $this->line(sprintf('  %-26s %s', 'base url', $client->baseUrl()));
        $mismatch = $client->credentialEnvironmentMismatch();

        $this->line(sprintf('  %-26s %s', 'api user / key', $client->isConfigured()
            ? ($mismatch ? 'set, but they are ' . strtoupper($mismatch) . ' credentials' : 'set')
            : 'NOT SET'));

        if ($mismatch) {
            $this->warn(sprintf('      The key is a %s key and the environment is %s. Nothing will work,'
                . ' and on a live site nothing would be collected.', $mismatch, $client->environment()));
        }
        $this->line(sprintf('  %-26s %s', 'webhook secret', $client->hasWebhookSecret()
            ? 'set'
            : 'NOT SET — every webhook will be refused'));
        $this->line('');
    }

    protected function fee(PlatformFeeAccess $access): void
    {
        $setting = $access->setting();

        $this->info('The fee (database)');
        $this->line(str_repeat('-', 78));

        if (!$setting) {
            $this->line('  no platform fee has been configured.');
            $this->line('');

            return;
        }

        $this->line(sprintf('  %-26s %s', 'fee charged at all', $setting->is_enabled ? 'yes' : 'no'));
        $this->line(sprintf('  %-26s %s', 'online payment offered', $setting->online_payment_enabled ? 'yes' : 'no'));
        $this->line(sprintf('  %-26s %s %s', 'fee amount',
            number_format((float) $setting->fee_amount, 2), $setting->currency));
        $this->line(sprintf('  %-26s %s%% + %s = %s',
            'gateway commission',
            rtrim(rtrim(number_format((float) $setting->gateway_fee_percent, 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format((float) $setting->gateway_fee_fixed, 2, '.', ''), '0'), '.'),
            number_format($setting->surchargeAmount())));
        $this->line(sprintf('  %-26s %s %s', 'student is charged',
            number_format($setting->chargeAmount()), $setting->currency));
        $this->line('');
    }

    /** Said plainly, because it is the whole reason for running this. */
    protected function verdict(PlatformFeeAccess $access): void
    {
        $setting = $access->setting();

        $this->info('Can a student pay online right now?');
        $this->line(str_repeat('-', 78));

        if (!$setting) {
            $this->warn('  No. Configure the fee at /admin/platform-fee/settings first.');
            $this->line('');

            return;
        }

        $blocker = $setting->onlinePaymentBlocker();

        if ($blocker === null) {
            $this->line('  Yes — the Pay-now button is on offer.');
            $this->line('');

            return;
        }

        $this->warn('  No: ' . $blocker);
        $this->line('      Students see the manual shortcode and receipt upload instead,');
        $this->line('      and an admin verifies each one by hand.');
        $this->line('');
    }

    protected function transactions(): void
    {
        $this->info('Payments');
        $this->line(str_repeat('-', 78));

        $byStatus = FapshiTransaction::select('status', DB::raw('count(*) as n'), DB::raw('sum(amount_received) as received'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        if ($byStatus->isEmpty()) {
            $this->line('  none yet.');
        } else {
            foreach ($byStatus as $row) {
                $this->line(sprintf('  %-12s %4d   received %s', $row->status, $row->n,
                    number_format((float) $row->received, 2)));
            }
        }

        $this->line('');
        $this->line(sprintf('  %-34s %d', 'platform fee payments, all', PlatformFeePayment::count()));
        $this->line(sprintf('  %-34s %d', 'of those settled online', PlatformFeePayment::where('paid_via', 'fapshi')->count()));
        $this->line(sprintf('  %-34s %d', 'of those awaiting a human', PlatformFeePayment::where('status', 'pending')->count()));

        $stale = FapshiTransaction::whereIn('status', FapshiTransaction::OPEN_STATUSES)
            ->where('created_at', '<=', now()->subHour())
            ->count();

        if ($stale > 0) {
            $this->line('');
            $this->warn(sprintf('  %d payment(s) have been in flight for over an hour.', $stale));
            $this->line('      Run: php artisan fapshi:reconcile');
        }

        $this->line('');
    }

    protected function attention(): void
    {
        $needing = FapshiTransaction::whereIn('status', FapshiTransaction::NEEDS_ATTENTION_STATUSES)
            ->orderBy('id')
            ->get();

        if ($needing->isEmpty()) {
            return;
        }

        $this->info('Money that arrived without granting access');
        $this->line(str_repeat('-', 78));
        $this->warn('  These cannot be fixed by software — somebody has to refund or chase them.');
        $this->line('');

        foreach ($needing as $transaction) {
            $this->line(sprintf('  %-14s %-11s %12s %-5s  %s',
                $transaction->external_id,
                $transaction->status,
                number_format((float) ($transaction->amount_received ?? 0), 2),
                $transaction->currency,
                $transaction->failure_reason));
        }

        $this->line('');
    }
}
