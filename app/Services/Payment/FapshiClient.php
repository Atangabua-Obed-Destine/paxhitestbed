<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Fapshi collection API.
 *
 * https://docs.fapshi.com/en/api-reference
 *
 * This class talks HTTP and nothing else — no database, no business rules, no
 * decisions about money. PlatformFeeGatewayPayment owns all of that. The split
 * is the same one MtnMomoClient keeps, and it is what makes the rules testable
 * without a network.
 */
class FapshiClient
{
    protected array $cfg;

    public function __construct()
    {
        $this->cfg = config('fapshi', []);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->cfg['enabled'] ?? false);
    }

    public function environment(): string
    {
        return $this->cfg['environment'] ?? 'sandbox';
    }

    /** Are the credentials present? Reported, so a config screen can say so. */
    public function isConfigured(): bool
    {
        return filled($this->cfg['api_user'] ?? null) && filled($this->cfg['api_key'] ?? null);
    }

    public function hasWebhookSecret(): bool
    {
        return filled($this->cfg['webhook_secret'] ?? null);
    }

    /**
     * Does the stored key belong to the environment that is selected?
     *
     * Fapshi prefixes a sandbox key with FAK_TEST_ and a live key with FAK_
     * alone, so the two are told apart without asking anybody anything.
     *
     * This exists because the environment and the credentials are changed in
     * different places and it is easy to move one without the other. The API
     * key field is rendered blank on purpose — a live key is shown once and
     * cannot be read back, so blank has to mean "keep what is stored" — which
     * means switching to Live and saving keeps the sandbox key, and the only
     * symptom is the gateway refusing credentials that look perfectly good.
     *
     * The dangerous direction is the quiet one: a LIVE site running on test
     * keys would hand students a sandbox checkout and never take a franc.
     *
     * @return string|null  the environment the key appears to belong to, or
     *                      null when it matches the one selected
     */
    public function credentialEnvironmentMismatch(): ?string
    {
        $key = trim((string) ($this->cfg['api_key'] ?? ''));

        if ($key === '') {
            return null;
        }

        $keyLooksLike = str_starts_with($key, 'FAK_TEST_') ? 'sandbox' : 'live';

        return $keyLooksLike === $this->environment() ? null : $keyLooksLike;
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->cfg['base_url'] ?? 'https://sandbox.fapshi.com'), '/');
    }

    public function minimumAmount(): int
    {
        return (int) ($this->cfg['minimum_amount'] ?? 100);
    }

    public function linkLifetimeHours(): int
    {
        return (int) ($this->cfg['link_lifetime_hours'] ?? 24);
    }

    /**
     * Does a webhook request carry the secret configured in Fapshi's dashboard?
     *
     * hash_equals, not ==, so the comparison does not leak the secret one byte
     * at a time through its own timing. Returns false when no secret is set:
     * an unconfigured secret means unauthenticated callers cannot be told apart
     * from Fapshi, and the safe reading of that is "nobody is authentic".
     */
    public function webhookSecretMatches(?string $presented): bool
    {
        $expected = (string) ($this->cfg['webhook_secret'] ?? '');

        if ($expected === '' || $presented === null) {
            return false;
        }

        return hash_equals($expected, $presented);
    }

    /**
     * Generate a payment link.
     *
     * POST /initiate-pay → { message, link, transId, dateInitiated }
     *
     * amount must be a whole number of at least 100 XAF; userId and externalId
     * are limited to 1-100 characters of letters, digits, hyphen and underscore.
     * The caller is responsible for honouring that — this method does not
     * second-guess its input, because a silent correction here would mean the
     * figure charged differs from the figure shown to the student.
     */
    public function initiatePay(array $payload): Response
    {
        return $this->http()->post($this->baseUrl() . '/initiate-pay', $payload);
    }

    /**
     * The authoritative state of one payment.
     *
     * GET /payment-status/{transId} → status is one of CREATED, PENDING,
     * SUCCESSFUL, FAILED, EXPIRED, alongside amount, revenue, externalId,
     * financialTransId and dateConfirmed.
     *
     * Capped at 6 calls a minute per transId; a 429 comes back as an ordinary
     * response for the caller to handle rather than an exception, so a paced
     * reconciliation run can back off instead of dying.
     */
    public function paymentStatus(string $transId): Response
    {
        return $this->http()->get($this->baseUrl() . '/payment-status/' . urlencode($transId));
    }

    /**
     * The account's balance — and the only sound way to test credentials.
     *
     * GET /balance → { service, balance, currency }
     *
     * Read-only, no side effects, and it names the service the keys belong to,
     * which is worth more than a bare "ok" when somebody has two of them.
     */
    public function balance(): Response
    {
        return $this->http()->get($this->baseUrl() . '/balance');
    }

    /**
     * A cheap call that proves the credentials work, for the config screen's
     * Test button.
     *
     * This asked payment-status about a transaction that could not exist, which
     * seemed reasonable and was wrong: the two hosts refuse an unknown id
     * differently — sandbox answers 404, live answers 400 "Invalid request URL"
     * — so a perfectly good live key read as a failure. Both of those also
     * collide with the answer for a MALFORMED id, which arrives before
     * authentication is even reached.
     *
     * /balance has none of those problems: it is about the credentials
     * themselves, so there is nothing else for the answer to be about.
     *
     *   200                             the credentials were accepted
     *   400 "Invalid apiuser/apikey"    they were not — NOT a 403
     *   403                             documented for an IP that is not on
     *                                   the whitelist
     */
    public function probe(): Response
    {
        return $this->balance();
    }

    protected function http(): PendingRequest
    {
        $apiUser = $this->cfg['api_user'] ?? null;
        $apiKey  = $this->cfg['api_key'] ?? null;

        if (!filled($apiUser) || !filled($apiKey)) {
            // Loud, not silent: a half-configured gateway should fail where an
            // admin is looking at a config screen, not where a student is
            // trying to pay.
            throw new RuntimeException('Fapshi apiuser / apikey is not configured.');
        }

        return Http::timeout((int) ($this->cfg['http_timeout'] ?? 20))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'apiuser' => $apiUser,
                'apikey'  => $apiKey,
            ]);
    }
}
