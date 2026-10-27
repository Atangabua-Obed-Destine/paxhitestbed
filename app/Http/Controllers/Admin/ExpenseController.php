<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PaymentMethod;
use App\Support\TransactionWindow;
use Illuminate\Support\Facades\Auth;
use App\Services\Accounting\BudgetLineChoices;
use App\Services\Accounting\TransactionReport;
use App\Services\LetterheadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    /** Which side of the Income & Expenditure sheet this screen feeds. */
    protected const MAPPING_TYPE = BudgetLineChoices::EXPENSE;

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

        // One definition of the filter, shared with the printed report, so the
        // document and the screen it was printed from cannot disagree.
        $filter = app(TransactionReport::class)->filter($request);
        $data['selected_category'] = $category = $filter['categories'];

        /*
         * The window the screen opens on.
         *
         * It used to be the last twelve months counted back from today, which
         * belongs to no set of accounts: it straddles two exercices, so the
         * screen and the reports were drawn from different periods, and it
         * silently hid everything older. TransactionWindow answers with the
         * exercice the books are actually keeping, and leaves an explicit
         * start_date/end_date alone so a saved link still shows what it did.
         */
        $window = TransactionWindow::resolve(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->boolean('all')
        );

        $data['selected_start_date'] = $start_date = $window['start'];
        $data['selected_end_date'] = $end_date = $window['end'];
        $data['window'] = $window;


        // Search Filter
        $data['categories'] = ExpenseCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        // when() rather than two bare whereDate calls, because "All time"
        // resolves to no dates at all and a null bound matches nothing.
        $rows = Expense::when($start_date, fn ($q) => $q->whereDate('date', '>=', $start_date))
                    ->when($end_date, fn ($q) => $q->whereDate('date', '<=', $end_date));
                    if(!empty($request->title) || $request->title != null){
                        $rows->where('title', 'LIKE', '%'.$title.'%');
                    }
                    if ($category !== []) {
                        $rows->whereIn('category_id', $category);
                    }
        $data['rows'] = $rows->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        // Bucketed from the rows already fetched, so the band above the table
        // costs no extra query and can never disagree with the table below it.
        $data['summary'] = $this->summarise($data['rows']);

        return view($this->view.'.index', $data);
    }

    /**
     * The band above the table: how much, over how many rows, and how it moved.
     *
     * Taken from the rows the page already has rather than from its own query,
     * for two reasons. It costs nothing, and more importantly a separate query
     * could drift from the filters the table was built with — a total that
     * disagrees with the rows under it is worse than no total at all.
     *
     * @param  \Illuminate\Support\Collection  $rows
     * @return array{count:int, total:float, methods:array<int, array{label:string, count:int, total:float}>}
     */
    /**
     * The filtered list as a document, under the letterhead.
     *
     * Built from the records, not from the page the browser happens to be
     * showing. The export buttons on the table hand their rows to the browser
     * to turn into a file, which is why what came out was whatever DataTables
     * had rendered; this reads the same filter the screen read and prints
     * every row it selects.
     */
    public function report(Request $request)
    {
        $service = app(TransactionReport::class);
        $filter = $service->filter($request);
        $rows = $service->rows(TransactionReport::EXPENSE, $filter);

        $letterhead = app(LetterheadService::class);

        $pdf = Pdf::loadView('admin.account.report-pdf', [
            'title' => $this->title,
            'path' => $this->path,
            'report' => $service->build(TransactionReport::EXPENSE, $filter, $rows),
            'cover' => $service->describe(TransactionReport::EXPENSE, $filter),
            'filtered' => $service->isFiltered($filter),
            'accent' => '#eb6834',
            'accentDark' => '#9a3b12',
            'generatedAt' => now(),
            'generatedBy' => optional(Auth::user())->name ?? __('the system'),
            'letterhead' => $letterhead->render(true),
            'letterheadStyles' => $letterhead->styles(),
        ])
            ->setPaper('a4', 'portrait')
            // Nothing in this document needs the network: the letterhead's
            // images are resolved to filesystem paths before they get here,
            // and a renderer that will not fetch is one that cannot be made
            // to fetch something else.
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->download(
            Str::slug($this->title . ' ' . ($filter['window']['start'] ?? 'all') . ' ' . ($filter['window']['end'] ?? '')) . '.pdf'
        );
    }

    protected function summarise($rows): array
    {
        $methods = [];

        foreach ($rows as $row) {
            $key = $row->payment_method === null ? 0 : (int) $row->payment_method;

            if (!isset($methods[$key])) {
                $methods[$key] = [
                    'label' => $key === 0 ? __('acc_not_recorded') : PaymentMethod::label($key),
                    'count' => 0,
                    'total' => 0.0,
                ];
            }

            $methods[$key]['count']++;
            $methods[$key]['total'] += (float) $row->amount;
        }

        // Biggest first: what the money mostly came through is the thing worth
        // reading, not whichever method happens to be numbered lowest.
        uasort($methods, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'count' => $rows->count(),
            'total' => (float) $rows->sum('amount'),
            'methods' => $methods,
        ];
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
        // Carried so the form knows which side of the sheet it is on; without
        // it the shared theme fell back to the income accent.
        $data['path'] = $this->path;

        // Headings and the categories filed under them, so a figure cannot be
        // recorded against a category that reaches no line of the sheet.
        $data = array_merge($data, (new BudgetLineChoices())->formData(self::MAPPING_TYPE));
        $data['bl_selected'] = old('category');

        // No budget or allocation here. An expense reaches the Income and
        // Expenditure sheet through its category's budget line, which the two
        // dropdowns above now settle, so the screen asked twice for where the
        // money belongs. Nothing was using the answer either: of 558 expenses
        // none carries a budget_id or a budget_allocation_id, and no budget is
        // active and non-institutional, so the dropdown was always empty.
        // The edit screen still offers them, and store() still accepts them.

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
            'payment_method' => PaymentMethod::rule(),
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
            'budget_id' => 'nullable|exists:budgets,id',
            'budget_allocation_id' => 'nullable|exists:budget_allocations,id',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);

        // A category that reaches no line of the sheet is refused outright:
        // the figure would be recorded and then reported nowhere.
        if ($refusal = $this->refuseUnfiledCategory($request)) {
            return $refusal;
        }

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

        } catch (\Throwable $e) {
            DB::rollBack();

            /*
             * Throwable, not Exception.
             *
             * A null property read or a type error is an \Error, which an
             * Exception catch does not take — so those escaped as a white 500
             * page with the transaction left to Laravel to unwind. They are
             * the same failure to the Bursar and belong in the same log line.
             *
             * Say what went wrong, somewhere.
             *
             * This caught the exception and threw it away: the Bursar saw
             * "Created Fail!" and the log held nothing at all, so the one
             * screen that records money could fail and leave no way to find
             * out why. The reference goes on screen so a report of "it failed
             * at 12:46" can be matched to the entry in the log.
             */
            $reference = (string) Str::uuid();

            Log::error('Expense could not be recorded [' . $reference . ']: ' . $e->getMessage(), [
                'reference' => $reference,
                'user' => Auth::guard('web')->id(),
                'input' => $request->except(['_token', 'attach']),
                'exception' => $e,
            ]);

            \Flasher::addError(__('msg_created_error') . ' (' . $reference . ')', __('msg_error'));

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

        // The category already on this record stays selectable even if it is
        // no longer one this rule allows, so the record can still be edited.
        $data = array_merge($data, (new BudgetLineChoices())->formData(
            self::MAPPING_TYPE,
            $expense->category_id
        ));
        $data['bl_selected'] = old('category', $expense->category_id);
        
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
            'payment_method' => PaymentMethod::rule(),
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
            'budget_id' => 'nullable|exists:budgets,id',
            'budget_allocation_id' => 'nullable|exists:budget_allocations,id',
        ]);

        // The category already on the record stays allowed, so a record saved
        // before this rule existed can still be corrected.
        if ($refusal = $this->refuseUnfiledCategory($request, $expense->getOriginal('category_id'))) {
            return $refusal;
        }

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

        /*
         * Back to the list, not back to the form.
         *
         * redirect()->back() returned to the edit screen the save had been
         * made from, so the page came back looking exactly as it went: same
         * form, same values, nothing to say the save had happened. The toast
         * was there — it just landed on a page that looked unchanged, which
         * reads as nothing having happened at all.
         *
         * The list is where the record can actually be seen, and it is where
         * store() has always gone after recording one.
         */
        return redirect()->route($this->route.'.index');
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

    /**
     * Refuse a category that reaches no line of the Income & Expenditure
     * sheet.
     *
     * The dropdown only offers filed categories, but a request can name any
     * id, so this is where the rule actually lives. $original is the category
     * already on the record: it stays allowed so a record saved before this
     * rule existed can still be corrected, which on this installation covers
     * four income records carrying 20,254,000.
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function refuseUnfiledCategory(Request $request, $original = null)
    {
        $chosen = $request->category;

        if ($original !== null && (string) $chosen === (string) $original) {
            return null;
        }

        $choices = new BudgetLineChoices();

        if ($choices->isUsable(self::MAPPING_TYPE, $chosen)) {
            return null;
        }

        $reason = $choices->unlinked(self::MAPPING_TYPE)->firstWhere('id', (int) $chosen);

        Flasher::addError(
            $reason
                ? __('That category is not filed under a budget line, so money recorded against it would appear on no line of the Income & Expenditure sheet. :reason File it under a line first.', ['reason' => $reason['reason']])
                : __('Choose a category that is filed under a budget line.'),
            __('msg_error')
        );

        return redirect()->back()->withInput();
    }
}
