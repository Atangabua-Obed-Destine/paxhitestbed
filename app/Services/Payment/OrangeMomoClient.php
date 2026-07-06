<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Orange Money (Cameroon) — Web Payment API client.
 *
 * https://developer.orange.com/apis/om-webpay-cm
 *
 * Unlike MTN's USSD push, Orange Web Payment is redirect-based: we initialize
 * a payment, receive a payment_url, and redirect the applicant there. Orange
 * then POSTs a notification to notif_url on completion, and we can also poll
 * transactionstatus for the final state.
 */
class OrangeMomoClient
{
    protected array $cfg;

    public function __construct()
    {
        $this->cfg = config('momo.providers.orange');
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->cfg['enabled'] ?? false);
    }

    public function environment(): string
    {
        return $this->cfg['environment'] ?? 'sandbox';
    }

    protected function baseUrl(): string
    {
        return rtrim($this->cfg['base_url'], '/');
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(config('momo.http_timeout', 15))->acceptJson();
    }

    protected ?string $cachedToken = null;
    protected ?int $tokenExpiresAt = null;

    public function token(): string
    {
        if ($this->cachedToken && $this->tokenExpiresAt && $this->tokenExpiresAt > time() + 30) {
            return $this->cachedToken;
        }

        $clientId = $this->cfg['client_id'] ?? null;
        $clientSecret = $this->cfg['client_secret'] ?? null;
        if (!$clientId || !$clientSecret) {
            throw new RuntimeException('Orange Money client_id / client_secret is not configured.');
        }

        $response = $this->http()
            ->withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->baseUrl() . '/oauth/v3/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Orange Money token request failed: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        $this->cachedToken = $data['access_token'] ?? null;
        $this->tokenExpiresAt = time() + (int) ($data['expires_in'] ?? 3600);

        if (!$this->cachedToken) {
            throw new RuntimeException('Orange Money token response missing access_token.');
        }
        return $this->cachedToken;
    }

    /**
     * Initialize a web payment. Returns the raw response — controllers should
     * persist pay_token, payment_url, notif_token from the JSON body.
     */
    public function initWebPayment(
        string $orderId,
        string|float $amount,
        string $currency,
        string $returnUrl,
        string $cancelUrl,
        string $notifUrl,
        string $reference,
        string $lang = 'fr'
    ): Response {
        $merchantKey = $this->cfg['merchant_key'] ?? null;
        if (!$merchantKey) {
            throw new RuntimeException('Orange Money merchant_key is not configured.');
        }

        return $this->http()
            ->withToken($this->token())
            ->post($this->baseUrl() . '/orange-money-webpay/cm/v1/webpayment', [
                'merchant_key' => $merchantKey,
                'currency'     => $currency,
                'order_id'     => $orderId,
                'amount'       => (string) $amount,
                'return_url'   => $returnUrl,
                'cancel_url'   => $cancelUrl,
                'notif_url'    => $notifUrl,
                'lang'         => $lang,
                'reference'    => $reference,
            ]);
    }

    /**
     * Query the terminal status of a web payment. Returns JSON with keys
     * status (SUCCESS|FAILED|PENDING|INITIATED|EXPIRED), txnid, etc.
     */
    public function transactionStatus(string $orderId, string|float $amount, string $payToken): array
    {
        $response = $this->http()
            ->withToken($this->token())
            ->get($this->baseUrl() . '/orange-money-webpay/cm/v1/transactionstatus', [
                'order_id'  => $orderId,
                'amount'    => (string) $amount,
                'pay_token' => $payToken,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Orange Money status request failed: ' . $response->status() . ' ' . $response->body());
        }
        return $response->json() ?? [];
    }
}
