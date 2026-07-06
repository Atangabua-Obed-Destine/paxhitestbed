<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payment\MtnMomoClient;
use App\Services\Payment\OrangeMomoClient;
use App\Traits\EnvironmentVariable;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin UI for the Mobile Money gateways (MTN + Orange).
 *
 * Follows the same env-writing pattern as PaymentSettingController / the
 * EnvironmentVariable trait. All secrets live in .env; we never store them
 * in the database.
 */
class MobileMoneyConfigController extends Controller
{
    use EnvironmentVariable;

    protected $title, $route, $view, $access;

    public function __construct()
    {
        $this->title  = trans_choice('module_mobile_money', 1);
        $this->route  = 'admin.mobile-money-config';
        $this->view   = 'admin.mobile-money-config';
        $this->access = 'payment-gateway-manage';

        $this->middleware('permission:'.$this->access);
    }

    /** Show the config screen (MTN + Orange side by side). */
    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view']  = $this->view;
        $data['access'] = $this->access;

        $data['mtn'] = [
            'enabled'          => (bool) config('momo.providers.mtn.enabled'),
            'environment'      => config('momo.providers.mtn.environment'),
            'base_url'         => config('momo.providers.mtn.base_url'),
            'target_environment' => config('momo.providers.mtn.target_environment'),
            'subscription_key' => config('momo.providers.mtn.collections.subscription_key'),
            'api_user'         => config('momo.providers.mtn.collections.api_user'),
            'api_key'          => config('momo.providers.mtn.collections.api_key'),
            'callback_host'    => config('momo.providers.mtn.collections.callback_host'),
        ];

        $data['orange'] = [
            'enabled'       => (bool) config('momo.providers.orange.enabled'),
            'environment'   => config('momo.providers.orange.environment'),
            'base_url'      => config('momo.providers.orange.base_url'),
            'client_id'     => config('momo.providers.orange.client_id'),
            'client_secret' => config('momo.providers.orange.client_secret'),
            'merchant_key'  => config('momo.providers.orange.merchant_key'),
            'callback_host' => config('momo.providers.orange.callback_host'),
        ];

        $data['shared'] = [
            'currency'      => config('momo.currency'),
            'poll_timeout'  => config('momo.poll_timeout_seconds'),
            'poll_interval' => config('momo.poll_interval_seconds'),
            'http_timeout'  => config('momo.http_timeout'),
        ];

