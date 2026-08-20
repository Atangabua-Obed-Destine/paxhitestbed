<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetRevision;
use App\Models\ExpenseCategory;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Flasher\Laravel\Facade\Flasher;

class BudgetController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:budget-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:budget-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:budget-edit', ['only' => ['edit', 'update', 'revise']]);
        $this->middleware('permission:budget-delete', ['only' => ['destroy']]);
        $this->middleware('permission:budget-approve', ['only' => ['approve']]);
        $this->middleware('permission:budget-activate', ['only' => ['activate']]);
        $this->middleware('permission:budget-close', ['only' => ['close']]);
        $this->middleware('permission:budget-cancel', ['only' => ['cancel']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data['title'] = __('text_budgets');
        
        // Every budget, annual and departmental alike — they are the same kind
        // of thing at different scopes, and hiding the annual one made the
        // register look empty while an annual budget existed. The editing
        // screens are guarded instead, in redirectInstitutional().
        $query = Budget::query();
        
        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year', $request->fiscal_year);
        }
        
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        
        $data['rows'] = $query->with(['department', 'createdBy'])
            ->orderBy('id', 'desc')
            ->paginate(25);
        
        $data['departments'] = Department::where('status', 1)->orderBy('title')->get();
        $data['fiscal_years'] = Budget::select('fiscal_year')
            ->distinct()
            ->orderBy('fiscal_year', 'desc')
            ->pluck('fiscal_year');
        
        return view('admin.budget.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data['title'] = __('text_create_budget');
        $data['departments'] = Department::where('status', 1)->orderBy('title')->get();
        
        // Annual budgets a sub-budget can be delegated out of.
        $data['annualBudgets'] = Budget::where('is_institutional', true)
            ->whereIn('status', ['draft', 'pending_approval', 'approved', 'active'])
            ->orderByDesc('start_date')->get();

        return view('admin.budget.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:191',
            'type' => 'required|in:annual,departmental,project',
            'fiscal_year' => 'required|max:10',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'total_amount' => 'required|numeric|min:0',
            'department_id' => 'required_if:type,departmental',
            // Optional: which annual budget this one is delegated out of.
            'parent_id' => 'nullable|exists:budgets,id',
        ]);

        DB::beginTransaction();
        try {
            $budget = new Budget();
            $budget->fill($request->all());
            $budget->status = 'draft';
            $budget->created_by = auth()->user()->id;
            $budget->save();

            DB::commit();

            Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));
            return redirect()->route('admin.budget.show', $budget->id);
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_error_occurred'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data['title'] = __('text_budget_details');
        $data['row'] = Budget::with([
            'department', 'allocations.expenseCategory', 'allocations.department',
            'allocations.budgetLine', 'expenses', 'revisions',
            'children.department', 'parent',
        ])->findOrFail($id);

        return view('admin.budget.show', $data);
    }

    /**
     * Send an institutional budget back to its own editor.
     *
     * The departmental screens know nothing about budget lines, the opening
     * balance or income, so an annual sheet edited through them would lose the
     * structure it depends on — and an allocation created there would carry no
     * budget_line_id, putting a figure on no line of the sheet at all.
     *
     * Enforced here rather than by hiding buttons: a hidden button does not
     * stop someone reaching the URL.
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function redirectInstitutional($budget)
    {
        if (!$budget || !$budget->is_institutional) {
            return null;
        }

        Flasher::addWarning(
            __('This is the annual Income & Expenditure sheet. It is edited on its own screen.'),
            __('msg_warning')
        );

        return redirect()->route('admin.budget-sheet.show', $budget->id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $data['title'] = __('text_edit_budget');
        $data['row'] = Budget::findOrFail($id);

        if ($redirect = $this->redirectInstitutional($data['row'])) {
            return $redirect;
        }

        // Only allow editing draft budgets
        if (!in_array($data['row']->status, ['draft', 'pending_approval'])) {
            Flasher::addError(__('msg_cannot_edit_active_budget'), __('msg_error'));
            return redirect()->route('admin.budget.show', $id);
        }
        
        $data['departments'] = Department::where('status', 1)->orderBy('title')->get();
        
        // Annual budgets a sub-budget can be delegated out of.
        $data['annualBudgets'] = Budget::where('is_institutional', true)
            ->whereIn('status', ['draft', 'pending_approval', 'approved', 'active'])
            ->orderByDesc('start_date')->get();

        return view('admin.budget.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $budget = Budget::findOrFail($id);

        if ($redirect = $this->redirectInstitutional($budget)) {
            return $redirect;
        }

        // Only allow editing draft budgets
        if (!in_array($budget->status, ['draft', 'pending_approval'])) {
            Flasher::addError(__('msg_cannot_edit_active_budget'), __('msg_error'));
            return redirect()->route('admin.budget.show', $id);
        }

        $request->validate([
            'title' => 'required|max:191',
            'type' => 'required|in:annual,departmental,project',
            'fiscal_year' => 'required|max:10',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'total_amount' => 'required|numeric|min:0',
            'department_id' => 'required_if:type,departmental',
            // Optional: which annual budget this one is delegated out of.
            'parent_id' => 'nullable|exists:budgets,id',
        ]);

        DB::beginTransaction();
        try {
            $budget->fill($request->all());
            $budget->updated_by = auth()->user()->id;
            $budget->save();

            DB::commit();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.budget.show', $id);
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_error_occurred'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $budget = Budget::findOrFail($id);
        
        // Only allow deleting draft budgets
        if ($budget->status != 'draft') {
            Flasher::addError(__('msg_cannot_delete_active_budget'), __('msg_error'));
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            // Delete allocations first
            $budget->allocations()->delete();
            
            // Delete the budget
            $budget->delete();

            DB::commit();

            Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));
            return redirect()->route('admin.budget.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_error_occurred'), __('msg_error'));
            return redirect()->back();
        }
    }

    /**
     * Submit budget for approval
     */
    public function submitForApproval($id)
    {
        $budget = Budget::findOrFail($id);
        
        if ($budget->status != 'draft') {
            Flasher::addError(__('msg_invalid_budget_status'), __('msg_error'));
            return redirect()->back();
        }

        $budget->status = 'pending_approval';
        $budget->save();

        Flasher::addSuccess(__('msg_budget_submitted_for_approval'), __('msg_success'));
        return redirect()->route('admin.budget.show', $id);
    }

    /**
     * Approve budget
     */
    public function approve($id)
    {
        $budget = Budget::findOrFail($id);
        
        if ($budget->status != 'pending_approval') {
            Flasher::addError(__('msg_invalid_budget_status'), __('msg_error'));
            return redirect()->back();
        }

        DB::beginTransaction();
        try {
            $budget->status = 'approved';
            $budget->approved_by = auth()->user()->id;
            $budget->approved_at = now();
            $budget->save();

            DB::commit();

            Flasher::addSuccess(__('msg_budget_approved'), __('msg_success'));
            return redirect()->route('admin.budget.show', $id);
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_error_occurred'), __('msg_error'));
            return redirect()->back();
        }
    }

    /**
     * Activate budget
     */
    public function activate($id)
    {
        $budget = Budget::findOrFail($id);
        
        if ($budget->status != 'approved') {
            Flasher::addError(__('msg_invalid_budget_status'), __('msg_error'));
            return redirect()->back();
        }

        $budget->status = 'active';
        $budget->save();

        Flasher::addSuccess(__('msg_budget_activated'), __('msg_success'));
        return redirect()->route('admin.budget.show', $id);
    }

    /**
     * Close budget
     */
    public function close($id)
    {
        $budget = Budget::findOrFail($id);
        
        if ($budget->status != 'active') {
            Flasher::addError(__('msg_invalid_budget_status'), __('msg_error'));
            return redirect()->back();
        }

        $budget->status = 'closed';
        $budget->save();

        Flasher::addSuccess(__('msg_budget_closed'), __('msg_success'));
        return redirect()->route('admin.budget.show', $id);
    }

    /**
     * Cancel budget
     */
    public function cancel($id)
    {
        $budget = Budget::findOrFail($id);
        
        if (!in_array($budget->status, ['draft', 'pending_approval', 'approved'])) {
            Flasher::addError(__('msg_invalid_budget_status'), __('msg_error'));
            return redirect()->back();
        }

        $budget->status = 'cancelled';
        $budget->save();

        Flasher::addSuccess(__('msg_budget_cancelled'), __('msg_success'));
        return redirect()->route('admin.budget.show', $id);
    }
    
    /**
     * Revise an approved/active budget's total amount (logs a BudgetRevision).
     * This is the supported way to adjust a budget that is otherwise locked.
     */
    public function revise(Request $request, $id)
    {
        $budget = Budget::findOrFail($id);

        // Revisions only make sense for budgets that are otherwise locked from editing
        if (!in_array($budget->status, ['approved', 'active'])) {
            Flasher::addError(__('msg_invalid_budget_status'), __('msg_error'));
            return redirect()->back();
        }

        $request->validate([
            'new_amount' => 'required|numeric|min:0',
            'reason'     => 'required|string|max:1000',
        ]);

        $newAmount = (float) $request->new_amount;

        // Cannot revise the total below what is already allocated or already spent
        if ($newAmount < (float) $budget->allocated_amount) {
            Flasher::addError('New total (' . number_format($newAmount, 2) . ') cannot be below the allocated amount (' . number_format($budget->allocated_amount, 2) . ').', __('msg_error'));
            return redirect()->back()->withInput();
        }
        if ($newAmount < (float) $budget->spent_amount) {
            Flasher::addError('New total (' . number_format($newAmount, 2) . ') cannot be below the spent amount (' . number_format($budget->spent_amount, 2) . ').', __('msg_error'));
            return redirect()->back()->withInput();
        }
        if ($newAmount == (float) $budget->total_amount) {
            Flasher::addError('New total is unchanged.', __('msg_error'));
            return redirect()->back()->withInput();
        }

        DB::beginTransaction();
        try {
            $revision = BudgetRevision::create([
                'budget_id'       => $budget->id,
                'previous_amount' => $budget->total_amount,
                'new_amount'      => $newAmount,
                'reason'          => $request->reason,
                'status'          => 'pending',
                'requested_by'    => auth()->user()->id,
            ]);

            // Apply immediately: the model's approve() updates total + recomputes remaining
            $revision->approve(auth()->user()->id);

            DB::commit();
            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.budget.show', $id);
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_error_occurred'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Get budget allocations data for AJAX requests (used by expense form)
     */
    public function getAllocationsData($id)
    {
        $budget = Budget::findOrFail($id);
        $allocations = $budget->allocations()->with('expenseCategory')->get();
        
        return response()->json($allocations->map(function($allocation) {
            return [
                'id' => $allocation->id,
                'expense_category_id' => $allocation->expense_category_id,
                'category_title' => $allocation->expenseCategory->title,
                'allocated_amount' => $allocation->allocated_amount,
                'spent_amount' => $allocation->spent_amount,
                'remaining_amount' => $allocation->remaining_amount,
                'remaining_formatted' => number_format($allocation->remaining_amount, 2),
            ];
        }));
    }
}
