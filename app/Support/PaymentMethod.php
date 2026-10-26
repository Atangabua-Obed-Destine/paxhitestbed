<?php

namespace App\Support;

/**
 * How money moved, for an income or an expense.
 *
 * There used to be no single answer to that. The create forms stored one
 * numbering, the index and the show modal read a different and older one, and
 * the income edit form read the old one while the expense edit form read the
 * new — five views, three vocabularies, all writing to the same column. The
 * mismatch had been noticed once and papered over in the LANG FILE, by
 * repointing `payment_method_card` at "Cash" and `payment_method_cheque` at
 * "MTN Mobile Money" so that three of the five values happened to line up
 * again. Two did not: a Bank Transfer displayed as "Cash", and the income edit
 * dropdown offered "Cash" twice while Orange Money could not be chosen at all.
 *
 * So the vocabulary lives here now, and every form, list and modal reads it.
 *
 * The numbering is the one the create forms have always stored, because that
 * is what every existing row was entered under. Nothing is migrated: the two
 * Bank Transfer expenses stop being CALLED Cash, which corrects the label and
 * not the data.
 *
 * Scope is income and expense. Fees, stock, payroll and payment accounts store
 * their own numbering against the same old lang keys, so this is deliberately
 * not imposed on them — that needs each module's stored data read first. The
 * three repointed keys are left exactly as they are for the same reason: those
 * screens still depend on them.
 */
class PaymentMethod
{
    public const CASH = 1;
    public const BANK = 2;
    public const MTN_MOMO = 3;
    public const ORANGE_MONEY = 4;
    public const OTHER = 5;

    /**
     * Lang keys, in the order a form should offer them.
     *
     * Cash first because it is most of what a Bursar records, then the two
     * that clear through a bank or a phone, then the escape hatch.
     *
     * These are new keys rather than the old `payment_method_card` family,
     * which other modules still read and which no longer say what they are
     * named.
     *
     * @var array<int, string>
     */
    private const KEYS = [
        self::CASH => 'pm_cash',
        self::BANK => 'pm_bank_transfer',
        self::MTN_MOMO => 'pm_mtn_momo',
        self::ORANGE_MONEY => 'pm_orange_money',
        self::OTHER => 'pm_other',
    ];

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

    /**
     * What one stored value is called.
     *
     * An unrecognised value returns a dash rather than an empty cell, so a row
     * saved before this existed, or by something that did not go through a
     * form, reads as "not recorded" instead of looking like a rendering fault.
     */
    public static function label($value): string
    {
        $key = self::KEYS[(int) $value] ?? null;

        return $key ? __($key) : '—';
    }

    /** The stored values, for a validation rule or a test. */
    public static function values(): array
    {
        return array_keys(self::KEYS);
    }

    /**
     * The validation rule the controllers apply.
     *
     * Nullable because rows exist with no method recorded, and refusing to
     * save one of those on an unrelated edit would trap the record.
     */
    public static function rule(): string
    {
        return 'nullable|in:' . implode(',', self::values());
    }
}
