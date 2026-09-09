<?php

namespace App\Http\Controllers\Admin;

use App\Models\StaffHourlyAttendance;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use App\Models\StaffAttendance;
use App\Models\WorkShiftType;
use App\Models\PayrollDetail;
use Illuminate\Http\Request;
use App\Models\PrintSetting;
use App\Models\Transaction;
use App\Models\Designation;
use Illuminate\Support\Str;
use App\Models\TaxSetting;
use App\Models\TaxGroup;
use App\Models\Department;
use App\Models\Payroll;
use App\Services\PayrollAccountingService;
use App\Services\PayrollTaxBreakdownService;
use Carbon\Carbon;
use App\User;

class PayrollController extends Controller
{
    protected $title, $route, $view, $path, $access;
    protected $payrollAccountingService;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(PayrollAccountingService $payrollAccountingService)
    {
        // Inject the payroll accounting service
        $this->payrollAccountingService = $payrollAccountingService;
        
        // Module Data
        $this->title = trans_choice('module_payroll', 1);
        $this->route = 'admin.payroll';
        $this->view = 'admin.payroll';
        $this->path = 'payroll';
        $this->access = 'payroll';


        $this->middleware('permission:'.$this->access.'-action', ['only' => ['index','generate','store','pay','unpay']]);
        $this->middleware('permission:'.$this->access.'-view', ['only' => ['index']]);
        $this->middleware('permission:'.$this->access.'-report', ['only' => ['report']]);
        $this->middleware('permission:'.$this->access.'-print', ['only' => ['index','print']]);
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


        if(!empty($request->salary_type) || $request->salary_type != null){
            $data['selected_salary_type'] = $salary_type = $request->salary_type;
        }
        else{
            $data['selected_salary_type'] = '0';
        }

        if(!empty($request->department) || $request->department != null){
            $data['selected_department'] = $department = $request->department;
        }
        else{
            $data['selected_department'] = '0';
        }

        if(!empty($request->designation) || $request->designation != null){
            $data['selected_designation'] = $designation = $request->designation;
        }
        else{
            $data['selected_designation'] = '0';
        }

        if(!empty($request->month) || $request->month != null){
            $data['selected_month'] = $month = $request->month;
        }
        else{
            $data['selected_month'] = date("m", strtotime(Carbon::today()));
        }

        if(!empty($request->year) || $request->year != null){
            $data['selected_year'] = $year = $request->year;
        }
        else{
            $data['selected_year'] = date("Y", strtotime(Carbon::today()));
        }


        $data['departments'] = Department::where('status', '1')->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title', 'asc')->get();
        $data['print'] = PrintSetting::where('slug', 'pay-slip')->first();


        // Filter Users
        if($request->salary_type){

            $users = User::where('status', '1')
                ->whereDoesntHave('roles', function($query) {
                    $query->whereIn('name', ['Super Admin', 'Admin']);
                });

            if(!empty($request->salary_type)){
                $users->where('salary_type', $salary_type);
            }
            if(!empty($request->department)){
                $users->where('department_id', $department);
            }
            if(!empty($request->designation)){
                $users->where('designation_id', $designation);
            }
            $data['rows'] = $users->orderBy('staff_id', 'asc')->get();
        }


        // Filter Payrolls
        if(!empty($request->month) && !empty($request->year)){

            $payrolls = Payroll::whereYear('salary_month', $year)
                ->whereMonth('salary_month', $month)
                ->whereHas('user', function ($query) {
                    $query->whereDoesntHave('roles', function($q) {
                        $q->whereIn('name', ['Super Admin', 'Admin']);
                    });
                });

            if(!empty($request->salary_type)){
                $payrolls->where('salary_type', $salary_type);
            }
            if(!empty($request->department)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($department){
                    $query->where('department_id', $department);
                });
            }
            if(!empty($request->designation)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($designation){
                    $query->where('designation_id', $designation);
                });
            }

