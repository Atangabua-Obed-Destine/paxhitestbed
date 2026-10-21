<?php

namespace App\Support;

use App\Models\InstallmentPaymentReceipt;
use App\Models\MultiPayment;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\Cache;

/**
 * How many payment receipts are waiting to be verified.
 *
 * A receipt a student uploads sits in a queue that nobody is told about: the
 * only way to learn of it is to open the verification screen and look. On
 * this installation that produced a receipt pending since 22 September,
 * unseen for the better part of a month, while the student waited.
 *
 * This feeds a badge on the menu, so the number is in front of whoever can
 * act on it on every page they visit, and disappears by itself when the
 * queue is empty.
 *
 * Counted across all three kinds of receipt, exactly as the verification
 * screen counts them, so the badge and the page can never disagree.
 */
class PendingVerifications
{
    protected const CACHE_KEY = 'pending-payment-verifications';

    /**
     * Short enough that the number is never visibly stale, long enough that
     * three COUNT queries do not run on every page load of every admin.
     * Approving or rejecting clears it outright, so the common case is exact.
     */
    protected const TTL_SECONDS = 120;

    /** @return array{total:int, fee:int, installment:int, multi:int, oldest:?string} */
    public static function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, function () {
            $fee = PaymentReceipt::where('verification_status', 'pending')->count();
            $installment = InstallmentPaymentReceipt::where('status', 'pending')->count();
            $multi = MultiPayment::where('status', 'pending')->count();

            $oldest = collect([
                PaymentReceipt::where('verification_status', 'pending')->min('created_at'),
                InstallmentPaymentReceipt::where('status', 'pending')->min('created_at'),
                MultiPayment::where('status', 'pending')->min('created_at'),
            ])->filter()->min();

            return [
                'total' => $fee + $installment + $multi,
                'fee' => $fee,
                'installment' => $installment,
                'multi' => $multi,
                'oldest' => $oldest,
            ];
        });
    }

    public static function count(): int
    {
        return (int) (self::summary()['total'] ?? 0);
    }

    /** Whole days the oldest one has been waiting, or null if none are. */
    public static function oldestWaitingDays(): ?int
    {
        $oldest = self::summary()['oldest'] ?? null;

        if (!$oldest) {
            return null;
        }

        try {
            return (int) \Carbon\Carbon::parse($oldest)->startOfDay()->diffInDays(now()->startOfDay());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A sentence for the menu's tooltip.
     *
     * The age matters as much as the count: one receipt waiting an hour is
     * routine, one waiting three weeks is a student wondering where their
     * money went.
     */
    public static function describe(): string
    {
        $summary = self::summary();

        if ($summary['total'] === 0) {
            return __('No payments are waiting to be verified.');
        }

        $line = trans_choice(
            '{1}:count payment receipt is waiting to be verified|[2,*]:count payment receipts are waiting to be verified',
            $summary['total'],
            ['count' => $summary['total']]
        );

        $days = self::oldestWaitingDays();

        if ($days !== null && $days >= 1) {
            $line .= '. ' . trans_choice(
                '{1}The oldest has been waiting since yesterday|[2,*]The oldest has been waiting :days days',
                $days,
                ['days' => $days]
            ) . '.';
        }

        return $line;
    }

    /**
     * Drop the cached figure.
     *
     * Called the moment a receipt is approved or rejected, so the badge
     * answers to what the person just did rather than to a timer.
     */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
