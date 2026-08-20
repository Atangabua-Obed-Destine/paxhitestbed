<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Models\ChartOfAccount;
use App\Services\Accounting\AgingReportService;
use App\Services\Accounting\CashFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class AccountingReportsController extends Controller
{
    protected $agingService;
    protected $cashFlowService;

    public function __construct(AgingReportService $agingService, CashFlowService $cashFlowService)
    {
        $this->agingService = $agingService;
        $this->cashFlowService = $cashFlowService;
        
        $this->middleware('auth');
        $this->middleware('permission:accounting-report-view');
    }

    /**
     * Reports dashboard
     */
    public function index()
    {
        try {
            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
            $currentFiscalYear = FiscalYear::getActiveFiscalYear();

            return view('admin.accounting.reports.index', compact('fiscalYears', 'currentFiscalYear'));
        } catch (Exception $e) {
            Log::error('Error loading reports dashboard', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_reports'));
        }
    }

    /**
     * Accounts Receivable Aging Report
     */
    public function receivablesAging(Request $request)
    {
        try {
            $asOfDate = $request->as_of_date ?? now()->format('Y-m-d');
            
            $report = $this->agingService->getAgingSummaryWithCharts('receivables', $asOfDate);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $report,
                ]);
            }

            return view('admin.accounting.reports.receivables-aging', compact('report', 'asOfDate'));
        } catch (Exception $e) {
            Log::error('Error generating receivables aging report', ['exception' => $e->getMessage()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('error_generating_report'),
                ], 500);
            }
            
            return back()->with('error', __('error_generating_report'));
        }
    }

    /**
     * Accounts Payable Aging Report
     */
    public function payablesAging(Request $request)
    {
        try {
            $asOfDate = $request->as_of_date ?? now()->format('Y-m-d');
            
            $report = $this->agingService->getAgingSummaryWithCharts('payables', $asOfDate);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $report,
                ]);
            }

            return view('admin.accounting.reports.payables-aging', compact('report', 'asOfDate'));
        } catch (Exception $e) {
            Log::error('Error generating payables aging report', ['exception' => $e->getMessage()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('error_generating_report'),
                ], 500);
            }
            
            return back()->with('error', __('error_generating_report'));
        }
    }

    /**
     * Student Fee Aging Report
     */
    public function studentFeeAging(Request $request)
    {
        try {
            $asOfDate = $request->as_of_date ?? now()->format('Y-m-d');
            $filters = $request->only(['program_id', 'batch_id']);
            
            $report = $this->agingService->getStudentFeeAging($asOfDate, $filters);

            // Get programs and batches for filters
            $programs = \App\Models\Program::orderBy('title')->get();
            $batches = \App\Models\Batch::orderBy('title')->get();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $report,
                ]);
            }

            return view('admin.accounting.reports.student-fee-aging', compact('report', 'asOfDate', 'programs', 'batches'));
        } catch (Exception $e) {
            Log::error('Error generating student fee aging report', ['exception' => $e->getMessage()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('error_generating_report'),
                ], 500);
            }
            
            return back()->with('error', __('error_generating_report'));
        }
    }

    /**
     * Cash Flow Statement
     */
    public function cashFlowStatement(Request $request)
    {
        try {
            $fiscalYearId = $request->fiscal_year_id ?? FiscalYear::getActiveFiscalYear()?->id;
            
            if (!$fiscalYearId) {
                return back()->with('error', __('no_active_fiscal_year'));
            }

            $fiscalYear = FiscalYear::findOrFail($fiscalYearId);
            
            $startDate = $request->start_date ?? $fiscalYear->start_date;
            $endDate = $request->end_date ?? $fiscalYear->end_date;

            $statement = $this->cashFlowService->generateCashFlowStatement($fiscalYearId, $startDate, $endDate);

            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $statement,
                ]);
            }

            return view('admin.accounting.reports.cash-flow-statement', compact('statement', 'fiscalYears', 'fiscalYear', 'startDate', 'endDate'));
        } catch (Exception $e) {
            Log::error('Error generating cash flow statement', ['exception' => $e->getMessage()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('error_generating_report'),
                ], 500);
            }
            
            return back()->with('error', __('error_generating_report') . ': ' . $e->getMessage());
        }
    }

    /**
     * Comparative Cash Flow Statement
     */
    public function comparativeCashFlow(Request $request)
    {
        try {
            $fiscalYearId = $request->fiscal_year_id ?? FiscalYear::getActiveFiscalYear()?->id;
            $previousFiscalYearId = $request->previous_fiscal_year_id;
            
            if (!$fiscalYearId) {
                return back()->with('error', __('no_active_fiscal_year'));
            }

            $statement = $this->cashFlowService->generateComparativeCashFlowStatement($fiscalYearId, $previousFiscalYearId);

            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
            $currentFiscalYear = FiscalYear::find($fiscalYearId);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $statement,
                ]);
            }

            return view('admin.accounting.reports.comparative-cash-flow', compact('statement', 'fiscalYears', 'currentFiscalYear'));
        } catch (Exception $e) {
            Log::error('Error generating comparative cash flow', ['exception' => $e->getMessage()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('error_generating_report'),
                ], 500);
            }
            
            return back()->with('error', __('error_generating_report'));
        }
    }

    /**
     * Budget vs Actual Report
     */
    public function budgetVsActual(Request $request)
    {
        try {
            $fiscalYearId = $request->fiscal_year_id ?? FiscalYear::getActiveFiscalYear()?->id;
            
            if (!$fiscalYearId) {
                return back()->with('error', __('no_active_fiscal_year'));
            }

            $fiscalYear = FiscalYear::findOrFail($fiscalYearId);

            // Get budget data if budget module exists
            $hasBudgetModule = class_exists('\App\Models\Budget');
            
            $report = [];
            if ($hasBudgetModule) {
                $report = $this->generateBudgetVsActualReport($fiscalYearId);
            }

            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

            return view('admin.accounting.reports.budget-vs-actual', compact('report', 'fiscalYears', 'fiscalYear', 'hasBudgetModule'));
        } catch (Exception $e) {
            Log::error('Error generating budget vs actual report', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_generating_report'));
        }
    }

    /**
     * Generate budget vs actual report data
     */
    protected function generateBudgetVsActualReport($fiscalYearId)
    {
        $fiscalYear = \App\Models\FiscalYear::find($fiscalYearId);
        if (!$fiscalYear) {
            return [];
        }

        // Report against budget lines rather than ledger accounts. The sheet is
        // finer grained than the statutory chart, and the figures come from the
        // same resolver the sheet itself uses, so the two can never disagree.
        $actualsService = app(\App\Services\BudgetActualsService::class);

        $budget = \App\Models\Budget::where('is_institutional', true)
            ->where('start_date', '<=', $fiscalYear->end_date)
            ->where('end_date', '>=', $fiscalYear->start_date)
            ->orderByDesc('start_date')
            ->first();

        $from = $budget ? optional($budget->start_date)->format('Y-m-d') : $fiscalYear->start_date->format('Y-m-d');
        $to = $budget ? optional($budget->end_date)->format('Y-m-d') : $fiscalYear->end_date->format('Y-m-d');

        $resolved = $actualsService->forPeriod($from, $to);
        $actuals = $actualsService->withHeaderTotals($resolved['lines']);

        $budgeted = [];
        if ($budget) {
            $budgeted = \App\Models\BudgetAllocation::where('budget_id', $budget->id)
                ->whereNotNull('budget_line_id')
                ->pluck('allocated_amount', 'budget_line_id')
                ->map(fn ($v) => (float) $v)->toArray();
            $budgeted = $actualsService->withHeaderTotals($budgeted);
        }

        $report = [];
        foreach (\App\Models\BudgetLine::sheet() as $line) {
            $budgetAmount = (float) ($budgeted[$line->id] ?? 0);
            $actualAmount = (float) ($actuals[$line->id] ?? 0);

            // Skip lines with no activity either side, so the report is short
            // enough to read.
            if ($budgetAmount == 0.0 && $actualAmount == 0.0) {
                continue;
            }

            $report[] = [
                'account_code' => $line->code,
                'account_name' => $line->name,
                'section' => $line->section,
                'is_header' => (bool) $line->is_header,
                'budget' => $budgetAmount,
                'actual' => $actualAmount,
                'variance' => $budgetAmount - $actualAmount,
                'variance_percent' => $budgetAmount != 0.0
                    ? (($budgetAmount - $actualAmount) / $budgetAmount) * 100
                    : 0,
            ];
        }

        return $report;
    }

    /**
     * Get account balance for fiscal year
     */
    protected function getAccountBalance($accountId, $fiscalYearId)
    {
        $debits = \App\Models\JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('is_posted', true);
            })
            ->sum('debit');

        $credits = \App\Models\JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('is_posted', true);
            })
            ->sum('credit');

        return $debits - $credits;
    }

    /**
     * Export report to PDF
     */
    public function exportPdf(Request $request)
    {
        // Accept both 'report' and 'report_type' parameter names
        $reportType = $request->input('report') ?? $request->input('report_type');
        
        // Normalize report type (convert hyphens to underscores)
        $reportType = str_replace('-', '_', $reportType);
        
        $allowedTypes = ['receivables_aging', 'payables_aging', 'student_fee_aging', 'cash_flow', 'comparative_cash_flow', 'budget_vs_actual'];
        
        if (!$reportType || !in_array($reportType, $allowedTypes)) {
            return back()->with('error', __('invalid_report_type'));
        }

        try {
            $data = [];
            $view = '';

            switch ($reportType) {
                case 'receivables_aging':
                    $data = $this->agingService->getReceivablesAging($request->as_of_date);
                    $view = 'admin.accounting.reports.pdf.receivables-aging';
                    break;

                case 'payables_aging':
                    $data = $this->agingService->getPayablesAging($request->as_of_date);
                    $view = 'admin.accounting.reports.pdf.payables-aging';
                    break;

                case 'student_fee_aging':
                    $data = $this->agingService->getStudentFeeAging($request->as_of_date);
                    $view = 'admin.accounting.reports.pdf.student-fee-aging';
                    break;

                case 'cash_flow':
                    $data = $this->cashFlowService->generateCashFlowStatement($request->fiscal_year_id);
                    $view = 'admin.accounting.reports.pdf.cash-flow';
                    break;

                case 'comparative_cash_flow':
                    $data = $this->cashFlowService->generateComparativeCashFlowStatement($request->fiscal_year_id);
                    $view = 'admin.accounting.reports.pdf.comparative-cash-flow';
                    break;

                case 'budget_vs_actual':
                    $data = $this->generateBudgetVsActualReport($request->fiscal_year_id);
                    $view = 'admin.accounting.reports.pdf.budget-vs-actual';
                    break;
            }

            // If PDF package is available, generate PDF
            if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, compact('data', 'reportType'));
                return $pdf->download($reportType . '_' . date('Y-m-d') . '.pdf');
            }

            // Fallback: Return a printable HTML view
            return view($view, compact('data', 'reportType'))->with('printable', true);
        } catch (Exception $e) {
            Log::error('Error exporting report to PDF', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_exporting_report'));
        }
    }

    /**
     * Export report to Excel
     */
    public function exportExcel(Request $request)
    {
        // Accept both 'report' and 'report_type' parameter names
        $reportType = $request->input('report') ?? $request->input('report_type');
        
        // Normalize report type (convert hyphens to underscores)
        $reportType = str_replace('-', '_', $reportType);
        
        $allowedTypes = ['receivables_aging', 'payables_aging', 'student_fee_aging', 'cash_flow', 'comparative_cash_flow', 'budget_vs_actual'];
        
        if (!$reportType || !in_array($reportType, $allowedTypes)) {
            return back()->with('error', __('invalid_report_type'));
        }

        try {
            // This would integrate with Laravel Excel package
            // For now, return CSV

            $data = [];

            switch ($reportType) {
                case 'receivables_aging':
                    $data = $this->agingService->getReceivablesAging($request->as_of_date);
                    break;

                case 'payables_aging':
                    $data = $this->agingService->getPayablesAging($request->as_of_date);
                    break;

                case 'student_fee_aging':
                    $data = $this->agingService->getStudentFeeAging($request->as_of_date);
                    break;

                case 'cash_flow':
                    $data = $this->cashFlowService->generateCashFlowStatement($request->fiscal_year_id);
                    break;

                case 'comparative_cash_flow':
                    $data = $this->cashFlowService->generateComparativeCashFlowStatement($request->fiscal_year_id);
                    break;

                case 'budget_vs_actual':
                    $data = $this->generateBudgetVsActualReport($request->fiscal_year_id);
                    break;
            }

            // Generate CSV
            $filename = $reportType . '_' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($data, $reportType) {
                $file = fopen('php://output', 'w');
                
                // Write headers based on report type
                if (in_array($reportType, ['receivables_aging', 'payables_aging'])) {
                    fputcsv($file, ['Account Code', 'Account Name', '0-30 Days', '31-60 Days', '61-90 Days', '91-120 Days', 'Over 120 Days', 'Total']);
                    foreach ($data['accounts'] ?? [] as $account) {
                        fputcsv($file, [
                            $account['account_code'],
                            $account['account_name'],
                            $account['brackets']['bracket_0'] ?? 0,
                            $account['brackets']['bracket_1'] ?? 0,
                            $account['brackets']['bracket_2'] ?? 0,
                            $account['brackets']['bracket_3'] ?? 0,
                            $account['brackets']['bracket_4'] ?? 0,
                            $account['total'],
                        ]);
                    }
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            Log::error('Error exporting report to Excel', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_exporting_report'));
        }
    }
}
