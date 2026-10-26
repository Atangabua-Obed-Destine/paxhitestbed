<?php

namespace App\Support;

use App\Models\FiscalYear;
use Carbon\Carbon;

/**
 * Which dates the income and expense lists open on, and the ranges offered
 * beside them.
 *
 * Both screens used to default to the last twelve months counted back from
 * today. That window belongs to no set of accounts: it straddled two
 * exercices, so a figure shown on the screen and the same figure in a report
 * were drawn from different periods — and it silently hid everything older,
 * which on this database was sixteen expenses the Bursar had no way of knowing
 * were missing.
 *
 * So the default is the exercice the books are actually keeping, and the
 * window being applied is stated on the page rather than left to be inferred
 * from two date inputs.
 *
 * An explicit start_date/end_date in the query string always wins, so a saved
 * link or the back button still shows what it showed before.
 */
class TransactionWindow
{
    /**
     * The dates a request resolves to, and how to describe them.
     *
     * $all is how "All time" says it meant no dates. Without it, a range
     * carrying neither bound is indistinguishable from a first visit, and the
     * default would quietly put the exercice back.
     *
     * @return array{start:?string, end:?string, label:string, ranges:array<int, array{label:string, start:?string, end:?string, active:bool}>}
     */
    public static function resolve($start, $end, bool $all = false): array
    {
        $ranges = self::ranges();

        if ($all) {
            $start = $end = null;
        }

        if (!$all && $start === null && $end === null) {
            $current = self::currentExercice();

            $start = $current ? self::day($current->start_date) : null;
            $end = $current ? self::day($current->end_date) : null;
        }

        foreach ($ranges as $i => $range) {
            $ranges[$i]['active'] = $range['start'] === $start && $range['end'] === $end;
        }

        // Only "All time" has no dates, and it is the last range, so an
        // explicit all=1 can only mean that one.
        if ($all) {
            foreach ($ranges as $i => $range) {
                $ranges[$i]['active'] = $range['start'] === null && $range['end'] === null;
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => self::label($start, $end, $ranges),
            'ranges' => $ranges,
        ];
    }

    /**
     * The exercice today falls in, or the active one.
     *
     * forDate() is asked first because it answers from the dates rather than
     * from a flag, and the flag can be left on a year that has ended. It
     * returns null when no exercice covers today, and then the active year is
     * better than nothing at all.
     */
    public static function currentExercice(): ?FiscalYear
    {
        return FiscalYear::forDate(Carbon::today()->toDateString())
            ?: FiscalYear::where('is_active', 1)->first();
    }

    /**
     * The quick ranges, in the order they are offered.
     *
     * This month first because it is what day-to-day entry wants, then the
     * exercice the reports are built on, then the one before it for a
     * comparison, then everything.
     *
     * @return array<int, array{label:string, start:?string, end:?string, active:bool}>
     */
    public static function ranges(): array
    {
        $out = [[
            'label' => __('acc_this_month'),
            'start' => Carbon::today()->startOfMonth()->toDateString(),
            'end' => Carbon::today()->endOfMonth()->toDateString(),
            'active' => false,
        ]];

        $current = self::currentExercice();

        if ($current) {
            $out[] = [
                'label' => $current->name,
                'start' => self::day($current->start_date),
                'end' => self::day($current->end_date),
                'active' => false,
            ];

            $previous = FiscalYear::where('end_date', '<', $current->start_date)
                ->orderByDesc('end_date')->first();

            if ($previous) {
                $out[] = [
                    'label' => $previous->name,
                    'start' => self::day($previous->start_date),
                    'end' => self::day($previous->end_date),
                    'active' => false,
                ];
            }
        }

        $out[] = [
            'label' => __('acc_all_time'),
            'start' => null,
            'end' => null,
            'active' => false,
        ];

        return $out;
    }

    /** How to say, in one phrase, what is on screen. */
    protected static function label(?string $start, ?string $end, array $ranges): string
    {
        foreach ($ranges as $range) {
            if ($range['active']) {
                return $range['label'];
            }
        }

        if ($start === null && $end === null) {
            return __('acc_all_time');
        }

        $fmt = fn (?string $d) => $d ? Carbon::parse($d)->format('j M Y') : '…';

        return $fmt($start) . ' – ' . $fmt($end);
    }

    /** Fiscal year dates are stored as datetimes; only the day matters here. */
    protected static function day($value): string
    {
        return Carbon::parse($value)->toDateString();
    }
}
