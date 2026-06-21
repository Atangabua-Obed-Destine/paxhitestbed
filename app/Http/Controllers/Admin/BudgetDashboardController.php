<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;
use App\Models\Department;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BudgetDashboardController extends Controller
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
     * Display budget dashboard with KPIs and charts
     */
    public function index()
    {
        // Get active budgets
        $activeBudgets = Budget::where('status', 'active')->get();
        
        // Calculate overall KPIs
        $kpis = $this->calculateKPIs($activeBudgets);
        
        // Get chart data
        $utilizationData = $this->getUtilizationData($activeBudgets);
        $departmentData = $this->getDepartmentSpendingData();
        $monthlyTrendData = $this->getMonthlyTrendData();
        $categoryData = $this->getCategorySpendingData();
        
        // Get budget alerts
        $alerts = $this->getBudgetAlerts($activeBudgets);
        
        return view('admin.budget.dashboard', compact(
            'kpis',
            'activeBudgets',
            'utilizationData',
            'departmentData',
            'monthlyTrendData',
            'categoryData',
            'alerts'
        ));
    }
    
    /**
     * Calculate KPI metrics
     */
    private function calculateKPIs($budgets)
    {
        $totalBudget = $budgets->sum('total_amount');
        $totalAllocated = $budgets->sum('allocated_amount');
        $totalSpent = $budgets->sum('spent_amount');
        $totalRemaining = $budgets->sum('remaining_amount');

        $utilizationRate = $totalBudget > 0 ? ($totalSpent / $totalBudget) * 100 : 0;
        $allocationRate = $totalBudget > 0 ? ($totalAllocated / $totalBudget) * 100 : 0;

        return [
            'total_budget' => $totalBudget,
            'total_allocated' => $totalAllocated,
            'total_spent' => $totalSpent,
            'total_remaining' => $totalRemaining,
            'utilization_rate' => round($utilizationRate, 2),
            'allocation_rate' => round($allocationRate, 2),
            'active_budgets_count' => $budgets->count(),
        ];
    }
    
    /**
     * Get utilization data for gauge chart
     */
    private function getUtilizationData($budgets)
    {
        $totalSpent = $budgets->sum('spent_amount');
        $totalRemaining = $budgets->sum('remaining_amount');

        return [
            'labels' => ['Spent', 'Remaining'],
            'values' => [$totalSpent, $totalRemaining],
            'colors' => ['#dc3545', '#28a745'],
        ];
    }
    
    /**
     * Get department spending data for pie chart
     */
    private function getDepartmentSpendingData()
    {
        // Get spending by department from budget allocations
        $departmentSpending = \App\Models\BudgetAllocation::whereNotNull('department_id')
            ->whereHas('budget', function($q) {
                $q->whereIn('status', ['active', 'approved']);
            })
            ->join('departments', 'budget_allocations.department_id', '=', 'departments.id')
            ->select('departments.title as department', DB::raw('SUM(budget_allocations.spent_amount) as total_spent'))
            ->groupBy('departments.id', 'departments.title')
            ->having('total_spent', '>', 0)
            ->orderBy('total_spent', 'desc')
            ->limit(10)
            ->get();
        
        // If no department-level allocations, try budget-level
        if ($departmentSpending->isEmpty()) {
            $departmentSpending = Budget::where('budgets.status', 'active')
                ->whereNotNull('budgets.department_id')
                ->where('budgets.spent_amount', '>', 0)
                ->join('departments', 'budgets.department_id', '=', 'departments.id')
                ->select('departments.title as department', DB::raw('SUM(budgets.spent_amount) as total_spent'))
                ->groupBy('departments.id', 'departments.title')
                ->orderBy('total_spent', 'desc')
                ->limit(10)
                ->get();
        }
        
        return [
            'labels' => $departmentSpending->pluck('department')->toArray(),
            'values' => $departmentSpending->pluck('total_spent')->toArray(),
        ];
    }
    
    /**
     * Get monthly spending trend for line chart
     */
    private function getMonthlyTrendData()
    {
        $currentYear = Carbon::now()->year;
        
        $monthlySpending = Expense::whereYear('date', $currentYear)
            ->where('approval_status', 'approved')
            ->whereNotNull('budget_id')
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');
        
        // Fill in all 12 months
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $values = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $values[] = $monthlySpending->get($i)->total ?? 0;
        }
        
        return [
            'labels' => $months,
            'values' => $values,
        ];
    }
    
    /**
     * Get category spending data for bar chart
     */
    private function getCategorySpendingData()
    {
        $categorySpending = BudgetAllocation::whereHas('budget', function($query) {
                $query->where('status', 'active');
            })
            ->join('expense_categories', 'budget_allocations.expense_category_id', '=', 'expense_categories.id')
            ->select(
                'expense_categories.title as category',
                DB::raw('SUM(budget_allocations.spent_amount) as total_spent'),
                DB::raw('SUM(budget_allocations.allocated_amount) as total_allocated')
            )
            ->groupBy('expense_categories.id', 'expense_categories.title')
            ->orderBy('total_spent', 'desc')
            ->limit(10)
            ->get();
        
        return [
            'labels' => $categorySpending->pluck('category')->toArray(),
            'allocated' => $categorySpending->pluck('total_allocated')->toArray(),
            'spent' => $categorySpending->pluck('total_spent')->toArray(),
        ];
    }
    
    /**
     * Get budget alerts (over-budget, near-limit, etc.)
     */
    private function getBudgetAlerts($budgets)
    {
        $alerts = [];
        
        foreach ($budgets as $budget) {
            $utilizationRate = $budget->total_amount > 0 ? ($budget->spent_amount / $budget->total_amount) * 100 : 0;
            
            // Critical: Over 100% spent
            if ($utilizationRate > 100) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'ti-alert',
                    'title' => 'Budget Exceeded',
                    'message' => "Budget '{$budget->title}' has exceeded its limit by " . 
                                number_format($budget->spent_amount - $budget->total_amount, 2),
                    'budget_id' => $budget->id,
                ];
            }
            // Warning: Over 90% spent
            elseif ($utilizationRate > 90) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'ti-alert',
                    'title' => 'Budget Alert',
                    'message' => "Budget '{$budget->title}' is at " . round($utilizationRate, 1) . "% utilization",
                    'budget_id' => $budget->id,
                ];
            }
            // Info: Over 75% spent
            elseif ($utilizationRate > 75) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'ti-info',
                    'title' => 'Budget Notice',
                    'message' => "Budget '{$budget->title}' is at " . round($utilizationRate, 1) . "% utilization",
                    'budget_id' => $budget->id,
                ];
            }
            
            // Check for allocations that are over-spent
            foreach ($budget->allocations as $allocation) {
                if ($allocation->spent_amount > $allocation->allocated_amount) {
                    $alerts[] = [
                        'type' => 'danger',
                        'icon' => 'ti-alert',
                        'title' => 'Allocation Exceeded',
                        'message' => "Allocation '{$allocation->title}' in budget '{$budget->title}' has been exceeded by " . 
                                    number_format($allocation->spent_amount - $allocation->allocated_amount, 2),
                        'budget_id' => $budget->id,
                    ];
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get budget summary for AJAX requests
     */
    public function getBudgetSummary($id)
    {
        $budget = Budget::with(['allocations.expenseCategory'])->findOrFail($id);
        
        $summary = [
            'title' => $budget->title,
            'code' => $budget->budget_code,
            'status' => $budget->status,
            'total_amount' => $budget->total_amount,
            'allocated_amount' => $budget->allocated_amount,
            'spent_amount' => $budget->spent_amount,
            'remaining_amount' => $budget->remaining_amount,
            'utilization_rate' => $budget->total_amount > 0 ? ($budget->spent_amount / $budget->total_amount) * 100 : 0,
            'allocations' => $budget->allocations->map(function($allocation) {
                return [
                    'category' => $allocation->expenseCategory->title,
                    'allocated' => $allocation->allocated_amount,
                    'spent' => $allocation->spent_amount,
                    'remaining' => $allocation->remaining_amount,
                ];
            }),
        ];
        
        return response()->json($summary);
    }
}
