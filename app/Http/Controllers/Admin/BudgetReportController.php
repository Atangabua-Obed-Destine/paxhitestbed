<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;
use App\Models\Department;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDF; // Assuming you have DomPDF or similar installed

class BudgetReportController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:budget-view');
    }

    /**
     * Budget Performance Report - Budget vs Actual by Category
     */
    public function performance(Request $request)
    {
        $budgets = Budget::query();
        
        // Filters
        if ($request->filled('budget_id')) {
            $budgets->where('id', $request->budget_id);
        }
        if ($request->filled('status')) {
            $budgets->where('status', $request->status);
        }
        if ($request->filled('budget_type')) {
            $budgets->where('type', $request->budget_type);
        }
        if ($request->filled('fiscal_year')) {
            $budgets->where('fiscal_year', $request->fiscal_year);
        }
        
        $budgets = $budgets->with(['allocations.expenseCategory'])->get();
        
        // Prepare report data
        $reportData = [];
        foreach ($budgets as $budget) {
            foreach ($budget->allocations as $allocation) {
                $variance = $allocation->allocated_amount - $allocation->spent_amount;
                $variancePercent = $allocation->allocated_amount > 0 
                    ? ($variance / $allocation->allocated_amount) * 100 
                    : 0;
                
                $reportData[] = [
                    'budget_code' => $budget->code,
                    'budget_title' => $budget->title,
                    'category' => $allocation->expenseCategory->title,
                    'allocated' => $allocation->allocated_amount,
                    'spent' => $allocation->spent_amount,
                    'committed' => $allocation->committed_amount,
                    'remaining' => $allocation->remaining_amount,
                    'variance' => $variance,
                    'variance_percent' => $variancePercent,
                    'status' => $variance >= 0 ? 'favorable' : 'unfavorable',
                ];
            }
        }
        
        // For filters
        $allBudgets = Budget::orderBy('title')->get();
        $fiscalYears = Budget::select('fiscal_year')->distinct()->orderBy('fiscal_year', 'desc')->pluck('fiscal_year');
        
        // Export handling
        if ($request->has('export')) {
            if ($request->export == 'pdf') {
                \Flasher::addWarning('PDF export requires DomPDF package. Please contact administrator.');
                return redirect()->back();
            }
        }
        
        return view('admin.budget.reports.performance', compact('reportData', 'allBudgets', 'fiscalYears'));
    }
    
    /**
     * Variance Analysis Report - Favorable vs Unfavorable variances
     */
    public function variance(Request $request)
    {
        $query = BudgetAllocation::with(['budget', 'expenseCategory']);
        
        // Filters
        if ($request->filled('budget_id')) {
            $query->where('budget_id', $request->budget_id);
        }
        if ($request->filled('variance_type')) {
            if ($request->variance_type == 'favorable') {
                $query->whereRaw('allocated_amount >= spent_amount');
            } else {
                $query->whereRaw('allocated_amount < spent_amount');
            }
        }
        
        $allocations = $query->get();
        
        // Calculate variance data
        $reportData = $allocations->map(function($allocation) {
            $variance = $allocation->allocated_amount - $allocation->spent_amount;
            $variancePercent = $allocation->allocated_amount > 0 
                ? ($variance / $allocation->allocated_amount) * 100 
                : 0;
            
            return [
                'budget_code' => $allocation->budget->code,
                'budget_title' => $allocation->budget->title,
                'category' => $allocation->expenseCategory->title,
                'allocated' => $allocation->allocated_amount,
                'spent' => $allocation->spent_amount,
                'variance' => $variance,
                'variance_percent' => $variancePercent,
                'status' => $variance >= 0 ? 'favorable' : 'unfavorable',
            ];
        });
        
        // Summary statistics
        $summary = [
            'total_allocations' => $allocations->count(),
            'favorable_count' => $allocations->filter(function($a) {
                return $a->allocated_amount >= $a->spent_amount;
            })->count(),
            'unfavorable_count' => $allocations->filter(function($a) {
                return $a->allocated_amount < $a->spent_amount;
            })->count(),
            'total_variance' => $allocations->sum(function($a) {
                return $a->allocated_amount - $a->spent_amount;
            }),
        ];
        
        $allBudgets = Budget::orderBy('title')->get();
        
        // Export handling
        if ($request->has('export')) {
            if ($request->export == 'pdf') {
                \Flasher::addWarning('PDF export requires DomPDF package. Please contact administrator.');
                return redirect()->back();
            }
        }
        
        return view('admin.budget.reports.variance', compact('reportData', 'summary', 'allBudgets'));
    }
    
    /**
     * Department Expenditure Report - Compare spending across departments
     */
    public function department(Request $request)
    {
        $query = Budget::where('type', 'departmental')
            ->whereNotNull('department_id')
            ->with('department');
        
        // Filters
        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year', $request->fiscal_year);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $budgets = $query->get();
        
        // Group by department
        $reportData = [];
        foreach ($budgets as $budget) {
            $deptId = $budget->department_id;
            if (!isset($reportData[$deptId])) {
                $reportData[$deptId] = [
                    'department' => $budget->department->title,
                    'budget_count' => 0,
                    'total_budget' => 0,
                    'total_allocated' => 0,
                    'total_spent' => 0,
                    'total_remaining' => 0,
                    'utilization_rate' => 0,
                ];
            }
            
            $reportData[$deptId]['budget_count']++;
            $reportData[$deptId]['total_budget'] += $budget->total_amount;
            $reportData[$deptId]['total_allocated'] += $budget->allocated_amount;
            $reportData[$deptId]['total_spent'] += $budget->spent_amount;
            $reportData[$deptId]['total_remaining'] += $budget->remaining_amount;
        }
        
        // Calculate utilization rates
        foreach ($reportData as $key => $data) {
            $reportData[$key]['utilization_rate'] = $data['total_budget'] > 0 
                ? ($data['total_spent'] / $data['total_budget']) * 100 
                : 0;
        }
        
        // Sort by spending (highest to lowest)
        usort($reportData, function($a, $b) {
            return $b['total_spent'] <=> $a['total_spent'];
        });
        
        $fiscalYears = Budget::select('fiscal_year')->distinct()->orderBy('fiscal_year', 'desc')->pluck('fiscal_year');
        
        // Export handling
        if ($request->has('export')) {
            if ($request->export == 'pdf') {
                \Flasher::addWarning('PDF export requires DomPDF package. Please contact administrator.');
                return redirect()->back();
            }
        }
        
        return view('admin.budget.reports.department', compact('reportData', 'fiscalYears'));
    }
    
    /**
     * Cash Flow Projection - Based on allocations and periods
     */
    public function cashflow(Request $request)
    {
        $budgetId = $request->get('budget_id');
        
        if (!$budgetId) {
            $activeBudgets = Budget::whereIn('status', ['active', 'approved'])->get();
            $allBudgets = Budget::orderBy('title')->get();
            
            return view('admin.budget.reports.cashflow', compact('activeBudgets', 'allBudgets'));
        }
        
        $budget = Budget::with(['allocations.expenseCategory'])->findOrFail($budgetId);
        
        // Project cash flow by month based on allocation periods
        $projections = [];
        $currentYear = Carbon::parse($budget->start_date)->year;
        
        for ($month = 1; $month <= 12; $month++) {
            $monthName = Carbon::create($currentYear, $month)->format('M');
            $projections[$monthName] = [
                'month' => $monthName,
                'planned' => 0,
                'actual' => 0,
                'cumulative_planned' => 0,
                'cumulative_actual' => 0,
            ];
        }
        
        // Calculate planned spending from allocations
        foreach ($budget->allocations as $allocation) {
            // Default to monthly if no period specified
            $period = $allocation->period ?? 'monthly';
            
            if ($period == 'monthly') {
                $monthlyAmount = $allocation->allocated_amount / 12;
                foreach ($projections as $month => $data) {
                    $projections[$month]['planned'] += $monthlyAmount;
                }
            } elseif ($period == 'quarterly') {
                $quarterlyAmount = $allocation->allocated_amount / 4;
                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                foreach ([0, 3, 6, 9] as $startMonth) {
                    if (isset($projections[$months[$startMonth]])) {
                        $projections[$months[$startMonth]]['planned'] += $quarterlyAmount;
                    }
                }
            } elseif ($period == 'annual') {
                $projections['Jan']['planned'] += $allocation->allocated_amount;
            } else {
                // If period is something else or null, spread evenly across 12 months
                $monthlyAmount = $allocation->allocated_amount / 12;
                foreach ($projections as $month => $data) {
                    $projections[$month]['planned'] += $monthlyAmount;
                }
            }
        }
        
        // Get actual spending by month (including pending expenses)
        $actualSpending = Expense::where('budget_id', $budgetId)
            ->whereIn('approval_status', ['approved', 'pending'])
            ->whereYear('date', $currentYear)
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->get()
            ->keyBy('month');
        
        // Add actual spending to projections
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($actualSpending as $monthNum => $spending) {
            $monthName = $months[$monthNum - 1];
            $projections[$monthName]['actual'] = $spending->total;
        }
        
        // Calculate cumulative values
        $cumulativePlanned = 0;
        $cumulativeActual = 0;
        foreach ($projections as $month => &$data) {
            $cumulativePlanned += $data['planned'];
            $cumulativeActual += $data['actual'];
            $data['cumulative_planned'] = $cumulativePlanned;
            $data['cumulative_actual'] = $cumulativeActual;
        }
        
        $allBudgets = Budget::orderBy('title')->get();
        
        // Export handling
        if ($request->has('export')) {
            if ($request->export == 'pdf') {
                \Flasher::addWarning('PDF export requires DomPDF package. Please contact administrator.');
                return redirect()->back();
            }
        }
        
        return view('admin.budget.reports.cashflow', compact('budget', 'projections', 'allBudgets'));
    }
    
    /**
     * Export Performance Report as PDF
     */
    private function exportPerformancePDF($data, $filters)
    {
        $pdf = PDF::loadView('admin.budget.reports.pdf.performance', compact('data', 'filters'));
        return $pdf->download('budget-performance-report-' . date('Y-m-d') . '.pdf');
    }
    
    /**
     * Export Variance Report as PDF
     */
    private function exportVariancePDF($data, $summary, $filters)
    {
        $pdf = PDF::loadView('admin.budget.reports.pdf.variance', compact('data', 'summary', 'filters'));
        return $pdf->download('budget-variance-report-' . date('Y-m-d') . '.pdf');
    }
    
    /**
     * Export Department Report as PDF
     */
    private function exportDepartmentPDF($data, $filters)
    {
        $pdf = PDF::loadView('admin.budget.reports.pdf.department', compact('data', 'filters'));
        return $pdf->download('department-expenditure-report-' . date('Y-m-d') . '.pdf');
    }
    
    /**
     * Export Cashflow Report as PDF
     */
    private function exportCashflowPDF($budget, $projections, $filters)
    {
        $pdf = PDF::loadView('admin.budget.reports.pdf.cashflow', compact('budget', 'projections', 'filters'));
        return $pdf->download('cashflow-projection-' . $budget->code . '-' . date('Y-m-d') . '.pdf');
    }
}
