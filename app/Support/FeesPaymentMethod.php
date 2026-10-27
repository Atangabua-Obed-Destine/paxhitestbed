<?php

namespace App\Support;

/**
 * How a fee was paid.
 *
 * Fees number their payment methods from one to EIGHT. The report screen's
 * if-chain carried the stock template's list instead, so above five it named
 * payment gateways this institution does not use:
 *
 *     6 →  "PayPal"      1 row
 *     7 →  "Stripe"      none yet
 *     8 →  "RazorPay"   26 rows
 *     null → blank      42 rows
 *
 * Those 27 rows arrive from student-uploaded manual receipts — exactly the
 * ones a Bursar is most likely to be checking — and the report was telling
 * them 26 cash-office receipts came in through RazorPay. Being confidently
 * wrong is worse than the blank cell the null rows showed.
 *
 * The eight values are not invented here. They are the map
 * \App\Models\PaymentReceipt::getPaymentMethodNameAttribute() has always
 * carried and which the report never used.
 *
 * ---------------------------------------------------------------------------
 * This is deliberately SEPARATE from \App\Support\PaymentMethod.
 *
 * That class serves income and expense, where 2 means Bank Transfer. Here 2
 * means whatever the fees forms have always written, which is a different
 * thing. The two modules were numbered independently and merging them would
 * put the wrong words against real money. Neither should learn about the
 * other.
 * ---------------------------------------------------------------------------
 *
 * One fault is knowingly left in place. The lang file was edited at some point
 * to repoint three keys — `payment_method_card` now reads "Cash",
 * `payment_method_cheque` reads "MTN Mobile Money", `payment_method_e_wallet`
 * reads "Other" — which makes three pairs collide:
 *
 *     1 (63 rows) and 2 (136 rows)  both read "Cash"
 *     3 (44 rows) and 6  (1 row)    both read "MTN Mobile Money"
 *     5  (8 rows) and 8 (26 rows)   both read "Other"
 *
 * Only the institution knows whether that repoint was deliberate, so this
 * class reproduces it rather than guessing. Settling it means deciding what 1
 * and 2 were entered under, and that is a question about their records, not
 * about this code.
 */
class FeesPaymentMethod
{
    /**
     * Stored value => lang key, as PaymentReceipt defines them.
     *
     * @var array<int, string>
     */
    private const KEYS = [
        1 => 'payment_method_card',
        2 => 'payment_method_cash',
        3 => 'payment_method_cheque',
        4 => 'payment_method_bank',
        5 => 'payment_method_e_wallet',
        6 => 'payment_method_mtn_momo',
        7 => 'payment_method_orange_money',
        8 => 'payment_method_other',
    ];

    /**
     * What one stored value is called.
     *
     * Null reads "Not recorded" rather than as an empty cell: a fee assigned
     * but never paid legitimately has no method, and a blank looks like a
     * rendering fault instead of a fact. Anything outside the eight reads as a
     * dash, which is the same distinction.
     */
    public static function label($value): string
    {
        if ($value === null || $value === '') {
            return __('acc_not_recorded');
        }

        $key = self::KEYS[(int) $value] ?? null;

        return $key ? __($key) : '—';
    }

    /**
     * Every method, as stored value => translated name.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::KEYS as $value => $key) {
            $out[$value] = __($key);
        }

        return $out;
    }

    /** The stored values, for a validation rule or a test. */
    public static function values(): array
    {
        return array_keys(self::KEYS);
    }
}
