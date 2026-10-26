<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Closure;

/**
 * What dates a budget may cover, and whether they line up with the accounts.
 *
 * The budget module works on a different principle from the ledger: a budget is
 * a free start and end date, and BudgetActualsService reports whatever falls
 * between them. It never consults a fiscal year. That is a reasonable design —
 * a school plans by academic year — but it has two consequences nothing was
 * checking.
 *
 * The first is overlap. Two institutional sheets covering the same day both
 * claim the same income and the same expenditure, and the same franc is
 * reported as spent against two budgets.
 *
 * The second is alignment, and it is the one that was actually biting. Budget
 * vs actual asks for a FISCAL YEAR and then reports over the matched BUDGET's
 * dates. With an eighteen-month budget straddling two exercices, asking for a
 * three-month first exercice returned eighteen months of actuals — measured on
 * this database at 116,807,830 against the 31,884,092 the exercice actually
 * contains.
 *
 * Overlap is refused outright, because it has no legitimate reading. Alignment
 * is reported as a warning rather than refused: drafting next year's budget
 * before its fiscal year exists is ordinary, and a school that deliberately
 * budgets by academic year is making a choice, not a mistake. It should simply
 * be told what that choice costs it.
 */
class BudgetPeriod
{
    /**
     * No two institutional sheets may cover the same day.
     *
     * Departmental and project budgets are exempt: they are delegated out of an
     * annual sheet and are MEANT to sit inside its period, alongside each
     * other.
     *
     * @param  int|null $ignoreId  the budget being edited, which may overlap itself
     */
    public static function noOverlapRule(?string $startDate, ?int $ignoreId = null): Closure
    {
        return function ($attribute, $value, $fail) use ($startDate, $ignoreId) {
            if (!$startDate || !$value) {
                return;
            }

            $clash = Budget::where('is_institutional', true)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->whereDate('start_date', '<=', $value)
                ->whereDate('end_date', '>=', $startDate)
                ->first();

            if ($clash) {
                $fail(sprintf(__('These dates overlap the sheet "%s" (%s to %s). Two sheets covering the'
                    . ' same day would each claim the same income and the same spending.'),
                    $clash->title,
                    $clash->start_date->format('j M Y'),
                    $clash->end_date->format('j M Y')));
            }
        };
    }

    /**
     * A delegated budget belongs inside the sheet it was delegated from.
     *
     * A departmental budget running past its parent spends money the annual
     * sheet never provided for, and the parent's allocated total stops meaning
     * anything.
     */
    public static function withinParentRule(?string $startDate, $parentId): Closure
    {
        return function ($attribute, $value, $fail) use ($startDate, $parentId) {
            if (!$parentId || !$startDate || !$value) {
                return;
            }

            $parent = Budget::find($parentId);

            if (!$parent || !$parent->start_date || !$parent->end_date) {
                return;
            }

            if (Carbon::parse($startDate)->lt($parent->start_date) || Carbon::parse($value)->gt($parent->end_date)) {
                $fail(sprintf(__('These dates fall outside "%s" (%s to %s), which this budget is'
                    . ' delegated from.'),
                    $parent->title,
                    $parent->start_date->format('j M Y'),
                    $parent->end_date->format('j M Y')));
            }
        };
    }

    /**
     * How this period sits against the exercices — said plainly, not enforced.
     *
     * @return array<int,string>  warnings, empty when the period matches one exercice exactly
     */
    public static function alignmentWarnings(?string $startDate, ?string $endDate): array
    {
        if (!$startDate || !$endDate) {
            return [];
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $startYear = FiscalYear::forDate($start);
        $endYear = FiscalYear::forDate($end);

        $warnings = [];

        if (!$startYear && !$endYear) {
            return [__('No fiscal year covers this period at all, so budget against actual cannot be'
                . ' compared with the statutory accounts until one is created.')];
        }

        if (!$startYear) {
            $warnings[] = sprintf(__('It begins on %s, before any fiscal year starts.'),
                $start->format('j M Y'));
        }

        if (!$endYear) {
            $warnings[] = sprintf(__('It ends on %s, after the last fiscal year finishes.'),
                $end->format('j M Y'));
        }

        /*
         * How many exercices does this period touch?
         *
         * Counted by overlap rather than by resolving the two endpoints. The
         * first version asked whether the start and end fell in DIFFERENT
         * fiscal years, which quietly missed the case that prompted all of
         * this: the eighteen-month sheet starts on 1 March 2025, before any
         * exercice exists, so its start resolved to nothing and the spanning
         * test never ran — on the very budget that spans two of them.
         */
        $spanned = FiscalYear::whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->pluck('name');

        if ($spanned->count() > 1) {
            $warnings[] = sprintf(__('It spans more than one exercice (%s). Budget against actual reports'
                . ' over the budget\'s own dates, so asking for either exercice will return the whole'
                . ' period rather than that year alone.'), $spanned->implode(', '));
        }

        if ($startYear && $endYear && $startYear->id === $endYear->id) {
            $exact = $start->isSameDay($startYear->start_date) && $end->isSameDay($startYear->end_date);

            if (!$exact) {
                $warnings[] = sprintf(__('It sits inside %s (%s to %s) without covering all of it, so the'
                    . ' sheet and that exercice\'s accounts will not report the same days.'),
                    $startYear->name,
                    $startYear->start_date->format('j M Y'),
                    $startYear->end_date->format('j M Y'));
            }
        }

        return $warnings;
    }

    /**
     * The fiscal_year label, derived rather than typed.
     *
     * The column is a VARCHAR(10) with no relationship to anything, and it was
     * being set from the start date's calendar year alone — so a sheet running
     * September 2026 to August 2027 was labelled "2026", which is true of its
     * first four months and of nothing else. Deriving it at least keeps the
     * label and the dates telling the same story.
     */
    public static function labelFor(?string $startDate, ?string $endDate): string
    {
        if (!$startDate) {
            return (string) date('Y');
        }

        $start = Carbon::parse($startDate);

        if (!$endDate) {
            return (string) $start->year;
        }

        $end = Carbon::parse($endDate);

        if ($start->year === $end->year) {
            return (string) $start->year;
        }

        return $start->year . '-' . substr((string) $end->year, -2);
    }
}
