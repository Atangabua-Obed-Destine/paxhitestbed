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
use App\Models\TaxGroup;
use App\Models\Department;
use App\Models\Payroll;
use App\Services\PayrollAccountingService;
use App\Services\PayrollTaxBreakdownService;
use App\Services\PayrollCalculator;
use App\Services\PayrollWriter;
use App\Services\PayrollPayer;
use App\Services\PayslipService;
use App\Services\PayrollBankFile;
use App\Models\PayrollSetting;
use App\Models\StaffLoan;
use App\Models\TransactionMapping;
use App\Models\JournalEntry;
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


        $this->middleware('permission:'.$this->access.'-action', ['only' => ['index','generate','preview','store','pay','unpay','bulk','bankFile','emailPayslip']]);
        $this->middleware('permission:'.$this->access.'-approve', ['only' => ['approve']]);
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
        $data['settings'] = PayrollSetting::current();


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

            $data['payrolls'] = $payrolls->with('approver')->orderBy('id', 'asc')->get();
        }


        return view($this->view.'.index', $data);
    }

    /**
     * The payroll screen for one staff member and month.
     *
     * Its figures come from PayrollCalculator, the same class that saving
     * uses, so what the page shows on arrival is what would be saved.
     */
    public function generate($id, $month, $year)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['selected_month'] = $month;
        $data['selected_year'] = $year;

        $user = $data['row'] = User::with('exemptTaxes')->where('id', $id)->where('status', '1')->firstOrFail();

        $data['payroll'] = $payroll = Payroll::with('details')->where('user_id', $id)
                        ->whereYear('salary_month', $year)
                        ->whereMonth('salary_month', $month)
                        ->first();

        // A paid payroll is history; it can only be looked at on its payslip.
        if (isset($payroll) && $payroll->status == 1) {
            return redirect()->back();
        }

        // This staff member's attendance only. The query used to load every
        // staff member's attendance for the month and filter in the view.
        $attendanceModel = $user->salary_type == 2 ? StaffHourlyAttendance::class : StaffAttendance::class;
        $data['attendances'] = $attendanceModel::where('user_id', $user->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $data['total_days'] = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        $salaryMonth = Carbon::createFromDate($year, $month, 1)->format('Y-m-d');

        // Start from what was saved last time, if this month was generated
        // before; otherwise from last month's lines (when the school carries
        // them over) and the instalments of any open loans.
        if ($payroll) {
            $allowances = $payroll->details->where('status', 1)->map(fn ($d) => ['title' => $d->title, 'amount' => $d->amount])->values()->all();
            $deductions = $payroll->details->where('status', 0)->map(fn ($d) => ['title' => $d->title, 'amount' => $d->amount, 'loan_id' => $d->staff_loan_id])->values()->all();
            $data['carried_from'] = null;

            // A loan given after this payroll was generated: its instalment
            // is due this month but not yet on the payroll.
            $onPayroll = $payroll->details->pluck('staff_loan_id')->filter()->all();
            foreach (StaffLoan::dueLines($user->id, $salaryMonth, $payroll->id) as $line) {
                if (!in_array($line['loan_id'], $onPayroll)) {
                    $deductions[] = $line;
                }
            }
        } else {
            $start = app(PayrollWriter::class)->startingLines($user, $salaryMonth);
            $allowances = $start['allowances'];
            $deductions = $start['deductions'];
            $data['carried_from'] = $start['from'];
        }

        $data['calc'] = app(PayrollCalculator::class)->calculate($user, $salaryMonth, $allowances, $deductions, $payroll);
        $data['start_allowances'] = $allowances;
        $data['start_deductions'] = $deductions;
        $data['open_loans'] = StaffLoan::where('user_id', $user->id)->where('status', 'active')->get()
            ->filter(fn ($loan) => $loan->available($payroll?->id) > 0)
            ->values();

        $data['tax_groups'] = TaxGroup::getEffectiveGroups(Carbon::parse($salaryMonth));
        $data['allowance_types'] = \App\Models\AllowanceType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['deduction_types'] = \App\Models\DeductionType::where('status', '1')->orderBy('title', 'asc')->get();

        return view($this->view.'.generate', $data);
    }

    /**
     * The figures for a payroll as its lines are being typed.
     *
     * The screen calls this instead of working the tax out in JavaScript, so
     * there is one tax engine, on the server, and the page cannot show a
     * figure that saving would then refuse.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'salary_month' => 'required|date',
        ]);

        $user = User::where('id', $request->user_id)->where('status', '1')->firstOrFail();
        $existing = Payroll::where('user_id', $user->id)->where('salary_month', Carbon::parse($request->salary_month)->format('Y-m-d'))->first();

        $calc = app(PayrollCalculator::class)->calculate(
            $user,
            $request->salary_month,
            $this->postedLines($request, 'allowance_titles', 'allowances'),
            $this->postedLines($request, 'deduction_titles', 'deductions', 'deduction_loans'),
            $existing
        );

        return response()->json($calc);
    }

    /**
     * Save a payroll.
     *
     * Every figure is worked out here by PayrollCalculator from the staff
     * record, the attendance, the allowance and deduction lines and the rates
     * in force. The figures the page posts are only compared with that: if
     * any disagrees the payroll is refused rather than quietly overridden, so
     * what the screen showed and what the books hold cannot drift apart. The
     * usual cause is a page left open while the rates or the staff record
     * changed underneath it, which is exactly the case worth stopping.
     *
     * This used to store the posted gross, allowance total, earning and basic
     * salary as they arrived and recheck only the tax, so a form edited in
     * the browser could pay somebody 500,000 more, or declare less tax on the
     * same pay, and be saved.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'salary_month' => 'required|date',
            'allowance_titles' => 'nullable|array',
            'allowances' => 'nullable|array',
            'deduction_titles' => 'nullable|array',
            'deductions' => 'nullable|array',
            'basic_salary' => 'nullable|numeric',
            'total_earning' => 'nullable|numeric',
            'total_allowance' => 'nullable|numeric',
            'total_deduction' => 'nullable|numeric',
            'gross_salary' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'employer_tax' => 'nullable|numeric',
            'net_salary' => 'nullable|numeric',
        ]);

        $user = User::where('id', $request->user_id)->where('status', '1')->firstOrFail();
        $salaryMonth = Carbon::parse($request->salary_month)->startOfMonth()->format('Y-m-d');

        $existing = Payroll::where('user_id', $user->id)->where('salary_month', $salaryMonth)->first();

        if ($existing && $existing->status == 1) {
            Flasher::addError(__('This payroll has been paid and can no longer be edited. Reverse the payment first.'), __('msg_error'));

            return redirect()->back();
        }

        $calc = app(PayrollCalculator::class)->calculate(
            $user,
            $salaryMonth,
            $this->postedLines($request, 'allowance_titles', 'allowances'),
            $this->postedLines($request, 'deduction_titles', 'deductions', 'deduction_loans'),
            $existing
        );

        if (!empty($calc['problems'])) {
            Flasher::addError(__('This payroll was not saved:') . ' ' . implode(' ', $calc['problems']), __('msg_error'));

            return redirect()->back()->withInput();
        }

        // What the page showed against what the server works out. Only
        // figures the page actually sent are compared.
        $shown = [
            'basic_salary' => __('field_basic_salary'),
            'total_earning' => __('field_total_earning'),
            'total_allowance' => __('field_total_allowance'),
            'total_deduction' => __('field_total_deduction'),
            'gross_salary' => __('field_gross_salary'),
            'tax' => __('field_tax'),
            'employer_tax' => __('field_employer_tax'),
            'net_salary' => __('field_net_salary'),
        ];

        $disagree = [];
        foreach ($shown as $field => $label) {
            if ($request->filled($field) && !$this->agrees((float) $request->input($field), (float) $calc[$field])) {
                $disagree[] = __(':label: the page showed :shown, the correct figure is :computed', [
                    'label' => $label,
                    'shown' => number_format((float) $request->input($field), 2),
                    'computed' => number_format((float) $calc[$field], 2),
                ]);
            }
        }

        if ($disagree) {
            Flasher::addError(__('This payroll was not saved because the figures on the page do not match the staff record and the configured rates. Reload the page and generate the payroll again.') . ' ' . implode('; ', $disagree) . '.', __('msg_error'));

            return redirect()->back()->withInput();
        }

        try {
            app(PayrollWriter::class)->save($user, $salaryMonth, $calc, Auth::guard('web')->user()->id);

            Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

            return redirect()->route($this->route.'.index', [
                'salary_type' => $user->salary_type,
                'month' => Carbon::parse($salaryMonth)->format('m'),
                'year' => Carbon::parse($salaryMonth)->format('Y'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Payroll could not be generated', [
                'user' => $request->user_id,
                'month' => $request->salary_month,
                'error' => $e->getMessage(),
            ]);

            Flasher::addError(
                $e instanceof \RuntimeException ? $e->getMessage() : __('msg_created_error'),
                __('msg_error')
            );

            return redirect()->back()->withInput();
        }
    }

    /**
     * An allowance or deduction list as the form posts it: two parallel
     * arrays, titles and amounts.
     *
     * @return array<int, array{title:mixed, amount:mixed}>
     */
    protected function postedLines(Request $request, string $titles, string $amounts, ?string $loans = null): array
    {
        $titleList = (array) $request->input($titles, []);
        $loanList = $loans ? (array) $request->input($loans, []) : [];

        return collect((array) $request->input($amounts, []))
            ->map(fn ($amount, $key) => [
                'title' => $titleList[$key] ?? '',
                'amount' => $amount,
                'loan_id' => $loanList[$key] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Close enough to be the same figure.
     *
     * The page rounds to the franc; the server keeps two decimals. The
     * tolerance covers that and nothing more: a genuine difference in rates,
     * salary or lines moves a figure by far more.
     */
    protected function agrees(float $posted, float $computed, float $tolerance = 2.0): bool
    {
        return abs($posted - $computed) <= $tolerance;
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
        $request->validate([
            'pay_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|in:1,2,3,4,5',
            'bank_account_id' => 'required|exists:staff_bank_accounts,id',
            'note' => 'nullable|string|max:255',
        ]);

        $payroll = Payroll::findOrFail($id);

        try {
            $result = app(PayrollPayer::class)->pay($payroll, $request->only('pay_date', 'payment_method', 'bank_account_id', 'note'), Auth::guard('web')->user()->id);
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->back();
        } catch (\Throwable $e) {
            Log::error('PayrollController::pay error: ' . $e->getMessage());
            Flasher::addError(__('msg_error_occurred') . ': ' . $e->getMessage(), __('msg_error'));

            return redirect()->back();
        }

        $this->reportPayment($result);

        return redirect()->back();
    }

    /** Tell the screen how a payment went: posted or not, emailed or not. */
    protected function reportPayment(array $result): void
    {
        if ($result['journal']) {
            Flasher::addSuccess(__('The payroll was paid and posted to the accounts.'), __('msg_success'));
        } else {
            Flasher::addSuccess(__('The payroll was paid.'), __('msg_success'));
            Flasher::addInfo(__('No journal entry was created: check the payroll account mapping, the fiscal year and the open accounting period for the pay date.'), __('msg_info'));
        }

        if ($result['emailed'] === true) {
            Flasher::addInfo(__('The payslip was emailed to the staff member.'), __('msg_info'));
        } elseif ($result['emailed'] === false) {
            Flasher::addWarning(__('The payslip was not emailed: :why', ['why' => $result['email_error']]), __('msg_warning'));
        }
    }

    /**
     * Reverse a payment.
     *
     * The ledger reversal is part of the unpay, not an extra: if the salary
     * expense was posted and cannot be reversed, the payroll stays paid. This
     * used to log a warning and mark the payroll unpaid anyway, which left the
     * books carrying an expense for a payroll the screens said was not paid.
     */
    public function unpay(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        // Checked before any transaction is opened. This branch used to return
        // from inside one, leaving it open on the connection.
        if ($payroll->status == '0') {
            Flasher::addWarning(__('Payroll is already unpaid'), __('msg_warning'));

            return redirect()->back();
        }

        DB::beginTransaction();

        try {
            // Is there a live ledger entry for this payment? A payroll paid
            // before the accounting integration has none, and that is fine.
            $mapping = TransactionMapping::where('transaction_type', 'payroll')
                ->where('transaction_id', $payroll->id)
                ->first();
            $posted = $mapping && $mapping->journal_entry_id && $mapping->status !== 'reversed'
                && JournalEntry::whereKey($mapping->journal_entry_id)->exists();

            $reversal = $this->payrollAccountingService->reversePayrollJournalEntry(
                $payroll,
                Auth::guard('web')->user()->id
            );

            if ($posted && !$reversal) {
                throw new \RuntimeException(__('The payment could not be reversed in the ledger, so the payroll is still paid. Check that the current accounting period is open, then try again.'));
            }

            // An unpaid payroll withheld nothing, so it owes nothing: leaving
            // the lines would keep tax showing as due to DGI and CNPS.
            app(PayrollTaxBreakdownService::class)->clear($payroll);

            $payroll->pay_date = null;
            $payroll->payment_method = null;
            $payroll->status = '0';
            $payroll->updated_by = Auth::guard('web')->user()->id;
            $payroll->save();

            $transaction = new Transaction;
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $payroll->net_salary;
            $transaction->type = '1'; // Credit / reversal
            $transaction->created_by = Auth::guard('web')->user()->id;
            $payroll->user->transactions()->save($transaction);

            DB::commit();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->back();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payroll unpay error: ' . $e->getMessage(), ['payroll' => $payroll->id]);
            Flasher::addError(
                $e instanceof \RuntimeException ? $e->getMessage() : __('An error occurred while unpaying the payroll'),
                __('msg_error')
            );

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
     * The payslip.
     *
     * It prints the taxes this payroll was actually charged, from its own
     * itemised lines. It used to recompute them in the view from the rates in
     * force, which left out the dependent taxes (the Additional Council Tax)
     * so the lines did not add up to the tax withheld, and would have changed
     * an old payslip whenever a rate changed.
     */
    public function print($id)
    {
        $data = app(PayslipService::class)->data(Payroll::findOrFail($id));
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;

        abort_unless($data['print'], 404);

        return view($this->view.'.print', $data);
    }

    /**
     * Approve a generated payroll, when the school requires approval before
     * paying. Approval is cleared again whenever the payroll is regenerated.
     */
    public function approve($id)
    {
        $payroll = Payroll::findOrFail($id);

        if ((int) $payroll->status === 1) {
            Flasher::addWarning(__('This payroll has already been paid.'), __('msg_warning'));
        } elseif ($payroll->isApproved()) {
            Flasher::addInfo(__('This payroll is already approved.'), __('msg_info'));
        } else {
            $payroll->forceFill(['approved_by' => Auth::guard('web')->user()->id, 'approved_at' => now()])->save();
            Flasher::addSuccess(__('The payroll was approved.'), __('msg_success'));
        }

        return redirect()->back();
    }

    /**
     * Generate, approve or pay several payrolls from the list at once.
     *
     * Each staff member goes through exactly what the single screens do, one
     * at a time and each in its own transaction, so one refusal (a missing
     * account, a deduction bigger than the pay) leaves the others done and is
     * reported by name.
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'action' => 'required|in:generate,approve,pay',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000|max:2100',
            'users' => 'required|array|min:1',
            'users.*' => 'integer',
            'pay_date' => 'required_if:action,pay|nullable|date|before_or_equal:today',
            'payment_method' => 'required_if:action,pay|nullable|in:1,2,3,4,5',
            'note' => 'nullable|string|max:255',
        ], [
            'users.required' => __('Tick at least one staff member first.'),
        ]);

        if ($request->action === 'approve' && !Auth::guard('web')->user()->can('payroll-approve')) {
            abort(403);
        }

        $by = Auth::guard('web')->user()->id;
        $salaryMonth = Carbon::createFromDate($request->year, $request->month, 1)->format('Y-m-d');
        $users = User::whereIn('id', $request->users)->where('status', '1')->orderBy('staff_id')->get();

        $done = [];
        $skipped = [];
        $notes = [];
        $name = fn ($u) => trim($u->first_name . ' ' . $u->last_name) . ' (' . $u->staff_id . ')';

        foreach ($users as $user) {
            $payroll = Payroll::where('user_id', $user->id)->where('salary_month', $salaryMonth)->first();

            try {
                if ($request->action === 'generate') {
                    if ($payroll) {
                        $skipped[] = $name($user) . ': ' . ($payroll->status == 1 ? __('already paid') : __('already generated'));
                        continue;
                    }
                    $result = app(PayrollWriter::class)->generateFromStartingLines($user, $salaryMonth, $by);
                    if (!$result['payroll']) {
                        $skipped[] = $name($user) . ': ' . implode(' ', $result['calc']['problems']);
                        continue;
                    }
                    $done[] = $name($user);
                } elseif ($request->action === 'approve') {
                    if (!$payroll || $payroll->status == 1) {
                        $skipped[] = $name($user) . ': ' . (!$payroll ? __('not generated') : __('already paid'));
                        continue;
                    }
                    if (!$payroll->isApproved()) {
                        $payroll->forceFill(['approved_by' => $by, 'approved_at' => now()])->save();
                    }
                    $done[] = $name($user);
                } else {
                    if (!$payroll) {
                        $skipped[] = $name($user) . ': ' . __('not generated');
                        continue;
                    }
                    if ($payroll->status == 1) {
                        $skipped[] = $name($user) . ': ' . __('already paid');
                        continue;
                    }
                    $account = \App\Models\StaffBankAccount::where('user_id', $user->id)->where('status', 1)
                        ->orderByDesc('is_default')->orderBy('id')->first();
                    if (!$account) {
                        $skipped[] = $name($user) . ': ' . __('no payment account on the staff record');
                        continue;
                    }
                    $result = app(PayrollPayer::class)->pay($payroll, [
                        'pay_date' => $request->pay_date,
                        'payment_method' => $request->payment_method,
                        'bank_account_id' => $account->id,
                        'note' => $request->note,
                    ], $by);
                    $done[] = $name($user);
                    if (!$result['journal']) {
                        $notes['journal'] = __('Some payments were not posted to the accounts: check the payroll account mapping, the fiscal year and the open accounting period.');
                    }
                    if ($result['emailed'] === false) {
                        $notes[] = $name($user) . ': ' . __('payslip not emailed') . ' (' . $result['email_error'] . ')';
                    }
                }
            } catch (\RuntimeException $e) {
                $skipped[] = $name($user) . ': ' . $e->getMessage();
            } catch (\Throwable $e) {
                Log::error('Bulk payroll ' . $request->action . ' failed', ['user' => $user->id, 'error' => $e->getMessage()]);
                $skipped[] = $name($user) . ': ' . __('an unexpected error occurred');
            }
        }

        $verb = ['generate' => __('generated'), 'approve' => __('approved'), 'pay' => __('paid')][$request->action];

        if ($done) {
            Flasher::addSuccess(trans_choice('{1} 1 payroll :verb.|[2,*] :count payrolls :verb.', count($done), ['count' => count($done), 'verb' => $verb]), __('msg_success'));
        }
        if ($skipped) {
            Flasher::addWarning(trans_choice('{1} 1 staff member skipped.|[2,*] :count staff members skipped.', count($skipped), ['count' => count($skipped)]), __('msg_warning'));
        }

        return redirect()->back()->with('payroll_bulk_result', [
            'action' => $request->action,
            'verb' => $verb,
            'done' => $done,
            'skipped' => $skipped,
            'notes' => array_values($notes),
        ]);
    }

    /**
     * The bank file for a month, in the layout set on the Payroll Settings
     * page: the payrolls not yet paid (to send before paying), those paid, or
     * both, optionally only the staff ticked on the list.
     */
    public function bankFile(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000|max:2100',
            'which' => 'required|in:unpaid,paid,all',
            'salary_type' => 'nullable|in:1,2',
            'users' => 'nullable|array',
            'users.*' => 'integer',
        ]);

        $payrolls = Payroll::with('user', 'bankAccount')
            ->whereYear('salary_month', $request->year)
            ->whereMonth('salary_month', $request->month)
            ->when($request->salary_type, fn ($q) => $q->where('salary_type', $request->salary_type))
            ->when($request->which === 'unpaid', fn ($q) => $q->where('status', 0))
            ->when($request->which === 'paid', fn ($q) => $q->where('status', 1))
            ->when(!empty($request->users), fn ($q) => $q->whereIn('user_id', $request->users))
            ->whereHas('user', fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['Super Admin', 'Admin'])))
            ->get()
            ->sortBy(fn ($p) => $p->user->staff_id ?? '')
            ->values();

        // With approval required, an unpaid payroll is only ready once approved.
        if (PayrollSetting::current()->require_approval && $request->which !== 'paid') {
            $payrolls = $payrolls->filter(fn ($p) => $p->status == 1 || $p->isApproved())->values();
        }

        $file = app(PayrollBankFile::class);
        $built = $file->build($payrolls);

        if (empty($built['rows'])) {
            Flasher::addWarning(__('There is nothing to put in a bank file for that month.') . ($built['missing'] ? ' ' . __('No payment account: :names.', ['names' => implode(', ', $built['missing'])]) : ''), __('msg_warning'));

            return redirect()->back();
        }

        $name = 'salaries-' . sprintf('%04d-%02d', $request->year, $request->month) . '-' . $request->which . '.csv';

        return response($file->csv($built['rows']), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'X-Payroll-Rows' => (string) count($built['rows']),
            'X-Payroll-Total' => (string) $built['total'],
            'X-Payroll-Missing' => rawurlencode(implode('; ', $built['missing'])),
        ]);
    }

    /** Send a paid payroll's payslip to the staff member now. */
    public function emailPayslip($id)
    {
        $result = app(PayslipService::class)->email(Payroll::with('user')->findOrFail($id));

        if ($result === true) {
            Flasher::addSuccess(__('The payslip was emailed to the staff member.'), __('msg_success'));
        } else {
            Flasher::addError($result, __('msg_error'));
        }

        return redirect()->back();
    }
}
