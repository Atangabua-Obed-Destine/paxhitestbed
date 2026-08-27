<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\Exports\BudgetSheetExport;
use App\Services\BudgetActualsService;
use App\Services\BudgetReconciliationService;
use App\Services\LetterheadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The Income & Expenditure sheet: budget figures beside what actually happened.
 *
 * One screen rather than two. The paper form is a single sheet, and splitting
 * entry from reporting would mean maintaining the same 78 lines in two places
 * and reconciling them by eye.
 */
class BudgetSheetController extends Controller
{
    protected BudgetActualsService $actuals;
    protected BudgetReconciliationService $reconciliation;
    protected LetterheadService $letterhead;

    public function __construct(
        BudgetActualsService $actuals,
        BudgetReconciliationService $reconciliation,
        LetterheadService $letterhead
    ) {
        $this->actuals = $actuals;
        $this->reconciliation = $reconciliation;
        $this->letterhead = $letterhead;

        $this->middleware('permission:budget-view', ['only' => ['index', 'show', 'exportPdf', 'exportExcel']]);
        $this->middleware('permission:budget-edit', ['only' => ['store', 'saveFigures', 'updatePeriod', 'submit']]);
        $this->middleware('permission:budget-approve', ['only' => ['approve']]);
        $this->middleware('permission:budget-activate', ['only' => ['activate']]);
        $this->middleware('permission:budget-close', ['only' => ['close']]);
        $this->middleware('permission:budget-delete', ['only' => ['destroy']]);
    }

    /**
     * The steps a sheet moves through, and what may follow each one.
     *
     * Held here rather than scattered through the actions so an invalid jump —
     * activating something nobody approved — is impossible by construction.
     */
    protected const TRANSITIONS = [
        'draft' => 'pending_approval',
        'pending_approval' => 'approved',
        'approved' => 'active',
        'active' => 'closed',
    ];

    /** Figures may only be edited while the sheet is still a draft. */
    public static function isEditable(Budget $budget): bool
    {
        return $budget->status === 'draft';
    }

    public function submit($id)
    {
        return $this->advance($id, 'draft', __('Sheet submitted for approval. The figures are now locked.'));
    }

    public function approve($id)
    {
        return $this->advance($id, 'pending_approval', __('Sheet approved.'), function (Budget $budget) {
            $budget->approved_by = Auth::id();
            $budget->approved_at = now();
        });
    }

    public function activate($id)
    {
        return $this->advance($id, 'approved', __('Sheet is now active for the year.'));
    }

    public function close($id)
    {
        return $this->advance($id, 'active', __('Sheet closed. Its closing balance carries forward to the next one.'));
    }

    /**
     * Move a sheet one step along, refusing anything out of order.
     */
    protected function advance($id, string $from, string $message, ?callable $before = null)
    {
        $budget = Budget::where('is_institutional', true)->findOrFail($id);

        if ($budget->status !== $from) {
            Flasher::addError(
                __('That step is not available: this sheet is :status.', ['status' => str_replace('_', ' ', $budget->status)]),
                __('msg_error')
            );
            return redirect()->back();
        }

        if ($before) {
            $before($budget);
        }

        $budget->status = static::TRANSITIONS[$from];
        $budget->updated_by = Auth::id();
        $budget->save();

        Flasher::addSuccess($message, __('msg_success'));

        return redirect()->route('admin.budget-sheet.show', $budget->id);
    }

