<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PaymentMethod;
use App\Support\TransactionWindow;
use Illuminate\Support\Facades\Auth;
use App\Services\Accounting\BudgetLineChoices;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Flasher\Laravel\Facade\Flasher;
use App\Models\IncomeCategory;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Income;
use Carbon\Carbon;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;

class IncomeController extends Controller
{
    use FileUploader;

    /** Which side of the Income & Expenditure sheet this screen feeds. */
    protected const MAPPING_TYPE = BudgetLineChoices::INCOME;

    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_income', 1);
        $this->route = 'admin.income';
        $this->view = 'admin.income';
        $this->path = 'income';
        $this->access = 'income';


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
        $data['categories'] = IncomeCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        // when() rather than two bare whereDate calls, because "All time"
        // resolves to no dates at all and a null bound matches nothing.
        $rows = Income::when($start_date, fn ($q) => $q->whereDate('date', '>=', $start_date))
                    ->when($end_date, fn ($q) => $q->whereDate('date', '<=', $end_date));
                    if(!empty($request->title) || $request->title != null){
                        $rows->where('title', 'LIKE', '%'.$title.'%');
                    }
                    if(!empty($request->category) || $request->category != null){
                        $rows->where('category_id', $category);
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
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);

        // A category that reaches no line of the sheet is refused outright:
        // the figure would be recorded and then reported nowhere.
        if ($refusal = $this->refuseUnfiledCategory($request)) {
            return $refusal;
        }


        try {
            DB::beginTransaction();

            // Insert Data
            $income = new Income;
            $income->category_id = $request->category;
            $income->title = $request->title;
            $income->invoice_id = $request->invoice_id;
            $income->amount = $request->amount;
            $income->date = $request->date;
            $income->reference = $request->reference;
            $income->note = $request->note;
            $income->payment_method = $request->payment_method;
            $income->payment_account_id = $request->payment_account_id;
            $income->attach = $this->uploadMedia($request, 'attach', $this->path);
            $income->created_by = Auth::guard('web')->user()->id;
            $income->save();

            // Create Payment Account Transaction if account is selected
            if ($request->payment_account_id) {
                $payment_account = PaymentAccount::findOrFail($request->payment_account_id);
                
                // Calculate new balance
                $new_balance = $payment_account->current_balance + $request->amount;
                
                // Create transaction record
                $account_transaction = new PaymentAccountTransaction;
                $account_transaction->payment_account_id = $request->payment_account_id;
                $account_transaction->transaction_type = 'credit'; // Money coming IN
                $account_transaction->amount = $request->amount;
                $account_transaction->transaction_date = $request->date;
                $account_transaction->title = 'Income - ' . $request->title;
                $account_transaction->description = 'Income: ' . ($income->category->title ?? '') . ' - ' . ($request->invoice_id ?? '');
                $account_transaction->payment_method = $request->payment_method;
                $account_transaction->reference_type = 'income';
                $account_transaction->reference_id = $income->id;
                $account_transaction->balance_after = $new_balance;
                $account_transaction->created_by = Auth::guard('web')->user()->id;
                $account_transaction->save();
                
                // Update account balance
                $payment_account->current_balance = $new_balance;
                $payment_account->save();
            }

            DB::commit();

            Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

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

            Log::error('Income could not be recorded [' . $reference . ']: ' . $e->getMessage(), [
                'reference' => $reference,
                'user' => Auth::guard('web')->id(),
                'input' => $request->except(['_token', 'attach']),
                'exception' => $e,
            ]);

            Flasher::addError(__('msg_created_error') . ' (' . $reference . ')', __('msg_error'));

            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Income $income)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['row'] = $income;

        return view($this->view.'.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Income $income)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['row'] = $income;

        // The category already on this record stays selectable even if it is
        // no longer one this rule allows, so the record can still be edited.
        $data = array_merge($data, (new BudgetLineChoices())->formData(
            self::MAPPING_TYPE,
            $income->category_id
        ));
        $data['bl_selected'] = old('category', $income->category_id);

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Income $income)
    {
        // Field Validation
        $request->validate([
            'category' => 'required',
            'title' => 'required',
            'amount' => 'required|numeric',
            'date' => 'required|date|before_or_equal:today',
            'payment_method' => PaymentMethod::rule(),
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
        ]);

        // The category already on the record stays allowed, so a record saved
        // before this rule existed can still be corrected.
        if ($refusal = $this->refuseUnfiledCategory($request, $income->getOriginal('category_id'))) {
            return $refusal;
        }


        // Update Data
        $income->category_id = $request->category;
        $income->title = $request->title;
        $income->invoice_id = $request->invoice_id;
        $income->amount = $request->amount;
        $income->date = $request->date;
        $income->reference = $request->reference;
        $income->note = $request->note;
        $income->payment_method = $request->payment_method;
        $income->attach = $this->updateMedia($request, 'attach', $this->path, $income);
        $income->updated_by = Auth::guard('web')->user()->id;
        $income->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

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
    public function destroy(Income $income)
    {
        // Delete Data
        $this->deleteMedia($this->path, $income);

        $income->delete();

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