            $data['payrolls'] = $payrolls->orderBy('id', 'asc')->get();
        }


        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function generate($id, $month, $year)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;


        $data['selected_month'] = $month;
        $data['selected_year'] = $year;

        $user = $data['row'] = User::with('exemptTaxes')->where('id', $id)->where('status', '1')->firstOrFail();

        // Filter Payroll
        $data['payroll'] = $payroll = Payroll::where('user_id', $id)
                        ->whereYear('salary_month', $year)
                        ->whereMonth('salary_month', $month)
                        ->first();

        // Update Validation
        if(isset($payroll) && $payroll->status == 1){
            return redirect()->back();
        }

        // Attendances
        if($user->salary_type == 1){
        $data['attendances'] = StaffAttendance::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();
        }
        if($user->salary_type == 2){
        $data['attendances'] = StaffHourlyAttendance::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();
        }

        $data['total_days'] = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Get active and effective tax brackets for the payroll month
        $payrollDate = Carbon::createFromDate($year, $month, 1);
        
        // Get tax groups with their brackets (for progressive tax calculation)
        $data['tax_groups'] = TaxGroup::getEffectiveGroups($payrollDate);
        
        // Get standalone tax brackets (not in any group) — base taxes only
        $data['taxs'] = TaxSetting::getEffectiveBrackets($payrollDate);
        
        // Get standalone dependent tax brackets
        $data['dependent_taxs'] = TaxSetting::getEffectiveDependentBrackets($payrollDate);
        
        // Get staff tax exemptions with custom values (only non-expired)
        $data['staff_tax_exemptions'] = \App\Models\StaffTaxExemption::where('user_id', $id)
            ->notExpired($payrollDate)
            ->get()
            ->keyBy('tax_setting_id');

        // Get allowance and deduction types for dropdowns
        $data['allowance_types'] = \App\Models\AllowanceType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['deduction_types'] = \App\Models\DeductionType::where('status', '1')->orderBy('title', 'asc')->get();


        return view($this->view.'.generate', $data);
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
            'user_id' => 'required',
            'basic_salary' => 'required|numeric',
            'total_earning' => 'required|numeric',
            'total_allowance' => 'required|numeric',
            'total_deduction' => 'required|numeric',
            'gross_salary' => 'required|numeric',
            'tax' => 'required|numeric',
            'employer_tax' => 'nullable|numeric',
            'net_salary' => 'required|numeric',
            'total_cost' => 'nullable|numeric',
            'salary_month' => 'required|date',
        ]);


        // Insert Data
        try{
            DB::beginTransaction();

            // Calculate total cost if not provided
            $employerTax = $request->employer_tax ?? 0;
            $totalCost = $request->total_cost ?? ($request->net_salary + $request->tax + $employerTax);

            // Insert Or Update Data
            $payroll = Payroll::updateOrCreate(
                [
                    'user_id' => $request->user_id,
                    'salary_month' => $request->salary_month
                ],
                [
                    'user_id' => $request->user_id,
                    'basic_salary' => $request->basic_salary,
                    'salary_type' => $request->salary_type,
                    'total_earning' => $request->total_earning,
                    'total_allowance' => $request->total_allowance,
                    'bonus' => '0',
                    'total_deduction' => $request->total_deduction,
                    'gross_salary' => $request->gross_salary,
                    'tax' => $request->tax,
                    'employer_tax' => $employerTax,
                    'net_salary' => $request->net_salary,
                    'total_cost' => $totalCost,
                    'salary_month' => $request->salary_month,
                    'status' => '0',
                    'created_by' => Auth::guard('web')->user()->id
                ]
            );


            // Remove Old Details
            PayrollDetail::where('payroll_id', $payroll->id)->delete();

            // Payroll Allowances
            if(is_array($request->allowances)){
            foreach($request->allowances as $key =>$allowance){
                if($allowance != '' && $allowance != null){
                // Insert Data
                $allowance = new PayrollDetail;
                $allowance->payroll_id = $payroll->id;
                $allowance->title = $request->allowance_titles[$key];
                $allowance->amount = $request->allowances[$key];
                $allowance->status = '1';
                $allowance->save();
                }
            }}

            // Payroll Deductions
            if(is_array($request->deductions)){
            foreach($request->deductions as $key =>$deduction){
                if($deduction != '' && $deduction != null){
                // Insert Data
                $deduction = new PayrollDetail;
                $deduction->payroll_id = $payroll->id;
                $deduction->title = $request->deduction_titles[$key];
                $deduction->amount = $request->deductions[$key];
                $deduction->status = '0';
                $deduction->save();
                }
            }}

            DB::commit();


            Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

            return redirect()->route($this->route.'.index');
        }
        catch(\Exception $e){

            Flasher::addError(__('msg_created_error'), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'user_id' => 'required',
            'basic_salary' => 'required|numeric',
            'total_earning' => 'required|numeric',
            'bonus' => 'required|numeric',
            'total_deduction' => 'required|numeric',
            'gross_salary' => 'required|numeric',
            'tax' => 'required|numeric',
            'net_salary' => 'required|numeric',
            'salary_month' => 'required|date',
        ]);


        // Insert Data
        $payroll = Payroll::findOrFail($id);
        $payroll->user_id = $request->user_id;
        $payroll->basic_salary = $request->basic_salary;
        $payroll->salary_type = $request->salary_type;
        $payroll->total_earning = $request->total_earning;
        $payroll->total_allowance = '0';
        $payroll->bonus = $request->bonus;
        $payroll->total_deduction = $request->total_deduction;
        $payroll->gross_salary = $request->gross_salary;
        $payroll->tax = $request->tax;
        $payroll->net_salary = $request->net_salary;
        $payroll->salary_month = $request->salary_month;
        $payroll->note = $request->note;
        $payroll->status = '0';
        $payroll->created_by = Auth::guard('web')->user()->id;
        $payroll->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function pay(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'pay_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required',
            'bank_account_id' => 'required|exists:staff_bank_accounts,id',
        ]);

        DB::beginTransaction();
        
        try {
            // Update Data
            $payroll = Payroll::findOrFail($id);
            $payroll->pay_date = $request->pay_date;
            $payroll->payment_method = $request->payment_method;
            $payroll->bank_account_id = $request->bank_account_id;
            $payroll->note = $request->note;
            $payroll->status = '1';
            $payroll->updated_by = Auth::guard('web')->user()->id;
            $payroll->save();

            // Transaction (legacy transaction record)
            $transaction = new Transaction;
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $payroll->net_salary;
            $transaction->type = '2';
            $transaction->created_by = Auth::guard('web')->user()->id;
            $payroll->user->transactions()->save($transaction);

            // Record what each individual tax took, before the journal entry
            // is built — the entry reads these lines to decide which liability
            // account each withholding accrues to.
            //
            // A breakdown that does not add up to the payroll's own total is
            // refused rather than written (see PayrollTaxBreakdownService),
            // and the payroll still goes through: the money has moved, and a
            // missing breakdown can be backfilled while a wrong one recorded
            // as fact cannot be told from a right one.
            $breakdown = app(PayrollTaxBreakdownService::class)->record($payroll);

            if (!$breakdown['reconciled']) {
                Log::warning('PayrollController::pay — itemised taxes for payroll #' . $payroll->id
                    . ' did not reconcile to the payroll total; no breakdown recorded.');
            }

            // Create journal entry for accounting integration
            $journalEntry = $this->payrollAccountingService->createPayrollJournalEntry(
                $payroll,
                Auth::guard('web')->user()->id
            );

            DB::commit();

            if ($journalEntry) {
                Flasher::addSuccess(__('msg_paid_successfully_with_journal'), __('msg_success'));
            } else {
                Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
                Flasher::addInfo(__('msg_journal_entry_not_created'), __('msg_info'));
            }

            return redirect()->back();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayrollController::pay error: ' . $e->getMessage());
            Flasher::addError(__('msg_error_occurred') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function unpay(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            // Update Data
            $payroll = Payroll::findOrFail($id);
            
            // Check if payroll is already unpaid
            if ($payroll->status == '0') {
                Flasher::addWarning(__('Payroll is already unpaid'), __('msg_warning'));
                return redirect()->back();
            }
            
            // Reverse journal entry first (before changing status)
            try {
                $reversalEntry = $this->payrollAccountingService->reversePayrollJournalEntry(
                    $payroll, 
                    Auth::guard('web')->user()->id
                );
            } catch (\Exception $e) {
                // Log the error but continue - the original entry might not exist
                Log::warning('Could not reverse payroll journal entry: ' . $e->getMessage());
            }
            
            // An unpaid payroll withheld nothing, so it owes nothing. Leaving
            // the lines behind would keep the month showing tax due to DGI and
            // CNPS that the ledger no longer carries, and the remittance
            // screen would invite someone to pay it.
            app(PayrollTaxBreakdownService::class)->clear($payroll);

            // Update payroll status
            $payroll->pay_date = null;
            $payroll->payment_method = null;
            $payroll->status = '0';
            $payroll->updated_by = Auth::guard('web')->user()->id;
            $payroll->save();

            // Create reversal transaction
            $transaction = new Transaction;
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $payroll->net_salary;
            $transaction->type = '1'; // Type 1 = Credit/Reversal
            $transaction->created_by = Auth::guard('web')->user()->id;

            $payroll->user->transactions()->save($transaction);

            DB::commit();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->back();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payroll unpay error: ' . $e->getMessage());
            Flasher::addError(__('An error occurred while unpaying the payroll'), __('msg_error'));
            return redirect()->back();
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function report(Request $request)
    {
        //
        $data['title'] = trans_choice('module_payroll_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        if(!empty($request->salary_type) || $request->salary_type != null){
            $data['selected_salary_type'] = $salary_type = $request->salary_type;
        }
        else{
            $data['selected_salary_type'] = '0';
        }

        if(!empty($request->department) || $request->department != null){
            $data['selected_department'] = $department = $request->department;
        }
        else{
            $data['selected_department'] = '0';
        }

        if(!empty($request->designation) || $request->designation != null){
            $data['selected_designation'] = $designation = $request->designation;
        }
        else{
            $data['selected_designation'] = '0';
        }

        if(!empty($request->shift) || $request->shift != null){
            $data['selected_shift'] = $shift = $request->shift;
        }
        else{
            $data['selected_shift'] = '0';
        }

        if(!empty($request->contract_type) || $request->contract_type != null){
            $data['selected_contract'] = $contract_type = $request->contract_type;
        }
        else{
            $data['selected_contract'] = '0';
        }

        if(!empty($request->month) || $request->month != null){
            $data['selected_month'] = $month = $request->month;
        }
        else{
            $data['selected_month'] = date("m", strtotime(Carbon::today()));
        }

        if(!empty($request->year) || $request->year != null){
            $data['selected_year'] = $year = $request->year;
        }
        else{
            $data['selected_year'] = date("Y", strtotime(Carbon::today()));
        }


        $data['departments'] = Department::where('status', '1')->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title', 'asc')->get();
        $data['work_shifts'] = WorkShiftType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['print'] = PrintSetting::where('slug', 'pay-slip')->first();


        // Filter Payrolls
        if(!empty($request->month) && !empty($request->year)){

            $payrolls = Payroll::whereYear('salary_month', $year)->whereMonth('salary_month', $month);

            if(!empty($request->salary_type)){
                $payrolls->where('salary_type', $salary_type);
            }
            if(!empty($request->department)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($department){
                    $query->where('department_id', $department);
                });
            }
            if(!empty($request->designation)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($designation){
                    $query->where('designation_id', $designation);
                });
            }
            if(!empty($request->shift)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($shift){
                    $query->where('work_shift', $shift);
                });
            }
            if(!empty($request->contract_type)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($contract_type){
                    $query->where('contract_type', $contract_type);
                });
            }

            $data['rows'] = $payrolls->orderBy('id', 'asc')->get();
        }


        return view($this->view.'.report', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = 'print-setting';

        // View
        $data['print'] = PrintSetting::where('slug', 'pay-slip')->firstOrFail();
        $data['row'] = Payroll::findOrFail($id);
        
        // Get effective tax brackets for the payroll month
        $payrollDate = Carbon::parse($data['row']->salary_month);
        
        // Get tax groups with their brackets
        $data['tax_groups'] = TaxGroup::getEffectiveGroups($payrollDate);
        
        // Get standalone tax brackets — base taxes only
        $data['taxs'] = TaxSetting::getEffectiveBrackets($payrollDate);
        
        // Get standalone dependent tax brackets
        $data['dependent_taxs'] = TaxSetting::getEffectiveDependentBrackets($payrollDate);
        
        // Get staff tax exemptions with custom values (non-expired)
        $data['staff_tax_exemptions'] = \App\Models\StaffTaxExemption::where('user_id', $data['row']->user_id)
            ->notExpired($payrollDate)
            ->get()
            ->keyBy('tax_setting_id');

        return view($this->view.'.print', $data);
    }
}