    /**
     * Correct the period a sheet covers.
     *
     * A sheet created with the wrong dates reports the wrong year's actuals,
     * which is worse than an empty one — the figures look authoritative.
     */
    public function updatePeriod(Request $request, $id)
    {
        $budget = Budget::where('is_institutional', true)->findOrFail($id);

        // Moving the dates changes which actuals the sheet reports, so it is a
        // draft-only action for the same reason the figures are.
        if (!static::isEditable($budget)) {
            Flasher::addError(__('The period cannot be changed once the sheet has been submitted.'), __('msg_error'));
            return redirect()->route('admin.budget-sheet.show', $budget->id);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $budget->update($validated);

        Flasher::addSuccess(__('Period updated. The actual figures now cover the new dates.'), __('msg_success'));

        return redirect()->route('admin.budget-sheet.show', $budget->id);
    }

    /**
     * Remove a sheet that was created by mistake.
     *
     * Only while it is still a draft: once submitted for approval it is part of
     * the record, and deleting it would remove figures someone has reviewed.
     */
    public function destroy($id)
    {
        $budget = Budget::where('is_institutional', true)->findOrFail($id);

        if ($budget->status !== 'draft') {
            Flasher::addError(__('Only a draft sheet can be deleted. This one has been submitted.'), __('msg_error'));
            return redirect()->back();
        }

        DB::transaction(function () use ($budget) {
            // The figures belong to the sheet and have no meaning without it.
            BudgetAllocation::where('budget_id', $budget->id)->delete();
            $budget->delete();
        });

        Flasher::addSuccess(__('Sheet deleted.'), __('msg_success'));

        return redirect()->route('admin.budget-sheet.index');
    }

    /** The institutional budgets, newest first. */
    public function index()
    {
        return view('admin.budget-sheet.index', [
            'title' => __('Income & Expenditure Sheet'),
            'budgets' => Budget::where('is_institutional', true)
                ->orderByDesc('start_date')->get(),
        ]);
    }

    /** Start a sheet for a period, if one does not already exist. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'opening_balance' => ['nullable', 'numeric'],
            'seed_from' => ['nullable', 'in:actual,budget'],
            'uplift_percent' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
        ]);

        $budget = Budget::create([
            'title' => $validated['title'],
            'type' => 'annual',
            'is_institutional' => true,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            // Carried forward from the previous sheet's closing balance when
            // one exists, so the years chain rather than being retyped.
            'opening_balance' => $validated['opening_balance'] ?? $this->previousClosingBalance($validated['start_date']),
            'total_amount' => 0,
            'status' => 'draft',
            'fiscal_year' => date('Y', strtotime($validated['start_date'])),
            'created_by' => Auth::id(),
        ]);

        // Optionally start from last year rather than from nothing. Retyping 66
        // lines is how figures get mistyped, and a budget is nearly always last
        // year plus a judgement about what changes.
        $seeded = 0;
        if (!empty($validated['seed_from'])) {
            $seeded = $this->seedFromPreviousSheet(
                $budget,
                $validated['seed_from'],
                (float) ($validated['uplift_percent'] ?? 0)
            );
        }

        Flasher::addSuccess(
            $seeded
                ? __(':count lines carried forward. Review every figure before submitting.', ['count' => $seeded])
                : __('Sheet created. Enter the figures below.'),
            __('msg_success')
        );

        return redirect()->route('admin.budget-sheet.show', $budget->id);
    }

    /**
     * Fill a new sheet from the one before it.
     *
     * `actual` copies what really happened last year, `budget` copies what was
     * planned. Actual is the better base for a forecast — a plan that was never
     * met is a poor starting point for the next one — so the screen defaults to
     * it, and an uplift can be applied for known inflation or fee changes.
     *
     * Headers are skipped: they total their children and carry no figure.
     */
    protected function seedFromPreviousSheet(Budget $budget, string $basis, float $upliftPercent): int
    {
        $previous = $this->previousSheet($budget);
        if (!$previous) {
            return 0;
        }

        if ($basis === 'actual') {
            $source = $this->actuals->forPeriod(
                $previous->start_date?->format('Y-m-d'),
                $previous->end_date?->format('Y-m-d')
            )['lines'];
        } else {
            $source = BudgetAllocation::where('budget_id', $previous->id)
                ->whereNotNull('budget_line_id')
                ->pluck('allocated_amount', 'budget_line_id')
                ->map(fn ($v) => (float) $v)
                ->toArray();
        }

        $multiplier = 1 + ($upliftPercent / 100);
        $postable = BudgetLine::where('is_header', false)->get()->keyBy('id');
        $count = 0;

        foreach ($source as $lineId => $amount) {
            $line = $postable->get((int) $lineId);
            if (!$line || $amount == 0) {
                continue;
            }

            // Same shape saveFigures writes, so a carried-forward figure is
            // indistinguishable from a typed one.
            BudgetAllocation::updateOrCreate(
                ['budget_id' => $budget->id, 'budget_line_id' => (int) $lineId],
                [
                    'allocated_amount' => round($amount * $multiplier, 2),
                    'title' => $line->name,
                    'is_active' => true,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]
            );
            $count++;
        }

        // Expenditure and capital only — income is money expected in, not a pot
        // being allocated from. Matches saveFigures().
        $budget->total_amount = $this->sectionTotal($budget, BudgetLine::SECTION_EXPENDITURE)
            + $this->sectionTotal($budget, BudgetLine::SECTION_CAPITAL);
        $budget->save();

        return $count;
    }

    /**
     * Everything the sheet shows, assembled once.
     *
     * Shared by the screen and the PDF so the printed document can never drift
     * from what was on screen when it was approved.
     */
    protected function sheetData($id): array
    {
        $budget = Budget::where('is_institutional', true)->findOrFail($id);

        $lines = BudgetLine::sheet();

        // Budgeted figures, keyed by line.
        $budgeted = BudgetAllocation::where('budget_id', $budget->id)
            ->whereNotNull('budget_line_id')
            ->pluck('allocated_amount', 'budget_line_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $result = $this->actuals->forPeriod(
            $budget->start_date?->format('Y-m-d'),
            $budget->end_date?->format('Y-m-d')
        );

        // Write the mapping-derived actuals back onto the allocations.
        //
        // The rest of the budget module — the dashboard, the performance and
        // variance reports, the over-budget alerts — all read spent_amount,
        // which only ever fills from expenses someone tagged by hand. Sheet
        // lines are never tagged, so those features saw zero and reported
        // nothing. Keeping the field in step means the sheet is a first-class
        // budget everywhere, instead of a screen that stands apart.
        $this->syncSpentAmounts($budget, $result['lines']);

        $actual = $this->actuals->withHeaderTotals($result['lines']);
        $budgetedWithHeaders = $this->actuals->withHeaderTotals($budgeted);

        $totals = $this->totals($budget, $budgeted, $result['lines']);

        // Last year, line for line. Budgeting starts from what actually
        // happened, so without this column the Bursar has to open the previous
        // sheet in another tab and read across by eye.
        $previous = $this->previousSheet($budget);
        $priorActual = $previous ? $this->actuals->withHeaderTotals(
            $this->actuals->forPeriod(
                $previous->start_date?->format('Y-m-d'),
                $previous->end_date?->format('Y-m-d')
            )['lines']
        ) : [];

        // Section totals for last year, so the comparison holds at the bottom of
        // each section as well as line by line.
        $priorTotals = [
            'opening' => $previous ? (float) $previous->opening_balance : 0.0,
            'income' => $previous ? $this->actuals->sectionTotal($priorActual, BudgetLine::SECTION_INCOME) : 0.0,
            'expenditure' => $previous ? $this->actuals->sectionTotal($priorActual, BudgetLine::SECTION_EXPENDITURE) : 0.0,
            'capital' => $previous ? $this->actuals->sectionTotal($priorActual, BudgetLine::SECTION_CAPITAL) : 0.0,
        ];
        $priorTotals['closing'] = $priorTotals['opening'] + $priorTotals['income'] - $priorTotals['expenditure'];

        // Does this sheet agree with the accounts? Answering it on the sheet
        // itself is the point: a budget nobody can tie back to the ledger is a
        // spreadsheet, not a control.
        $reconciliation = $this->reconciliation->reconcile(
            $budget->start_date?->format('Y-m-d'),
            $budget->end_date?->format('Y-m-d')
        );

        return [
            'title' => $budget->title,
            'budget' => $budget,
            'lines' => $lines,
            'budgeted' => $budgetedWithHeaders,
            'actual' => $actual,
            'unallocated' => $result['unallocated'],
            'totals' => $totals,
            'editable' => static::isEditable($budget),
            'delegated' => $this->delegatedByLine($budget),
            'previous' => $previous,
            'priorActual' => $priorActual,
            'priorTotals' => $priorTotals,
            'reconciliation' => $reconciliation,
            'accountsByLine' => $this->reconciliation->accountsByLine(),
            // Where to draw a subtotal, decided once so the screen, the PDF and
            // the workbook cannot disagree about where a group ends.
            'groupEnds' => BudgetLine::groupEnds($lines),
        ];
    }

    /** The sheet on screen. */
    public function show(Request $request, $id)
    {
        return view('admin.budget-sheet.show', $this->sheetData($id));
    }

    /**
     * The sheet as a PDF, for signing and filing.
     *
     * The Income & Expenditure sheet is the document the Bursar signs and the
     * diocese receives, so it has to leave the system as a document rather than
     * a browser page. It carries the configured letterhead, which is why this
     * renders through LetterheadService rather than repeating a masthead here.
     */
    public function exportPdf($id)
    {
        $data = $this->sheetData($id);
        $data['letterhead'] = $this->letterhead->render(true);
        $data['letterheadStyles'] = $this->letterhead->styles();

        $pdf = Pdf::loadView('admin.budget-sheet.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $name = 'income-and-expenditure-' . Str::slug($data['budget']->title) . '.pdf';

        return $pdf->download($name);
    }

    /**
     * The sheet as a workbook.
     *
     * The PDF is the document to sign; this is the one to work in. Figures go out
     * as numbers rather than formatted text so they can actually be summed and
     * modelled on the other side.
     */
    public function exportExcel($id)
    {
        $data = $this->sheetData($id);
        $name = 'income-and-expenditure-' . Str::slug($data['budget']->title) . '.xlsx';

        return Excel::download(new BudgetSheetExport($data), $name);
    }

    /** Save the figures typed into the sheet. */
    public function saveFigures(Request $request, $id)
    {
        $budget = Budget::where('is_institutional', true)->findOrFail($id);

        // Enforced here, not only in the view. Once a sheet has been submitted,
        // someone is reviewing those figures — a hidden form post must not be
        // able to change them underneath the approval.
        if (!static::isEditable($budget)) {
            Flasher::addError(
                __('This sheet is :status, so its figures can no longer be changed. Use a revision instead.',
                    ['status' => str_replace('_', ' ', $budget->status)]),
                __('msg_error')
            );
            return redirect()->route('admin.budget-sheet.show', $budget->id);
        }

        $request->validate([
            'opening_balance' => ['nullable', 'numeric'],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Headers total their children, so a figure typed against one would be
        // double counted. Ignore them rather than reject the whole save.
        $headerIds = BudgetLine::where('is_header', true)->pluck('id')->flip();

        DB::beginTransaction();
        try {
            $budget->opening_balance = $request->input('opening_balance', 0) ?: 0;

            foreach ((array) $request->input('amounts', []) as $lineId => $amount) {
                if ($headerIds->has((int) $lineId)) {
                    continue;
                }

                $amount = $amount === null || $amount === '' ? null : (float) $amount;

                if ($amount === null || $amount == 0.0) {
                    BudgetAllocation::where('budget_id', $budget->id)
                        ->where('budget_line_id', $lineId)->delete();
                    continue;
                }

                BudgetAllocation::updateOrCreate(
                    ['budget_id' => $budget->id, 'budget_line_id' => $lineId],
                    [
                        'allocated_amount' => $amount,
                        'title' => optional(BudgetLine::find($lineId))->name,
                        'is_active' => true,
                        'updated_by' => Auth::id(),
                    ]
                );
            }

            // The stored total is expenditure only: income is a forecast of
            // money coming in, not a pot being allocated from.
            $budget->total_amount = $this->sectionTotal($budget, BudgetLine::SECTION_EXPENDITURE)
                + $this->sectionTotal($budget, BudgetLine::SECTION_CAPITAL);
            $budget->save();

            DB::commit();
            Flasher::addSuccess(__('Figures saved.'), __('msg_success'));
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            Flasher::addError(__('Could not save the figures.'), __('msg_error'));
        }

        return redirect()->route('admin.budget-sheet.show', $budget->id);
    }

    /**
     * Keep each allocation's spent / remaining in step with the real actuals.
     *
     * Only rows whose figures have actually moved are written, so opening the
     * sheet is not 78 pointless updates.
     *
     * @param  array<int,float> $actualsByLine keyed by budget line id
     */
    protected function syncSpentAmounts(Budget $budget, array $actualsByLine): void
    {
        $allocations = BudgetAllocation::where('budget_id', $budget->id)
            ->whereNotNull('budget_line_id')->get();

        foreach ($allocations as $allocation) {
            $spent = (float) ($actualsByLine[$allocation->budget_line_id] ?? 0);
            $remaining = (float) $allocation->allocated_amount - $spent;

            if (abs((float) $allocation->spent_amount - $spent) < 0.01
                && abs((float) $allocation->remaining_amount - $remaining) < 0.01) {
                continue;
            }

            $allocation->forceFill([
                'spent_amount' => $spent,
                'remaining_amount' => $remaining,
            ])->save();
        }

        // The budget-level totals count money going OUT only.
        //
        // total_amount is expenditure plus capital, so spent_amount has to be
        // measured the same way. Summing every line instead — income included —
        // produced a utilisation of 948% on a healthy sheet, and the dashboard
        // alerts would have reported it as massively overspent.
        $spendingLineIds = BudgetLine::whereIn('section', [
            BudgetLine::SECTION_EXPENDITURE,
            BudgetLine::SECTION_CAPITAL,
        ])->where('is_header', false)->pluck('id')->flip();

        $totalAllocated = 0.0;
        $totalSpent = 0.0;

        foreach ($allocations as $allocation) {
            if (!$spendingLineIds->has($allocation->budget_line_id)) {
                continue;
            }
            $totalAllocated += (float) $allocation->allocated_amount;
            $totalSpent += (float) ($actualsByLine[$allocation->budget_line_id] ?? 0);
        }

        $budget->forceFill([
            // total_amount is recomputed here as well as on save, so the figure
            // the alerts divide by can never drift away from the allocations
            // they are measuring — a stale total silently changes every
            // percentage on the dashboard.
            'total_amount' => $totalAllocated,
            'allocated_amount' => $totalAllocated,
            'spent_amount' => $totalSpent,
            'remaining_amount' => $totalAllocated - $totalSpent,
        ])->save();
    }

    /**
     * How much of each sheet line has been handed to a sub-budget.
     *
     * A departmental budget allocates against expense categories, and the
     * category → budget line mapping already says where each of those belongs on
     * the sheet. So the delegated figure is derivable — nobody has to record it
     * twice, and it cannot fall out of step with the sub-budget itself.
     *
     * @return array<int,float> keyed by budget line id
     */
    protected function delegatedByLine(Budget $budget): array
    {
        $children = Budget::where('parent_id', $budget->id)
            ->whereNotIn('status', ['cancelled'])->pluck('id');

        if ($children->isEmpty()) {
            return [];
        }

        $rows = DB::table('budget_allocations as a')
            ->join('default_account_mappings as m', function ($join) {
                $join->on('m.category_id', '=', 'a.expense_category_id')
                    ->where('m.mapping_type', '=', 'expense_category');
            })
            ->whereIn('a.budget_id', $children)
            ->whereNotNull('m.budget_line_id')
            ->selectRaw('m.budget_line_id, SUM(a.allocated_amount) as total')
            ->groupBy('m.budget_line_id')
            ->get();

        $delegated = [];
        foreach ($rows as $row) {
            $delegated[(int) $row->budget_line_id] = (float) $row->total;
        }

        return $delegated;
    }

    /** Opening, income, expenditure, closing and the cash reconciliation. */
    protected function totals(Budget $budget, array $budgeted, array $actual): array
    {
        $sum = function (array $figures, string $section) {
            return $this->actuals->sectionTotal($figures, $section);
        };

        $opening = (float) $budget->opening_balance;

        $out = [];
        foreach ([['budget', $budgeted], ['actual', $actual]] as [$key, $figures]) {
            $income = $sum($figures, BudgetLine::SECTION_INCOME);
            $expenditure = $sum($figures, BudgetLine::SECTION_EXPENDITURE);
            $capital = $sum($figures, BudgetLine::SECTION_CAPITAL);

            // Presented exactly as the paper does it: capital sits below the
            // closing balance and is not deducted from it.
            $closing = $opening + $income - $expenditure;

            // Depreciation never leaves the bank, and capital spending does —
            // neither is reflected in the closing balance above, so the cash
            // position has to be stated separately or the sheet reads as though
            // the closing balance were money in the bank.
            $depreciation = $this->groupTotal($figures, '500');

            $out[$key] = [
                'opening' => $opening,
                'income' => $income,
                'expenditure' => $expenditure,
                'capital' => $capital,
                'closing' => $closing,
                'depreciation' => $depreciation,
                'cash' => $closing + $depreciation - $capital,
            ];
        }

        return $out;
    }

    /** A group header's total, by code. */
    protected function groupTotal(array $figures, string $headerCode): float
    {
        $header = BudgetLine::where('code', $headerCode)->first();
        if (!$header) {
            return 0.0;
        }

        $total = 0.0;
        foreach (BudgetLine::where('parent_id', $header->id)->pluck('id') as $childId) {
            $total += $figures[$childId] ?? 0;
        }

        return $total;
    }

    protected function sectionTotal(Budget $budget, string $section): float
    {
        return (float) BudgetAllocation::where('budget_id', $budget->id)
            ->whereIn('budget_line_id', BudgetLine::where('section', $section)->where('is_header', false)->pluck('id'))
            ->sum('allocated_amount');
    }

    /** The institutional sheet whose period ran immediately before this one. */
    protected function previousSheet(Budget $budget): ?Budget
    {
        if (!$budget->start_date) {
            return null;
        }

        return Budget::where('is_institutional', true)
            ->where('id', '!=', $budget->id)
            ->where('end_date', '<', $budget->start_date->format('Y-m-d'))
            ->orderByDesc('end_date')
            ->first();
    }

    /** The closing balance of the sheet that ended before this one starts. */
    protected function previousClosingBalance(string $startDate): float
    {
        $previous = Budget::where('is_institutional', true)
            ->where('end_date', '<', $startDate)
            ->orderByDesc('end_date')->first();

        if (!$previous) {
            return 0.0;
        }

        $budgeted = BudgetAllocation::where('budget_id', $previous->id)
            ->whereNotNull('budget_line_id')
            ->pluck('allocated_amount', 'budget_line_id')
            ->map(fn ($v) => (float) $v)->toArray();

        return (float) $previous->opening_balance
            + $this->actuals->sectionTotal($budgeted, BudgetLine::SECTION_INCOME)
            - $this->actuals->sectionTotal($budgeted, BudgetLine::SECTION_EXPENDITURE);
    }
}
