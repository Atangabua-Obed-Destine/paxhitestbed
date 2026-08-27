<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DaybookExport;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Services\DaybookService;
use App\Services\LetterheadService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The cash analysis book.
 *
 * The Bursar keeps a columnar daybook: every cash movement on one row, its
 * amount repeated under the analysis column it belongs to, and a monthly
 * summary that turns those columns into budget-versus-actual with the year to
 * date carried forward.
 *
 * Nothing here is typed twice. Every row already exists somewhere in the system
 * — a fee, an income, an expense, a payroll run — and this presents them in the
 * shape the Bursar already trusts. The Income & Expenditure sheet is the same
 * money summed down the same columns, which is why DaybookService is what both
 * read from.
 */
class DaybookController extends Controller
{
    protected DaybookService $daybook;

    public function __construct(DaybookService $daybook)
    {
        $this->daybook = $daybook;

        $this->middleware('permission:daybook-view');
    }

    /** The book itself, one period at a time. */
    public function index(Request $request)
    {
        $data = $this->bookData($request);

        if (!$data) {
            return redirect()->route('admin.budget-sheet.index')
                ->with('error', __('No institutional budget exists yet, so there is no period to open a daybook on.'));
        }

        return view('admin.daybook.index', $data);
    }

    /** The Monthly Summary: Budget · Monthly · BB Forward · Cumulative · Balance. */
    public function summary(Request $request)
    {
        $data = $this->summaryData($request);

        if (!$data) {
            return redirect()->route('admin.budget-sheet.index')
                ->with('error', __('No institutional budget exists yet.'));
        }

        return view('admin.daybook.summary', $data);
    }

    /**
     * The year as a shape: trends, pacing, and where the money goes.
     *
     * A month of the book answers "what happened". This answers the questions
     * that follow from it — is collection seasonal, are we spending faster than
     * the year is passing, which lines carry the weight.
     */
    public function analysis(Request $request)
    {
        $context = $this->context($request);

        if (!$context) {
            return redirect()->route('admin.budget-sheet.index')
                ->with('error', __('No institutional budget exists yet.'));
        }

        return view('admin.daybook.analysis', $context + [
            'title' => __('Trends & Analysis'),
            'analysis' => $this->daybook->analysis($context['budget']),
        ]);
    }

    /** The book as a document, for signing and filing. */
    public function pdf(Request $request)
    {
        $data = $this->bookData($request);
        abort_unless($data, 404);

        $letterhead = app(LetterheadService::class);

        // Same construction as the Income & Expenditure sheet: the letterhead is
        // rendered for PDF (filesystem image paths, not URLs, which dompdf
        // cannot fetch) and the parser options it needs are set explicitly.
        $pdf = Pdf::loadView('admin.daybook.pdf', $data + [
            'letterhead' => $letterhead->render(true),
            'letterheadStyles' => $letterhead->styles(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->download($this->filename($data['period'], 'daybook') . '.pdf');
    }

    /** The book as a workbook, laid out as the Bursar's own file lays it out. */
    public function excel(Request $request)
    {
        $data = $this->bookData($request);
        abort_unless($data, 404);

        $summary = $this->summaryData($request);

        return Excel::download(
            new DaybookExport($data, $summary),
            $this->filename($data['period'], 'daybook') . '.xlsx'
        );
    }

    /* ===================================================================
     |  Shared assembly
     |===================================================================*/

    /**
     * The budget and period a request is asking about.
     *
     * Periods come from the budget, not the fiscal year: a July-to-June book is
     * ordinary in a school while the ledger's fiscal years are calendar, and
     * tying the book to the budget keeps the two from having to agree.
     */
    protected function context(Request $request): ?array
    {
        $budget = $request->filled('budget_id')
            ? Budget::where('is_institutional', true)->find($request->input('budget_id'))
            : Budget::where('is_institutional', true)->orderByDesc('id')->first();

        if (!$budget) {
            return null;
        }

        $periods = $this->daybook->periodsFor($budget);

        if ($periods->isEmpty()) {
            return null;
        }

        $period = $request->filled('period_id')
            ? $periods->firstWhere('id', (int) $request->input('period_id'))
            : null;

        // Default to the period we are actually in, falling back to the last
        // one that carries movement rather than to an empty December.
        $period = $period ?: $periods->first(function (AccountingPeriod $p) {
            return now()->between($p->start_date, $p->end_date);
        }) ?: $periods->first();

        return [
            'budget' => $budget,
            'budgets' => Budget::where('is_institutional', true)->orderByDesc('id')->get(),
            'periods' => $periods,
            'period' => $period,
        ];
    }

    protected function bookData(Request $request): ?array
    {
        $context = $this->context($request);
        if (!$context) {
            return null;
        }

        $period = $context['period'];
        $rows = $this->daybook->rows(
            $this->day($period->start_date),
            $this->day($period->end_date)
        );

        $lines = BudgetLine::whereIn('id', array_filter(array_column($rows, 'line_id')))
            ->get()
            ->keyBy('id');

        $in = 0.0;
        $out = 0.0;
        $unanalysed = [];

        foreach ($rows as $row) {
            $row['direction'] === DaybookService::IN
                ? $in += $row['amount']
                : $out += $row['amount'];

            if ($row['line_id'] === null) {
                $unanalysed[] = $row;
            }
        }

        return $context + [
            'title' => __('Daybook'),
            'rows' => $rows,
            'lines' => $lines,
            'totalIn' => $in,
            'totalOut' => $out,
            // Called out at the top rather than left to be noticed: money with
            // no column is money the sheet cannot report.
            'unanalysed' => $unanalysed,
        ];
    }

    protected function summaryData(Request $request): ?array
    {
        $context = $this->context($request);
        if (!$context) {
            return null;
        }

        return $context + [
            'title' => __('Monthly Summary'),
            'summary' => $this->daybook->monthlySummary($context['budget'], $context['period']),
        ];
    }

    protected function day($value): string
    {
        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }

    protected function filename(AccountingPeriod $period, string $prefix): string
    {
        return $prefix . '-' . str_replace(' ', '-', strtolower($period->name));
    }
}
