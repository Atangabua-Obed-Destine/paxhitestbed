<?php

namespace App\Services\Chat;

/**
 * A provider could not answer.
 *
 * Carries whether the failure is worth trying elsewhere. A rate limit or an
 * overloaded model is transient: the next provider in the chain may well
 * answer. A rejected key, an unknown model or a malformed request is
 * permanent — trying the same thing again, here or anywhere, only makes the
 * person wait longer for the same failure.
 */
class ChatProviderException extends \RuntimeException
{
    protected string $provider;
    protected bool $transient;
    protected ?int $status;

    public function __construct(string $provider, string $message, bool $transient, ?int $status = null)
    {
        parent::__construct($message);

        $this->provider = $provider;
        $this->transient = $transient;
        $this->status = $status;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    /** Should the chain move on to the next provider? */
    public function isTransient(): bool
    {
        return $this->transient;
    }

    public function status(): ?int
    {
        return $this->status;
    }

    /** HTTP states that another provider might survive. */
    public static function transientStatus(int $status): bool
    {
        return in_array($status, [408, 409, 425, 429, 500, 502, 503, 504, 529], true);
    }
}
