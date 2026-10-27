<?php

namespace App\Support;

/**
 * The fees band's figures, from either controller's statistics.
 *
 * FeesStudentController computes the same quantities twice under different
 * names — the list calls them total_amount_due / total_paid, the report calls
 * them total_amount / total_collected — because the two methods were written
 * at different times. Renaming either set would reach into views and exports
 * that read them, so this maps both onto one shape and leaves the controllers
 * alone.
 *
 * Everything here is arithmetic on figures already computed. It runs no query
 * and knows nothing about Fee, so it cannot disagree with the rows the page
 * is showing.
 */
class FeesSummary
{
    /**
     * @param  array<string, mixed>|null  $stats  either controller's shape
     * @return array{
     *     has:bool, billed:float, collected:float, outstanding:float,
     *     overpaid:float, rate:float, counts:array<string, int>
     * }
     */
    public static function from(?array $stats): array
    {
        if (!$stats) {
            return self::empty();
        }

        $pick = function (array $keys) use ($stats) {
            foreach ($keys as $key) {
                if (isset($stats[$key])) {
                    return (float) $stats[$key];
                }
            }

            return 0.0;
        };

        $billed = $pick(['total_amount', 'total_amount_due']);
        $collected = $pick(['total_collected', 'total_paid']);
        $outstanding = $pick(['total_remaining']);

        return [
            'has' => true,
            'billed' => $billed,
            'collected' => $collected,
            'outstanding' => $outstanding,
            'overpaid' => $pick(['total_overpayment']),

            /*
             * Collected over billed, capped at 100.
             *
             * Capped because overpayment can push cash received above what was
             * billed, and a bar wider than its track reads as a rendering
             * fault rather than as good news. The excess has its own figure.
             */
            'rate' => $billed > 0 ? min(100.0, round($collected / $billed * 100, 1)) : 0.0,

            'counts' => [
                'total' => (int) ($stats['total_fees'] ?? 0),
                'paid' => (int) ($stats['fully_paid_count'] ?? 0),
                'partial' => (int) ($stats['partially_paid_count'] ?? 0),
                // The list counts unpaid directly; the report does not, so it
                // is what the others leave over.
                'unpaid' => (int) ($stats['unpaid_count'] ?? max(0,
                    (int) ($stats['total_fees'] ?? 0)
                    - (int) ($stats['fully_paid_count'] ?? 0)
                    - (int) ($stats['partially_paid_count'] ?? 0)
                    - (int) ($stats['cancelled_count'] ?? 0)
                    - (int) ($stats['overpaid_count'] ?? 0))),
                'cancelled' => (int) ($stats['cancelled_count'] ?? 0),
                'overpaid' => (int) ($stats['overpaid_count'] ?? 0),
            ],
        ];
    }

    /** Nothing has been searched for yet. */
    protected static function empty(): array
    {
        return [
            'has' => false,
            'billed' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0,
            'overpaid' => 0.0, 'rate' => 0.0,
            'counts' => ['total' => 0, 'paid' => 0, 'partial' => 0,
                         'unpaid' => 0, 'cancelled' => 0, 'overpaid' => 0],
        ];
    }
}
