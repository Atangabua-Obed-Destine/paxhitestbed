<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\ExpenseCategory;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Flasher\Laravel\Facade\Flasher;

class BudgetAllocationController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:budget-view', ['only' => ['index']]);
        $this->middleware('permission:budget-allocation-create', ['only' => ['store']]);
        $this->middleware('permission:budget-allocation-edit', ['only' => ['update']]);
        $this->middleware('permission:budget-allocation-delete', ['only' => ['destroy']]);
    }

    /**
     * Show allocation form for a budget
     */
    public function index($budgetId)
    {
        $data['title'] = __('text_budget_allocations');
        $data['budget'] = Budget::with(['allocations.expenseCategory', 'allocations.department'])->findOrFail($budgetId);
        $data['expenseCategories'] = ExpenseCategory::where('status', 1)->orderBy('title')->get();
        $data['departments'] = Department::where('status', 1)->orderBy('title')->get();
        
        return view('admin.budget.allocations', $data);
    }

    /**
     * Store allocation
     */
    public function store(Request $request, $budgetId)
    {
        $budget = Budget::findOrFail($budgetId);
        
        // Can't allocate to active or closed budgets
        if (in_array($budget->status, ['active', 'closed', 'cancelled'])) {
            Flasher::addError(__('msg_cannot_modify_budget'), __('msg_error'));
            return redirect()->back();
        }

        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'title' => 'required|max:191',
            'allocated_amount' => 'required|numeric|min:0',
            'period' => 'required|in:yearly,q1,q2,q3,q4,semester1,semester2',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Validate that allocation doesn't exceed unallocated amount
        $unallocatedAmount = $budget->total_amount - $budget->allocated_amount;
        if ($request->allocated_amount > $unallocatedAmount) {
            Flasher::addError('Allocation amount (' . number_format($request->allocated_amount, 2) . ') exceeds unallocated budget (' . number_format($unallocatedAmount, 2) . ')', __('msg_error'));
            return redirect()->back()->withInput();
        }

        DB::beginTransaction();
        try {
            $allocation = new BudgetAllocation();
            $allocation->budget_id = $budgetId;
            $allocation->fill($request->all());
            $allocation->created_by = auth()->user()->id;
            $allocation->save();

            // Update budget allocated amount
            $budget->calculateAllocatedAmount();

            DB::commit();

            Flasher::addSuccess(__('msg_allocation_created_successfully'), __('msg_success'));
            return redirect()->route('admin.budget.allocations', $budgetId);
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_error_occurred'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Update allocation
     */
    public function update(Request $request, $budgetId, $id)
    {
        $budget = Budget::findOrFail($budgetId);
        $allocation = BudgetAllocation::where('budget_id', $budgetId)->findOrFail($id);
        
        // Can't modify active or closed budgets
        if (in_array($budget->status, ['active', 'closed', 'cancelled'])) {
            Flasher::addError(__('msg_cannot_modify_budget'), __('msg_error'));
            return redirect()->back();
        }

        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'title' => 'required|max:191',
            'allocated_amount' => 'required|numeric|min:0',
            'period' => 'required|in:yearly,q1,q2,q3,q4,semester1,semester2',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Validate that new allocation doesn't exceed unallocated amount
        // Calculate unallocated amount excluding current allocation
        $otherAllocationsTotal = $budget->allocations()->where('id', '!=', $id)->sum('allocated_amount');
        $unallocatedAmount = $budget->total_amount - $otherAllocationsTotal;
        
        if ($request->allocated_amount > $unallocatedAmount) {
            Flasher::addError('Allocation amount (' . number_format($request->allocated_amount, 2) . ') exceeds available budget (' . number_format($unallocatedAmount, 2) . ')', __('msg_error'));
            return redirect()->back()->withInput();
        }

        DB::beginTransaction();
        try {
            $allocation->fill($request->all());
            $allocation->updated_by = auth()->user()->id;
            $allocation->save();

            // Update budget allocated amount
            $budget->calculateAllocatedAmount();

            DB::commit();

            Flasher::addSuccess(__('msg_allocation_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.budget.allocations', $budgetId);
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_error_occurred'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Delete allocation
     */
    public function destroy($budgetId, $id)
    {
        $budget = Budget::findOrFail($budgetId);
        $allocation = BudgetAllocation::where('budget_id', $budgetId)->findOrFail($id);
        
        // Can't delete from active or closed budgets
        if (in_array($budget->status, ['active', 'closed', 'cancelled'])) {
            Flasher::addError(__('msg_cannot_modify_budget'), __('msg_error'));
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            $allocation->delete();

            // Update budget allocated amount
            $budget->calculateAllocatedAmount();

            DB::commit();

            Flasher::addSuccess(__('msg_allocation_deleted_successfully'), __('msg_success'));
            return redirect()->route('admin.budget.allocations', $budgetId);
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_error_occurred'), __('msg_error'));
            return redirect()->back();
        }
    }
}
