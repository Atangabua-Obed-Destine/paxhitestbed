<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin wrapper around the MTN MoMo Collections API.
 *
 * https://momodeveloper.mtn.com/api-documentation/api-description/
 *
 * This class is stateless from a business standpoint — it just talks HTTP.
 * The controller layer is responsible for persisting MomoTransaction rows and
 * updating fees / receipts once a call returns.
 */
class MtnMomoClient
{
    protected array $cfg;

    public function __construct()
    {
        $this->cfg = config('momo.providers.mtn');
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->cfg['enabled'] ?? false);
    }

    public function environment(): string
    {
        return $this->cfg['environment'] ?? 'sandbox';
    }

    public function targetEnvironment(): string
    {
        return $this->cfg['target_environment'] ?? 'sandbox';
    }

    protected function baseUrl(): string
    {
        return rtrim($this->cfg['base_url'], '/');
    }

    protected function subscriptionKey(): string
    {
        $key = $this->cfg['collections']['subscription_key'] ?? null;
        if (!$key) {
            throw new RuntimeException('MTN MoMo collections subscription key is not configured.');
        }
        return $key;
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(config('momo.http_timeout', 15))
            ->acceptJson();
    }

    /**
     * Obtain a short-lived OAuth token for the Collections product.
     * Cached in-memory for the lifetime of this instance.
     */
    protected ?string $cachedToken = null;
    protected ?int $tokenExpiresAt = null;

    public function token(): string
    {
        if ($this->cachedToken && $this->tokenExpiresAt && $this->tokenExpiresAt > time() + 30) {
            return $this->cachedToken;
        }

        $apiUser = $this->cfg['collections']['api_user'] ?? null;
        $apiKey  = $this->cfg['collections']['api_key'] ?? null;
        if (!$apiUser || !$apiKey) {
            throw new RuntimeException('MTN MoMo collections API user / key is not configured.');
        }

        $response = $this->http()
            ->withBasicAuth($apiUser, $apiKey)
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey(),
            ])
            ->post($this->baseUrl() . '/collection/token/');

        if (!$response->successful()) {
            throw new RuntimeException('MTN MoMo token request failed: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        $this->cachedToken = $data['access_token'] ?? null;
        $this->tokenExpiresAt = time() + (int) ($data['expires_in'] ?? 3600);

        if (!$this->cachedToken) {
            throw new RuntimeException('MTN MoMo token response missing access_token.');
        }

        return $this->cachedToken;
    }

    /**
     * Initiate a Request-to-Pay against the payer's MSISDN.
     *
     * @param string $referenceId  UUIDv4 you generate and store as the tx handle.
     * @param string $externalId   Your own reference (e.g. "FEE-123-APP-456").
     * @param string $msisdn       MSISDN in international format WITHOUT the leading '+' (e.g. "237670000000").
     * @param string|float $amount Amount in the configured currency.
     * @param string $currency     Currency code (XAF in Cameroon; EUR in sandbox).
     * @param string $payerMessage Shown to the payer on their phone.
     * @param string $payeeNote    Free-text note stored with the tx.
     *
     * Returns the raw Response for logging. A 202 indicates the request was queued.
     */
    public function requestToPay(
        string $referenceId,
        string $externalId,
        string $msisdn,
        string|float $amount,
        string $currency,
        string $payerMessage,
        string $payeeNote
    ): Response {
        $body = [
            'amount'       => (string) $amount,
            'currency'     => $currency,
            'externalId'   => $externalId,
            'payer'        => [
                'partyIdType' => 'MSISDN',
                'partyId'     => $msisdn,
            ],
            'payerMessage' => Str::limit($payerMessage, 160, ''),
            'payeeNote'    => Str::limit($payeeNote, 160, ''),
        ];

        $headers = [
            'Authorization'             => 'Bearer ' . $this->token(),
            'X-Reference-Id'            => $referenceId,
            'X-Target-Environment'      => $this->targetEnvironment(),
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey(),
            'Content-Type'              => 'application/json',
        ];

        if (!empty($this->cfg['collections']['callback_host'])) {
            $headers['X-Callback-Url'] = rtrim($this->cfg['collections']['callback_host'], '/')
                . '/payment/momo/mtn/webhook';
        }

        $response = $this->http()->withHeaders($headers)->post(
            $this->baseUrl() . '/collection/v1_0/requesttopay',
            $body
        );

        if ($response->status() !== 202 && !$response->successful()) {
            Log::warning('MTN MoMo requestToPay non-202', [
                'ref' => $referenceId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    /**
     * Poll the current status of a request-to-pay.
     * Returns decoded JSON with keys: amount, currency, financialTransactionId,
     * externalId, payer, status (PENDING|SUCCESSFUL|FAILED), reason.
     */
    public function status(string $referenceId): array
    {
        $response = $this->http()->withHeaders([
            'Authorization'             => 'Bearer ' . $this->token(),
            'X-Target-Environment'      => $this->targetEnvironment(),
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey(),
        ])->get($this->baseUrl() . '/collection/v1_0/requesttopay/' . $referenceId);

        if (!$response->successful()) {
            throw new RuntimeException('MTN MoMo status request failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /* -----------------------------------------------------------------------
     |  Sandbox bootstrap helpers — used by an artisan command to provision
     |  the API User + API Key against a fresh subscription. Not called at
     |  runtime for normal payments.
     |----------------------------------------------------------------------- */

    public function provisionSandboxApiUser(string $referenceId, string $callbackHost): Response
    {
        return $this->http()->withHeaders([
            'X-Reference-Id'            => $referenceId,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey(),
            'Content-Type'              => 'application/json',
        ])->post($this->baseUrl() . '/v1_0/apiuser', [
            'providerCallbackHost' => $callbackHost,
        ]);
    }

    public function provisionSandboxApiKey(string $referenceId): Response
    {
        return $this->http()->withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey(),
        ])->post($this->baseUrl() . '/v1_0/apiuser/' . $referenceId . '/apikey');
    }
}
