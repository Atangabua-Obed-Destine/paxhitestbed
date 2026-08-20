<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Flasher\Laravel\Facade\Flasher;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Expense;
use Carbon\Carbon;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;

class ExpenseController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_expense', 1);
        $this->route = 'admin.expense';
        $this->view = 'admin.expense';
        $this->path = 'expense';
        $this->access = 'expense';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        if(!empty($request->title) || $request->title != null){
            $data['selected_title'] = $title = $request->title;
        }
        else{
            $data['selected_title'] = $title = null;
        }

        if(!empty($request->category) || $request->category != null){
            $data['selected_category'] = $category = $request->category;
        }
        else{
            $data['selected_category'] = $category = '0';
        }

        if(!empty($request->start_date) || $request->start_date != null){
            $data['selected_start_date'] = $start_date = $request->start_date;
        }
        else{
            $data['selected_start_date'] = $start_date = date('Y-m-d', strtotime(Carbon::now()->subYear()));
        }

        if(!empty($request->end_date) || $request->end_date != null){
            $data['selected_end_date'] = $end_date = $request->end_date;
        }
        else{
            $data['selected_end_date'] = $end_date = date('Y-m-d', strtotime(Carbon::today()));
        }


        // Search Filter
        $data['categories'] = ExpenseCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        $rows = Expense::whereDate('date', '>=', $start_date)
                    ->whereDate('date', '<=', $end_date);
                    if(!empty($request->title) || $request->title != null){
                        $rows->where('title', 'LIKE', '%'.$title.'%');
                    }
                    if(!empty($request->category) || $request->category != null){
                        $rows->where('category_id', $category);
                    }
        $data['rows'] = $rows->orderBy('id', 'desc')->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;

        $data['categories'] = ExpenseCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();
        
        // Get active budgets for dropdown
        // Departmental budgets only. The Income and Expenditure sheet reports
        // spending through its category mappings, so charging an expense
        // directly against it would count that spending twice.
        $data['activeBudgets'] = \App\Models\Budget::whereIn('status', ['active', 'approved'])
                            ->where('is_institutional', false)
                            ->orderBy('created_at', 'desc')->get();

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'category' => 'required',
            'title' => 'required',
            'amount' => 'required|numeric',
            'date' => 'required|date|before_or_equal:today',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
            'budget_id' => 'nullable|exists:budgets,id',
            'budget_allocation_id' => 'nullable|exists:budget_allocations,id',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);

        // Validate budget allocation if provided
        if ($request->filled('budget_allocation_id')) {
            $allocation = \App\Models\BudgetAllocation::findOrFail($request->budget_allocation_id);
            
            if ($request->amount > $allocation->remaining_amount) {
                \Flasher::addError(
                    __('expense_exceeds_allocation') . ': ' . 
                    number_format($allocation->remaining_amount, 2)
                );
                return redirect()->back()->withInput();
            }
        }

        try {
            DB::beginTransaction();

            // Insert Data
            $expense = new Expense;
            $expense->category_id = $request->category;
            $expense->title = $request->title;
            $expense->invoice_id = $request->invoice_id;
            $expense->amount = $request->amount;
            $expense->date = $request->date;
            $expense->reference = $request->reference;
            $expense->note = $request->note;
            $expense->payment_method = $request->payment_method;
            $expense->payment_account_id = $request->payment_account_id;
            $expense->budget_id = $request->budget_id;
            $expense->budget_allocation_id = $request->budget_allocation_id;
            $expense->approval_status = 'pending'; // Default status
            $expense->attach = $this->uploadMedia($request, 'attach', $this->path);
            $expense->created_by = Auth::guard('web')->user()->id;
            $expense->save();

            // Recompute budget figures from the source of truth (the expense rows).
            // BudgetAllocation::updateSpentAmount() cascades to its parent budget; we also
            // recompute the budget directly for budget-level expenses with no allocation.
            if ($request->filled('budget_allocation_id')) {
                \App\Models\BudgetAllocation::find($request->budget_allocation_id)?->updateSpentAmount();
            }
            if ($request->filled('budget_id')) {
                \App\Models\Budget::find($request->budget_id)?->updateSpentAmount();
            }

            // Create Payment Account Transaction if account is selected
            if ($request->payment_account_id) {
                $payment_account = PaymentAccount::findOrFail($request->payment_account_id);
                
                // Check if account has sufficient balance
                if ($payment_account->current_balance < $request->amount) {
                    DB::rollBack();
                    \Flasher::addError(__('insufficient_balance_in_account'));
                    return redirect()->back()->withInput();
                }
                
                // Calculate new balance
                $new_balance = $payment_account->current_balance - $request->amount;
                
                // Create transaction record
                $account_transaction = new PaymentAccountTransaction;
                $account_transaction->payment_account_id = $request->payment_account_id;
                $account_transaction->transaction_type = 'debit'; // Money going OUT
                $account_transaction->amount = $request->amount;
                $account_transaction->transaction_date = $request->date;
                $account_transaction->title = 'Expense - ' . $request->title;
                $account_transaction->description = 'Expense: ' . ($expense->category->title ?? '') . ' - ' . ($request->invoice_id ?? '');
                $account_transaction->payment_method = $request->payment_method;
                $account_transaction->reference_type = 'expense';
                $account_transaction->reference_id = $expense->id;
                $account_transaction->balance_after = $new_balance;
                $account_transaction->created_by = Auth::guard('web')->user()->id;
                $account_transaction->save();
                
                // Update account balance
                $payment_account->current_balance = $new_balance;
                $payment_account->save();
            }

            DB::commit();

            \Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

            return redirect()->route($this->route.'.index');

        } catch (\Exception $e) {
            DB::rollBack();
            \Flasher::addError(__('msg_created_error'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Expense $expense)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['row'] = $expense;

        return view($this->view.'.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Expense $expense)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['row'] = $expense;
        $data['categories'] = ExpenseCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();
        
        // Get active budgets for dropdown
        // Departmental budgets only. The Income and Expenditure sheet reports
        // spending through its category mappings, so charging an expense
        // directly against it would count that spending twice.
        $data['activeBudgets'] = \App\Models\Budget::whereIn('status', ['active', 'approved'])
                            ->where('is_institutional', false)
                            ->orderBy('created_at', 'desc')->get();

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Expense $expense)
    {
        // Field Validation
        $request->validate([
            'category' => 'required',
            'title' => 'required',
            'amount' => 'required|numeric',
            'date' => 'required|date|before_or_equal:today',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
            'budget_id' => 'nullable|exists:budgets,id',
            'budget_allocation_id' => 'nullable|exists:budget_allocations,id',
        ]);

        // Validate budget allocation if provided
        if ($request->filled('budget_allocation_id')) {
            $allocation = \App\Models\BudgetAllocation::findOrFail($request->budget_allocation_id);
            
            // Calculate available remaining (add back old expense amount if it was from this allocation)
            $availableRemaining = $allocation->remaining_amount;
            if ($expense->budget_allocation_id == $request->budget_allocation_id) {
                $availableRemaining += $expense->amount;
            }
            
            if ($request->amount > $availableRemaining) {
                \Flasher::addError(
                    __('expense_exceeds_allocation') . ': ' . 
                    number_format($availableRemaining, 2)
                );
                return redirect()->back()->withInput();
            }
        }

        // Store old links for budget recompute (before reassigning)
        $oldAllocationId = $expense->budget_allocation_id;
        $oldBudgetId = $expense->budget_id;

        // Update Data
        $expense->category_id = $request->category;
        $expense->title = $request->title;
        $expense->invoice_id = $request->invoice_id;
        $expense->amount = $request->amount;
        $expense->date = $request->date;
        $expense->reference = $request->reference;
        $expense->note = $request->note;
        $expense->payment_method = $request->payment_method;
        $expense->budget_id = $request->budget_id;
        $expense->budget_allocation_id = $request->budget_allocation_id;
        $expense->attach = $this->updateMedia($request, 'attach', $this->path, $expense);
        $expense->updated_by = Auth::guard('web')->user()->id;
        $expense->save();

        // Recompute every affected allocation and budget (old + new) from the source of
        // truth (expense rows). Idempotent — handles amount changes and allocation/budget
        // re-linking without drift or double-counting.
        foreach (array_unique(array_filter([$oldAllocationId, $request->budget_allocation_id])) as $allocId) {
            \App\Models\BudgetAllocation::find($allocId)?->updateSpentAmount();
        }
        foreach (array_unique(array_filter([$oldBudgetId, $request->budget_id])) as $budgetId) {
            \App\Models\Budget::find($budgetId)?->updateSpentAmount();
        }


        \Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Expense $expense)
    {
        // Capture links, delete, then recompute from the remaining expense rows
        $allocationId = $expense->budget_allocation_id;
        $budgetId = $expense->budget_id;

        $this->deleteMedia($this->path, $expense);
        $expense->delete();

        if ($allocationId) {
            \App\Models\BudgetAllocation::find($allocationId)?->updateSpentAmount();
        }
        if ($budgetId) {
            \App\Models\Budget::find($budgetId)?->updateSpentAmount();
        }

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
