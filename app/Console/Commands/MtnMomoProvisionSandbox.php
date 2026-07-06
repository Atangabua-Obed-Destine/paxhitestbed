<?php

namespace App\Console\Commands;

use App\Services\Payment\MtnMomoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-shot bootstrap for the MTN MoMo sandbox: generates an API User + API Key
 * against your Collections subscription. Output the values to paste into .env.
 *
 * Usage:
 *   php artisan momo:mtn-provision-sandbox --host=webhook.site  (host required by MTN)
 */
class MtnMomoProvisionSandbox extends Command
{
    protected $signature = 'momo:mtn-provision-sandbox {--host= : Provider callback host (e.g. webhook.site or your ngrok domain, no scheme)}';
    protected $description = 'Provision an MTN MoMo sandbox API user + API key from your Collections subscription key.';

    public function handle(MtnMomoClient $client): int
    {
        $host = $this->option('host') ?: 'webhook.site';
        $referenceId = (string) Str::uuid();

        $this->info('Provisioning API User with X-Reference-Id ' . $referenceId . ' ...');
        $userResp = $client->provisionSandboxApiUser($referenceId, $host);
        if ($userResp->status() !== 201) {
            $this->error('apiuser failed: HTTP ' . $userResp->status() . ' - ' . $userResp->body());
            return self::FAILURE;
        }

        $this->info('Requesting API Key ...');
        $keyResp = $client->provisionSandboxApiKey($referenceId);
        if (!$keyResp->successful()) {
            $this->error('apikey failed: HTTP ' . $keyResp->status() . ' - ' . $keyResp->body());
            return self::FAILURE;
        }

        $apiKey = $keyResp->json('apiKey');
        $this->newLine();
        $this->line('=== Add these to your .env ===');
        $this->line('MTN_MOMO_COLLECTIONS_API_USER=' . $referenceId);
        $this->line('MTN_MOMO_COLLECTIONS_API_KEY=' . $apiKey);
        $this->line('MTN_MOMO_ENABLED=true');
        $this->newLine();
        return self::SUCCESS;
    }
}