        return view($this->view.'.index', $data);
    }

    /** Persist the form values to .env. */
    public function update(Request $request)
    {
        $request->validate([
            'mtn_environment'       => 'nullable|in:sandbox,production',
            'mtn_base_url'          => 'nullable|url|max:255',
            'mtn_target_environment'=> 'nullable|in:sandbox,mtncameroon',
            'mtn_subscription_key'  => 'nullable|string|max:255',
            'mtn_api_user'          => 'nullable|string|max:255',
            'mtn_api_key'           => 'nullable|string|max:255',
            'mtn_callback_host'     => 'nullable|string|max:255',

            'orange_environment'   => 'nullable|in:sandbox,production',
            'orange_base_url'      => 'nullable|url|max:255',
            'orange_client_id'     => 'nullable|string|max:255',
            'orange_client_secret' => 'nullable|string|max:255',
            'orange_merchant_key'  => 'nullable|string|max:255',
            'orange_callback_host' => 'nullable|string|max:255',

            'momo_currency'      => 'nullable|string|size:3',
            'momo_poll_timeout'  => 'nullable|integer|min:10|max:600',
            'momo_poll_interval' => 'nullable|integer|min:1|max:30',
            'momo_http_timeout'  => 'nullable|integer|min:3|max:120',
        ]);

        // MTN
        $this->putEnv('MTN_MOMO_ENABLED',                       $request->boolean('mtn_enabled') ? 'true' : 'false', quote: false);
        $this->putEnv('MTN_MOMO_ENV',                           $request->input('mtn_environment', 'sandbox'));
        $this->putEnv('MTN_MOMO_BASE_URL',                      $request->input('mtn_base_url', 'https://sandbox.momodeveloper.mtn.com'));
        $this->putEnv('MTN_MOMO_TARGET_ENVIRONMENT',            $request->input('mtn_target_environment', 'sandbox'));
        $this->putEnv('MTN_MOMO_COLLECTIONS_SUBSCRIPTION_KEY',  (string) $request->input('mtn_subscription_key'));
        $this->putEnv('MTN_MOMO_COLLECTIONS_API_USER',          (string) $request->input('mtn_api_user'));
        $this->putEnv('MTN_MOMO_COLLECTIONS_API_KEY',           (string) $request->input('mtn_api_key'));
        $this->putEnv('MTN_MOMO_CALLBACK_HOST',                 (string) $request->input('mtn_callback_host'));

        // Orange
        $this->putEnv('ORANGE_MOMO_ENABLED',       $request->boolean('orange_enabled') ? 'true' : 'false', quote: false);
        $this->putEnv('ORANGE_MOMO_ENV',           $request->input('orange_environment', 'sandbox'));
        $this->putEnv('ORANGE_MOMO_BASE_URL',      $request->input('orange_base_url', 'https://api.orange.com'));
        $this->putEnv('ORANGE_MOMO_CLIENT_ID',     (string) $request->input('orange_client_id'));
        $this->putEnv('ORANGE_MOMO_CLIENT_SECRET', (string) $request->input('orange_client_secret'));
        $this->putEnv('ORANGE_MOMO_MERCHANT_KEY',  (string) $request->input('orange_merchant_key'));
        $this->putEnv('ORANGE_MOMO_CALLBACK_HOST', (string) $request->input('orange_callback_host'));

        // Shared
        $this->putEnv('MOMO_CURRENCY',      strtoupper($request->input('momo_currency', 'XAF')));
        $this->putEnv('MOMO_POLL_TIMEOUT',  (string) $request->input('momo_poll_timeout', 90), quote: false);
        $this->putEnv('MOMO_POLL_INTERVAL', (string) $request->input('momo_poll_interval', 3), quote: false);
        $this->putEnv('MOMO_HTTP_TIMEOUT',  (string) $request->input('momo_http_timeout', 15), quote: false);

        \Artisan::call('config:clear');
        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
        return redirect()->route($this->route.'.index');
    }

    /**
     * Probe MTN or Orange with the currently-saved credentials by requesting a token.
     * Returns JSON so the UI can badge Success/Failure without a full page reload.
     */
    public function testConnection(Request $request, string $provider): JsonResponse
    {
        try {
            if ($provider === 'mtn') {
                $client = app(MtnMomoClient::class);
                if (!$client->isEnabled()) {
                    return response()->json(['ok' => false, 'message' => 'MTN MoMo is disabled. Enable it and save first.'], 200);
                }
                $token = $client->token();
                return response()->json(['ok' => true, 'message' => 'Token obtained (len ' . strlen($token) . ').']);
            }
            if ($provider === 'orange') {
                $client = app(OrangeMomoClient::class);
                if (!$client->isEnabled()) {
                    return response()->json(['ok' => false, 'message' => 'Orange Money is disabled. Enable it and save first.'], 200);
                }
                $token = $client->token();
                return response()->json(['ok' => true, 'message' => 'Token obtained (len ' . strlen($token) . ').']);
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 200);
        }
        return response()->json(['ok' => false, 'message' => 'Unknown provider.'], 404);
    }

    /**
     * One-click MTN sandbox bootstrap — mirrors the artisan command
     * `momo:mtn-provision-sandbox` but writes the resulting API user + key
     * straight to .env so the admin doesn't need SSH.
     */
    public function provisionMtnSandbox(Request $request): JsonResponse
    {
        $host = $request->input('host') ?: 'webhook.site';
        $referenceId = (string) Str::uuid();

        try {
            $client = app(MtnMomoClient::class);
            $userResp = $client->provisionSandboxApiUser($referenceId, $host);
            if ($userResp->status() !== 201) {
                return response()->json(['ok' => false, 'message' => 'apiuser failed: HTTP ' . $userResp->status() . ' - ' . $userResp->body()], 200);
            }
            $keyResp = $client->provisionSandboxApiKey($referenceId);
            if (!$keyResp->successful()) {
                return response()->json(['ok' => false, 'message' => 'apikey failed: HTTP ' . $keyResp->status() . ' - ' . $keyResp->body()], 200);
            }
            $apiKey = $keyResp->json('apiKey');

            $this->putEnv('MTN_MOMO_COLLECTIONS_API_USER', $referenceId);
            $this->putEnv('MTN_MOMO_COLLECTIONS_API_KEY', $apiKey);
            \Artisan::call('config:clear');

            return response()->json([
                'ok' => true,
                'message' => 'Sandbox credentials provisioned and saved to .env.',
                'api_user' => $referenceId,
                'api_key' => $apiKey,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Env writer that matches PaymentSettingController's convention:
     * values are quoted "..." unless `quote: false` (used for booleans/ints).
     */
    protected function putEnv(string $key, string $value, bool $quote = true): void
    {
        // Guard against embedded quotes / newlines corrupting .env.
        $safe = str_replace(['"', "\n", "\r"], ['\"', '', ''], $value);
        $this->updateEnvVariable($key, $quote ? '"' . $safe . '"' : $safe);
    }
}
