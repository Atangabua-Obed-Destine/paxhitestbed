<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Services\DaybookService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Month-addressable entry points into the daybook and the budget sheet.
 *
 * WHY THESE EXIST. EdutrustPay holds a signed monthly summary of this
 * institution's figures — OHADA group totals, never transactions. When somebody
 * at the body wants the detail behind a month, the console does not fetch it:
 * it sends the person here, to this institution's own screens, where this
 * institution's own login governs what they may see. Nothing reaches into this
 * database from outside; the browser simply follows a link.
 *
 * WHY A REDIRECT RATHER THAN A LINK. Neither screen is addressable by month.
 * The daybook takes `budget_id` and `period_id` — database ids that mean
 * nothing to an outside system and change between installations. The budget
 * sheet takes a budget id, and that id IS the period: sheet 22 is October 2025
 * to August 2026. An external system cannot construct either URL from
 * "2026-08" without reading this database, which is precisely what it must not
 * do. So it asks for a month, and this resolves it.
 *
 * These are the only outward-facing additions in this codebase, and they add no
 * new visibility: both are guarded by the same permissions as the screens they
 * land on, so a link from the console is exactly as privileged as typing the
 * URL by hand.
 */
class MonthLinkController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:daybook-view', ['only' => ['daybook']]);
        $this->middleware('permission:budget-view', ['only' => ['budgetSheet']]);
    }

    /**
     * The daybook for a calendar month, e.g. /admin/daybook/month/2026-08.
     */
    public function daybook(Request $request, string $month, DaybookService $daybook)
    {
        $date = $this->parse($month);

        if (! $date) {
            return redirect()->route('admin.daybook.index')
                ->with('error', __('":month" is not a calendar month. Use YYYY-MM.', ['month' => $month]));
        }

        $budget = $this->budgetCovering($date);

        if (! $budget) {
            return redirect()->route('admin.daybook.index')
                ->with('error', __('No institutional budget covers :month, so the daybook has no period to open.', [
                    'month' => $date->format('F Y'),
                ]));
        }

        // The accounting period containing the month, not merely overlapping
        // it: a period spanning a quarter would otherwise match three months
        // and open on whichever happened to sort first.
        $period = $daybook->periodsFor($budget)
            ->first(fn (AccountingPeriod $p) => $date->between($p->start_date, $p->end_date));

        if (! $period) {
            return redirect()->route('admin.daybook.summary', ['budget_id' => $budget->id])
                ->with('error', __('No accounting period covers :month.', ['month' => $date->format('F Y')]));
        }

        return redirect()->route('admin.daybook.summary', [
            'budget_id' => $budget->id,
            'period_id' => $period->id,
        ]);
    }

    /**
     * The budget sheet whose year contains a calendar month.
     */
    public function budgetSheet(Request $request, string $month)
    {
        $date = $this->parse($month);

        if (! $date) {
            return redirect()->route('admin.budget-sheet.index')
                ->with('error', __('":month" is not a calendar month. Use YYYY-MM.', ['month' => $month]));
        }

        $budget = $this->budgetCovering($date);

        if (! $budget) {
            return redirect()->route('admin.budget-sheet.index')
                ->with('error', __('No budget sheet covers :month.', ['month' => $date->format('F Y')]));
        }

        return redirect()->route('admin.budget-sheet.show', $budget->id);
    }

    private function parse(string $month): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The institutional budget whose span contains this month.
     *
     * Budget years overlap here and the overlap is real, not an error: sheet 22
     * runs October 2025 to August 2026 (the school year) while sheet 64 runs
     * the 2026 calendar year, so August 2026 genuinely sits inside both. There
     * is no single correct answer, only a consistent one.
     *
     * So this deliberately matches DaybookController::context(), which defaults
     * to `orderByDesc('id')->first()`. A link arriving from the console must
     * land where this institution's own screen would have landed unaided —
     * otherwise the body and the bursar end up reading different sheets while
     * both believe they are looking at August, which is worse than either
     * choice on its own.
     *
     * If that default is ever wrong, it is wrong in one place for both, and
     * fixing it there fixes it here.
     */
    private function budgetCovering(Carbon $date): ?Budget
    {
        return Budget::where('is_institutional', true)
            ->whereDate('start_date', '<=', $date->copy()->endOfMonth())
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('id')
            ->first();
    }
}
