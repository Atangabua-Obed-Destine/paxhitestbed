<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
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
        $data['categories'] = IncomeCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        $rows = Income::whereDate('date', '>=', $start_date)
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

        $data['categories'] = IncomeCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

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
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);


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

        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_created_error'), __('msg_error'));
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
        $data['categories'] = IncomeCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

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
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
        ]);


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

        return redirect()->back();
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
}
