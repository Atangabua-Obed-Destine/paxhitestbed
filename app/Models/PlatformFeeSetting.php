<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PlatformFeeSetting extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'welcome_message',
        'fee_amount',
        'gateway_fee_percent',
        'gateway_fee_fixed',
        'is_enabled',
        'online_payment_enabled',
        'payment_instructions',
        'ussd_template',
        'merchant_name',
        'merchant_number',
        'currency',
        'status',
    ];

    /** The placeholders a school may use in the shortcode pattern. */
    public const AMOUNT_PLACEHOLDERS = ['{amount}', '{AMOUNT}', 'AMOUNT'];

    /**
     * The shortcode with the fee filled in — what the student should dial.
     *
     * The school stores a pattern once, e.g. *126*4*123456*{amount}#, because
     * the merchant number never changes and the amount does. Typing a code like
     * that by hand is where payments go astray, so the portal builds it.
     *
     * The amount goes in whole units: a USSD string carrying "2000.00" is
     * rejected by the network.
     */
    public function dialCode(): ?string
    {
        $template = trim((string) $this->ussd_template);

        if ($template === '') {
            return null;
        }

        $amount = (float) $this->fee_amount;
        $amount = floor($amount) == $amount
            ? (string) (int) $amount
            : rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

        return str_replace(self::AMOUNT_PLACEHOLDERS, $amount, $template);
    }

    /**
     * That code as something a phone will act on.
     *
     * The hash has to be percent-encoded: a bare # in a tel: URI is a fragment
     * marker, so the dialler receives the code with its terminator stripped and
     * the session never starts. The asterisks are left as they are — encoding
     * them stops some diallers recognising a USSD string at all.
     */
    public function dialLink(): ?string
    {
        $code = $this->dialCode();

        if ($code === null) {
            return null;
        }

        return 'tel:' . str_replace('#', '%23', $code);
    }

    /** Has the school set a shortcode students can tap? */
    public function hasDialCode(): bool
    {
        return $this->dialCode() !== null;
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'fee_amount' => 'decimal:2',
        'gateway_fee_percent' => 'decimal:2',
        'gateway_fee_fixed' => 'decimal:2',
        'is_enabled' => 'boolean',
        'online_payment_enabled' => 'boolean',
        'status' => 'boolean',
    ];

    /** The only currency the gateway accepts. */
    public const GATEWAY_CURRENCY = 'XAF';

    /**
     * The gateway's commission, which the school passes on to the student.
     *
     * Carried as a percentage rather than a flat figure because a flat figure
     * silently becomes wrong the moment the fee changes: 63 on 2,000 is the
     * right charge, and 63 on 20,000 is not. The fixed part is there for a
     * provider that charges one on top.
     *
     * Rounded up, and to a whole unit, because the gateway will not accept
     * fractions and rounding down would have the school absorb the remainder
     * on every single payment.
     */
    public function surchargeAmount(): int
    {
        $fee = (float) $this->fee_amount;
        $percent = (float) $this->gateway_fee_percent;
        $fixed = (float) $this->gateway_fee_fixed;

        if ($fee <= 0) {
            return 0;
        }

        return (int) ceil($fee * $percent / 100) + (int) ceil($fixed);
    }

    /**
     * What the student is actually asked for.
     *
     * One method, called by the student's screen, the settings screen and the
     * request sent to the gateway alike — so the figure a student is shown and
     * the figure they are charged cannot drift apart.
     */
    public function chargeAmount(): int
    {
        return (int) ceil((float) $this->fee_amount) + $this->surchargeAmount();
    }

    /**
     * Is the fee a whole number of currency units?
     *
     * The settings form allows 0.01 steps and the column holds two decimals,
     * but the gateway takes integers only. A fee of 2,000.50 has to be refused
     * rather than quietly charged as 2,000 or 2,001.
     */
    public function feeIsWholeUnits(): bool
    {
        $fee = (float) $this->fee_amount;

        return abs($fee - floor($fee)) < 0.00001;
    }

    /**
     * Why online payment is not on offer, or null when it is.
     *
     * Returns a reason rather than a boolean so every screen can say what is
     * missing instead of leaving an admin to guess why the button never
     * appeared. Three separate things have to be true, and they fail for
     * different reasons and are fixed in different places.
     */
    public function onlinePaymentBlocker(): ?string
    {
        if (!$this->is_enabled) {
            return 'The platform access fee itself is switched off.';
        }

        if (!$this->online_payment_enabled) {
            return 'Online payment is switched off, so students pay manually and an admin verifies each receipt.';
        }

        $client = app(\App\Services\Payment\FapshiClient::class);

        if (!$client->isEnabled()) {
            return 'The Fapshi gateway is disabled. Enable it on the Mobile Money configuration screen.';
        }

        if (!$client->isConfigured()) {
            return 'The Fapshi API user and key are not set. Add them on the Mobile Money configuration screen.';
        }

        // Refusing to offer the button at all, because the quiet failure here
        // is the expensive one: a live site holding sandbox keys would send
        // every student to a test checkout and collect nothing, while the
        // screens all reported that payment was working.
        if ($belongsTo = $client->credentialEnvironmentMismatch()) {
            return sprintf('The Fapshi credentials are a %s key while the gateway is set to %s. '
                . 'Paste the %s pair on the Mobile Money configuration screen.',
                $belongsTo, $client->environment(), $client->environment());
        }

        if (strtoupper((string) $this->currency) !== self::GATEWAY_CURRENCY) {
            return sprintf('The fee is configured in %s, and the gateway only accepts %s.',
                $this->currency, self::GATEWAY_CURRENCY);
        }

        if (!$this->feeIsWholeUnits()) {
            return sprintf('The fee (%s) is not a whole number, and the gateway only accepts whole amounts.',
                rtrim(rtrim(number_format((float) $this->fee_amount, 2, '.', ''), '0'), '.'));
        }

        $minimum = (int) config('fapshi.minimum_amount', 100);

        if ($this->chargeAmount() < $minimum) {
            return sprintf('The total charge (%s) is below the gateway minimum of %s %s.',
                number_format($this->chargeAmount()), number_format($minimum), self::GATEWAY_CURRENCY);
        }

        return null;
    }

    /** May a student be offered the Pay-now button right now? */
    public function onlinePaymentAvailable(): bool
    {
        return $this->onlinePaymentBlocker() === null;
    }
}
