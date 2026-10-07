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
        'is_enabled',
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
        'is_enabled' => 'boolean',
        'status' => 'boolean',
    ];
}
