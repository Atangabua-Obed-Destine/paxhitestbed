<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Models\PrintSetting;
use App\Models\FeesCategory;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Models\Semester;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Session;
use App\Models\Program;
use App\Models\Section;
use App\Models\Fee;
use Carbon\Carbon;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentReceipt;
use App\Models\SubjectMarking;

class FeesStudentController extends Controller
{
    protected $title, $route, $view, $path, $access;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_fees_due', 1);
        $this->route = 'admin.fees-student';
        $this->view = 'admin.fees-student';
        $this->path = 'student';
        $this->access = 'fees-student';


        $this->middleware('permission:'.$this->access.'-due', ['only' => ['index']]);
        $this->middleware('permission:'.$this->access.'-quick-assign', ['only' => ['quickAssign','quickAssignStore']]);
        $this->middleware('permission:'.$this->access.'-quick-received', ['only' => ['quickReceived','quickReceivedStore']]);
        $this->middleware('permission:'.$this->access.'-action', ['only' => ['index','pay','unpay','cancel']]);
        $this->middleware('permission:'.$this->access.'-report', ['only' => ['report']]);
        $this->middleware('permission:'.$this->access.'-print', ['only' => ['report','print','multiPrint']]);
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


        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = $faculty = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = $program = '0';
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = $session = '0';
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = $semester = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = $section = '0';
        }

        if(!empty($request->category) || $request->category != null){
            $data['selected_category'] = $category = $request->category;
        }
        else{
            $data['selected_category'] = $category = '0';
        }

        if(!empty($request->student_id) || $request->student_id != null){
            $data['selected_student_id'] = $student_id = $request->student_id;
        }
        else{
            $data['selected_student_id'] = $student_id = null;
        }

        if(!empty($request->payment_status) || $request->payment_status != null){
            $data['selected_payment_status'] = $payment_status = $request->payment_status;
        }
        else{
            $data['selected_payment_status'] = $payment_status = 'all';
        }


        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['categories'] = FeesCategory::where('status', '1')->orderBy('title', 'asc')->get();
        $data['print'] = PrintSetting::where('slug', 'fees-receipt')->first();


        // Filter Search
        if(!empty($request->faculty) && $request->faculty != '0'){
        $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $sessions = Session::where('status', 1);
        $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['sessions'] = $sessions->orderBy('id', 'desc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $semesters = Semester::where('status', 1);
        $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['semesters'] = $semesters->orderBy('id', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0' && !empty($request->semester) && $request->semester != '0'){
        $sections = Section::where('status', 1);
        $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
            $query->where('program_id', $program);
            $query->where('semester_id', $semester);
        });
        $data['sections'] = $sections->orderBy('title', 'asc')->get();}



        if(isset($request->faculty) || isset($request->program) || isset($request->session) || isset($request->semester) || isset($request->section) || isset($request->category) || isset($request->student_id)){
            // Filter Fees - Include unpaid and partially paid
            $fees = Fee::with(['studentEnroll.student', 'studentEnroll.session', 'studentEnroll.semester', 'studentEnroll.program', 'category', 'approvedReceipts', 'paymentPlan']);
            
            // Payment status filter
            if($payment_status == '0'){
                $fees->where('status', 0); // Unpaid only
            } elseif($payment_status == '2'){
                $fees->where('status', 2); // Partially paid only
            } else {
                $fees->whereIn('status', [0, 2]); // All fees due (unpaid + partially paid)
            }

            if(!empty($request->faculty) || !empty($request->program) || !empty($request->session) || !empty($request->semester) || !empty($request->section)){
                $fees->whereHas('studentEnroll.program', function ($query) use ($faculty){
                    if($faculty != 0){
                    $query->where('faculty_id', $faculty);
                    }
                });

                $fees->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
                    if($program != 0){
                    $query->where('program_id', $program);
                    }
                    if($session != 0){
                    $query->where('session_id', $session);
                    }
                    if($semester != 0){
                    $query->where('semester_id', $semester);
                    }
                    if($section != 0){
                    $query->where('section_id', $section);
                    }
                });
            }
            if($category != 0){
                $fees->where('category_id', $category);
            }
            if(!empty($request->student_id)){
                $fees->whereHas('studentEnroll', function ($query) use ($student_id){
                    if($student_id != 0){
                        $query->where('matricule', 'LIKE', '%'.$student_id.'%')
                              ->orWhereHas('student', function($q) use ($student_id) {
                                  $q->where('student_id', 'LIKE', '%'.$student_id.'%');
                              });
                    }
                });
            }

            $fees->whereHas('studentEnroll.student', function ($query){
                $query->orderBy('matricule', 'asc');
            });

            $rows = $fees->orderBy('due_date', 'asc')->orderBy('id', 'desc')->get();
            $data['rows'] = $rows->sortBy(function($row){
                return $row->studentEnroll->matricule ?? $row->studentEnroll->student->student_id;
            });
            
            // Calculate statistics
            $all_due_fees = clone $fees;
            $all_due_fees = $all_due_fees->get();
            
            $data['stats'] = [
                'total_fees' => $all_due_fees->count(),
                'unpaid_count' => $all_due_fees->where('status', 0)->count(),
                'partially_paid_count' => $all_due_fees->where('status', 2)->count(),
                'total_amount_due' => $all_due_fees->sum(function($fee) {
                    return $fee->total_amount;
                }),
                // Cap per-fee paid at total_amount so overpayments (extracted as
                // StudentCredits) are not double-counted in the per-student summary.
                'total_paid' => $all_due_fees->sum(function($fee) {
                    return min((float) $fee->paid_amount, (float) $fee->total_amount);
                }),
                'total_remaining' => $all_due_fees->sum(function($fee) {
                    return max(0, (float) $fee->remaining_balance);
                }),
            ];
        }


        return view($this->view.'.index', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function pay(Request $request)
    {
        // Field Validation
        $request->validate([
            'pay_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required',
            'paid_amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);


        $fee = Fee::find($request->fee_id);

        if (!$fee) {
            Flasher::addError(__('fee_not_found'));
            return redirect()->back();
        }

        // Check if fee has an active payment plan
        if ($fee->hasActivePaymentPlan()) {
            Flasher::addError('This fee is currently under an active payment plan. Direct payment is not allowed. Please use the payment plan to make installment payments.');
            return redirect()->back();
        }

        // Resit fees require full payment - no partial payments allowed
        $fee->load('resitRequest');
        if ($fee->resitRequest) {
            $remainingBalance = $fee->remaining_balance;
            if ($request->paid_amount < $remainingBalance) {
                Flasher::addError(__('Partial payment is not allowed for resit fees. You must pay the full remaining balance.'));
                return redirect()->back();
            }
        }

        // Discount Calculation
        $discount_amount = 0;
        $today = date('Y-m-d');

        if(isset($fee->category)){
        foreach($fee->category->discounts->where('status', '1') as $discount){

        $availability = \App\Models\FeesDiscount::availability($discount->id, $fee->studentEnroll->student_id);

            if(isset($availability)){
            if($discount->start_date <= $today && $discount->end_date >= $today){
                if($discount->type == '1'){
                    $discount_amount = $discount_amount + $discount->amount;
                }
                else{
                    $discount_amount = $discount_amount + ( ($fee->fee_amount / 100) * $discount->amount);
                }
            }}
        }}


        // Fine Calculation
        $fine_amount = 0;
        if(empty($fee->pay_date) || $fee->due_date < $fee->pay_date){

            $due_date = strtotime($fee->due_date);
            $today = strtotime(date('Y-m-d'));
            $days = (int)(($today - $due_date)/86400);

            if($fee->due_date < date("Y-m-d")){
                if(isset($fee->category)){
                foreach($fee->category->fines->where('status', '1') as $fine){
                if($fine->start_day <= $days && $fine->end_day >= $days){
                    if($fine->type == '1'){
                        $fine_amount = $fine_amount + $fine->amount;
                    }
                    else{
                        $fine_amount = $fine_amount + ( ($fee->fee_amount / 100) * $fine->amount);
                    }
                }
                }}
            }
        }


        // Net Amount Calculation
        $net_amount = ($fee->fee_amount - $discount_amount) + $fine_amount;


        DB::beginTransaction();
        
        try {
            // Get current paid amount and new payment
            $current_paid = $fee->paid_amount ?? 0;
            $new_payment = $request->paid_amount;
            $total_paid = $current_paid + $new_payment;
            
            // Calculate remaining balance after this payment
            $remaining_after_payment = $net_amount - $total_paid;
            
            // Check for overpayment
            $overpayment_amount = 0;
            $allow_overpayment = $request->allow_overpayment == '1';
            
            if ($total_paid > $net_amount) {
                if (!$allow_overpayment) {
                    DB::rollBack();
                    Flasher::addError(__('overpayment_not_allowed'));
                    return redirect()->back();
                }
                
                // Calculate overpayment amount, subtracting any credits already issued from previous overpayments on this fee
                $previously_credited = \App\Models\StudentCredit::where('source_fee_id', $fee->id)
                    ->where('source_type', \App\Models\StudentCredit::SOURCE_OVERPAYMENT)
                    ->sum('original_amount');
                $overpayment_amount = ($total_paid - $net_amount) - $previously_credited;
                
                // Only create credit if there's a net new overpayment
                if ($overpayment_amount < 0) {
                    $overpayment_amount = 0;
                }
            }
            
            // Determine status based on payment
            if ($total_paid >= $net_amount) {
                $status = '1'; // Fully paid (including overpaid)
            } else {
                $status = '2'; // Partially paid
            }
            
            // Update Data - store the ACTUAL paid amount (can exceed net_amount)
            $fee->discount_amount = $discount_amount;
            $fee->fine_amount = $fine_amount;
            $fee->paid_amount = $total_paid; // Store actual total paid (including overpayment)
            $fee->pay_date = $request->pay_date;
            $fee->payment_method = $request->payment_method;
            $fee->payment_account_id = $request->payment_account_id;
            $fee->note = $request->note;
            $fee->status = $status;
            $fee->updated_by = Auth::guard('web')->user()->id;
            $fee->save();


            // Transaction (Student Ledger)
            $transaction = new Transaction;
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $new_payment; // Record the actual payment amount
            $transaction->type = '1';
            $transaction->created_by = Auth::guard('web')->user()->id;
            $fee->studentEnroll->student->transactions()->save($transaction);

            // Create Payment Receipt record for tracking in unlinked transactions report
            $paymentReceipt = PaymentReceipt::create([
                'fee_id' => $fee->id,
                'student_id' => $fee->studentEnroll->student_id,
                'payment_reference' => 'ADMIN-' . date('Ymd') . '-' . $fee->id . '-' . Str::random(4),
                'payment_date' => $request->pay_date,
                'amount' => $new_payment,
                'payment_method' => $request->payment_method,
                'verification_status' => 'approved', // Admin payments are auto-approved
                'verified_by' => Auth::guard('web')->user()->id,
                'verified_at' => now(),
                'payment_account_id' => $request->payment_account_id, // May be null - will show in unlinked if null
                'student_note' => 'Direct payment recorded by admin',
                'verification_note' => 'Admin direct payment',
            ]);

            // Create Payment Account Transaction if account is selected
            if ($request->payment_account_id) {
                $payment_account = PaymentAccount::findOrFail($request->payment_account_id);
                
                // Calculate new balance
                $new_balance = $payment_account->current_balance + $new_payment;
                
                // Create transaction record
                $account_transaction = new PaymentAccountTransaction;
                $account_transaction->payment_account_id = $request->payment_account_id;
                $account_transaction->transaction_type = 'credit'; // Money coming IN
                $account_transaction->amount = $new_payment;
                $account_transaction->transaction_date = $request->pay_date;
                $account_transaction->title = 'Fee Payment - ' . ($fee->studentEnroll->student->first_name ?? '') . ' ' . ($fee->studentEnroll->student->last_name ?? '');
                $account_transaction->description = 'Fee payment for ' . ($fee->category->title ?? 'Fee');
                $account_transaction->payment_method = $request->payment_method;
                $account_transaction->reference_type = 'fees';
                $account_transaction->reference_id = $fee->id;
                $account_transaction->balance_after = $new_balance;
                $account_transaction->created_by = Auth::guard('web')->user()->id;
                $account_transaction->save();
                
                // Update account balance
                $payment_account->current_balance = $new_balance;
                $payment_account->save();
            }
            
            // Handle overpayment - create student credit
            if ($overpayment_amount > 0) {
                $creditService = new \App\Services\StudentCreditService();
                $credit = $creditService->createFromOverpayment(
                    $fee, 
                    $overpayment_amount, 
                    Auth::guard('web')->user()->id
                );
                
                // Add note about credit created
                $fee->note = ($fee->note ? $fee->note . ' | ' : '') . 
                    "Overpayment of " . number_format($overpayment_amount, 2) . " credited (Credit ID: {$credit->id})";
                $fee->save();
                
                Flasher::addInfo(__('overpayment_credit_created', ['amount' => number_format($overpayment_amount, 2)]));
            }

            DB::commit();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->back()->with('receipt', $fee->id);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Fee payment error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            Flasher::addError(__('msg_updated_error'), __('msg_error'));
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
        try{

            DB::beginTransaction();
            // Update Data
            $fee = Fee::findOrFail($id);
            $fee->pay_date = null;
            $fee->payment_method = null;
            $fee->note = $request->note;
            $fee->status = '0';
            $fee->updated_by = Auth::guard('web')->user()->id;
            $fee->save();


            // Transaction
            $transaction = new Transaction;
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $fee->paid_amount;
            $transaction->type = '2';
            $transaction->created_by = Auth::guard('web')->user()->id;
            $fee->studentEnroll->student->transactions()->save($transaction);
            DB::commit();


            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->back();
        }
        catch(\Exception $e){

            Flasher::addError(__('msg_updated_error'), __('msg_error'));

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
    public function cancel(Request $request, $id)
    {
        // Update Data
        $fee = Fee::findOrFail($id);
        $fee->pay_date = null;
        $fee->payment_method = null;
        $fee->note = $request->note;
        $fee->status = '2';
        $fee->updated_by = Auth::guard('web')->user()->id;
        $fee->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function report(Request $request)
    {
        //
        $data['title'] = trans_choice('module_fees_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = $faculty = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = $program = '0';
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = $session = '0';
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = $semester = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = $section = '0';
        }

        // Fees type: any number of categories. None chosen (or "All") means every
        // category. A single value from an old link still works.
        $data['selected_category'] = $category = collect((array) $request->input('category', []))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if(!empty($request->student_id) || $request->student_id != null){
            $data['selected_student_id'] = $student_id = $request->student_id;
        }
        else{
            $data['selected_student_id'] = $student_id = null;
        }

        // Payment status: any number of statuses, shown if a fee matches any of
        // them. None chosen (or "all") means every status. A single value from
        // an old link still works.
        $data['selected_payment_status'] = $payment_status = collect((array) $request->input('payment_status', []))
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => in_array($value, ['0', '1', '2', '3', '4', '5'], true))
            ->unique()->values()->all();



        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['categories'] = FeesCategory::where('status', '1')->orderBy('title', 'asc')->get();
        $data['print'] = PrintSetting::where('slug', 'fees-receipt')->first();


        // Filter Search
        if(!empty($request->faculty) && $request->faculty != '0'){
        $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $sessions = Session::where('status', 1);
        $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['sessions'] = $sessions->orderBy('id', 'desc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $semesters = Semester::where('status', 1);
        $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['semesters'] = $semesters->orderBy('id', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0' && !empty($request->semester) && $request->semester != '0'){
        $sections = Section::where('status', 1);
        $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
            $query->where('program_id', $program);
            $query->where('semester_id', $semester);
        });
        $data['sections'] = $sections->orderBy('title', 'asc')->get();}


        if(isset($request->faculty) || isset($request->program) || isset($request->session) || isset($request->semester) || isset($request->section) || isset($request->category) || isset($request->student_id)){
            // Filter Fees - Include all paid and partially paid
            $fees = Fee::with(['studentEnroll.student', 'studentEnroll.session', 'studentEnroll.semester', 'studentEnroll.program', 'category', 'approvedReceipts', 'paymentPlan', 'creditApplications', 'generatedCredits', 'resitRequest.subject', 'resitRequest.session', 'resitRequest.resitSession', 'resitRequest.resitSemester'])
                ->withCreditMovedOut();

            // Paid and overpaid are judged on the net paid amount: overpayment
            // credit applied to another fee counts on that fee, not on this one
            // as well. See Fee::netPaidSql().
            $netPaid = Fee::netPaidSql('fees');
            $netDue = '(fees.fee_amount - COALESCE(fees.discount_amount, 0) + COALESCE(fees.fine_amount, 0))';

            // Payment status filter: a fee is shown if it matches any chosen status.
            $statusFilters = [
                // Unpaid
                '0' => fn ($q) => $q->where('status', 0),
                // Fully paid but NOT overpaid
                '1' => fn ($q) => $q->where('status', 1)->whereRaw("{$netPaid} <= {$netDue}"),
                // Partially paid
                '2' => fn ($q) => $q->where('status', 2),
                // Cancelled
                '3' => fn ($q) => $q->where('status', 3),
                // On an active payment plan
                '4' => fn ($q) => $q->whereNotNull('payment_plan_id')
                    ->whereHas('paymentPlan', fn ($plan) => $plan->where('status', 'active')),
                // Overpaid: still holding an excess that has not been applied
                // to another fee.
                '5' => fn ($q) => $q->whereRaw("{$netPaid} > {$netDue}"),
            ];

            if(!empty($payment_status)){
                $fees->where(function ($any) use ($payment_status, $statusFilters) {
                    foreach($payment_status as $status){
                        $any->orWhere(fn ($q) => $statusFilters[$status]($q));
                    }
                });
            } else {
                // All statuses (including status 0 = unpaid)
                $fees->whereIn('status', [0, 1, 2, 3]);
            }

            if(!empty($request->faculty) || !empty($request->program) || !empty($request->session) || !empty($request->semester) || !empty($request->section)){
                $fees->whereHas('studentEnroll.program', function ($query) use ($faculty){
                    if($faculty != 0){
                    $query->where('faculty_id', $faculty);
                    }
                });

                $fees->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
                    if($program != 0){
                    $query->where('program_id', $program);
                    }
                    if($session != 0){
                    $query->where('session_id', $session);
                    }
                    if($semester != 0){
                    $query->where('semester_id', $semester);
                    }
                    if($section != 0){
                    $query->where('section_id', $section);
                    }
                });
            }
            if(!empty($category)){
                $fees->whereIn('category_id', $category);
            }
            if(!empty($request->student_id)){
                $fees->whereHas('studentEnroll', function ($query) use ($student_id){
                    if($student_id != 0){
                        $query->where('matricule', 'LIKE', '%'.$student_id.'%')
                              ->orWhereHas('student', function($q) use ($student_id) {
                                  $q->where('student_id', 'LIKE', '%'.$student_id.'%');
                              });
                    }
                });
            }

            $fees->whereHas('studentEnroll.student', function ($query){
                $query->orderBy('matricule', 'asc');
            });

            $rows = $fees->orderBy('updated_at', 'desc')->get();
            $data['rows'] = $rows->sortBy(function($row){
                return $row->studentEnroll->matricule ?? $row->studentEnroll->student->student_id;
            });
            
            // Calculate statistics
            $all_fees = clone $fees;
            $all_fees = $all_fees->get();
            
            // Count overpaid fees
            $overpaid_count = $all_fees->filter(function($fee) {
                // Still holding an excess not applied to another fee. A First
                // Instalment whose overpayment went to the Second is not overpaid.
                return $fee->isNetOverpaid();
            })->count();
            
            // Calculate total overpayment amount
            $total_overpayment = $all_fees->sum(function($fee) {
                // Only the excess still held, not what has already been applied.
                return $fee->net_overpayment_amount;
            });
            
            // Pre-load resit markings for all resit fees in one query
            $resitFees = $rows->filter(fn($f) => $f->resitRequest !== null);
            $resitMarkings = collect();
            if ($resitFees->isNotEmpty()) {
                $markingKeys = $resitFees->map(fn($f) => [
                    'student_enroll_id' => $f->resitRequest->student_enroll_id,
                    'subject_id'        => $f->resitRequest->subject_id,
                ]);
                $resitMarkings = SubjectMarking::where(function ($q) use ($markingKeys) {
                    foreach ($markingKeys as $key) {
                        $q->orWhere(function ($sub) use ($key) {
                            $sub->where('student_enroll_id', $key['student_enroll_id'])
                                ->where('subject_id', $key['subject_id']);
                        });
                    }
                })->get()->keyBy(fn($m) => $m->student_enroll_id . '-' . $m->subject_id);
            }
            $data['resitMarkings'] = $resitMarkings;

            $data['stats'] = [
                'total_fees' => $all_fees->count(),
                // A First Instalment whose overpayment has been moved to the
                // Second is fully paid, not overpaid — it used to be left out.
                'fully_paid_count' => $all_fees->filter(function($fee) {
                    return $fee->status == 1 && !$fee->isNetOverpaid();
                })->count(),
                'partially_paid_count' => $all_fees->where('status', 2)->count(),
                'cancelled_count' => $all_fees->where('status', 3)->count(),
                'overpaid_count' => $overpaid_count,
                'total_amount' => $all_fees->sum(function($fee) {
                    return $fee->total_amount;
                }),
                // Cash actually received: overpayment credit applied to another
                // fee is counted on that fee and no longer on this one as well.
                'total_collected' => $all_fees->sum(function($fee) {
                    return $fee->net_paid_amount;
                }),
                'total_remaining' => $all_fees->sum(function($fee) {
                    return max(0, $fee->net_remaining_balance); // Only positive remaining
                }),
                'total_overpayment' => $total_overpayment,
            ];
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
        $data['title'] = trans_choice('module_fees_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = 'print-setting';

        // View - Allow printing for fully paid, partially paid, and fees with payment plans
        $data['print'] = PrintSetting::where('slug', 'fees-receipt')->firstOrFail();
        
        // Allow printing for status 1 (paid), 2 (partial), or fees with payment plans (regardless of status)
        $data['row'] = Fee::with([
                'paymentPlan.installments.payments',
                'resitRequest.subject',
                'resitRequest.session',
                'resitRequest.resitSession',
                'resitRequest.resitSemester',
                'resitRequest.studentEnroll',
                'studentEnroll.student',
                'studentEnroll.program',
                'studentEnroll.section',
            ])
            ->where('id', $id)
            ->where(function($query) {
                $query->whereIn('status', ['1', '2'])
                      ->orWhereNotNull('payment_plan_id');
            })
            ->firstOrFail();


        return view($this->view.'.print', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function multiPrint(Request $request)
    {
        //
        $data['title'] = trans_choice('module_fees_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = 'print-setting';

        $fees = explode(",",$request->fees);

        // View
        $data['print'] = PrintSetting::where('slug', 'fees-receipt')->firstOrFail();
        $data['rows'] = Fee::whereIn('id', $fees)->orderBy('id', 'asc')->get();

        return view($this->view.'.multi-print', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function quickAssign()
    {
        //
        $data['title'] = trans_choice('module_fees_quick_assign', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        $data['categories'] = FeesCategory::where('status', '1')->orderBy('title', 'asc')->get();

        // Filter Student
        $students = StudentEnroll::where('status', '1');
        $students->with('student')->whereHas('student', function ($query){
            $query->where('status', '1');
            $query->orderBy('matricule', 'asc');
        });

        $data['students'] = $students->get()->sortBy(function($enroll) {
            return $enroll->matricule ?? $enroll->student->student_id;
        });


        return view($this->view.'.quick-assign', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function quickAssignStore(Request $request)
    {
        // Field Validation
        $request->validate([
            'student' => 'required',
            'category' => 'required',
            'amount' => 'required|numeric',
            'type' => 'required|numeric',
            'assign_date' => 'required|date|after_or_equal:today',
            'due_date' => 'required|date|after_or_equal:assign_date',
        ]);


        $total_credits = 0;

        if($request->type == 1){
            $fee_amount = $request->amount;
        }
        else {
            $enroll = StudentEnroll::find($request->student);
            foreach($enroll->subjects as $subject){
                $total_credits = $total_credits + $subject->credit_hour;
            }

            $fee_amount = $total_credits * $request->amount;
        }

        // Assign Fees
        $fees = new Fee;
        $fees->student_enroll_id = $request->student;
        $fees->category_id = $request->category;
        $fees->fee_amount = $fee_amount;
        $fees->assign_date = $request->assign_date;
        $fees->due_date = $request->due_date;
        $fees->created_by = Auth::guard('web')->user()->id;
        $fees->save();


        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function quickReceived()
    {
        //
        $data['title'] = trans_choice('module_fees_quick_received', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        $data['categories'] = FeesCategory::where('status', '1')->orderBy('title', 'asc')->get();

        // Filter Student
        $students = StudentEnroll::where('status', '1');
        $students->with('student')->whereHas('student', function ($query){
            $query->where('status', '1');
            $query->orderBy('matricule', 'asc');
        });

        $data['students'] = $students->get()->sortBy(function($enroll) {
            return $enroll->matricule ?? $enroll->student->student_id;
        });


        return view($this->view.'.quick-received', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function quickReceivedStore(Request $request)
    {
        // Field Validation
        $request->validate([
            'student' => 'required',
            'category' => 'required',
            'fee_amount' => 'required|numeric',
            'discount_amount' => 'required|numeric',
            'fine_amount' => 'required|numeric',
            'paid_amount' => 'required|numeric',
            'payment_method' => 'required',
            'due_date' => 'required|date',
            'pay_date' => 'required|date|before_or_equal:today',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
        ]);


        try{
            DB::beginTransaction();
            // Insert Data
            $fee = new Fee;
            $fee->student_enroll_id = $request->student;
            $fee->category_id = $request->category;
            $fee->fee_amount = $request->fee_amount;
            $fee->discount_amount = $request->discount_amount;
            $fee->fine_amount = $request->fine_amount;
            $fee->paid_amount = $request->paid_amount;
            $fee->assign_date = Carbon::today();
            $fee->due_date = $request->due_date;
            $fee->pay_date = $request->pay_date;
            $fee->payment_method = $request->payment_method;
            $fee->payment_account_id = $request->payment_account_id;
            $fee->note = $request->note;
            $fee->status = '1';
            $fee->updated_by = Auth::guard('web')->user()->id;
            $fee->save();


            // Transaction (Student Ledger)
            $transaction = new Transaction;
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $request->paid_amount;
            $transaction->type = '1';
            $transaction->created_by = Auth::guard('web')->user()->id;
            $fee->studentEnroll->student->transactions()->save($transaction);

            // Create Payment Account Transaction if account is selected
            if ($request->payment_account_id) {
                $payment_account = PaymentAccount::findOrFail($request->payment_account_id);
                
                // Calculate new balance
                $new_balance = $payment_account->current_balance + $request->paid_amount;
                
                // Create transaction record
                $account_transaction = new PaymentAccountTransaction;
                $account_transaction->payment_account_id = $request->payment_account_id;
                $account_transaction->transaction_type = 'credit'; // Money coming IN
                $account_transaction->amount = $request->paid_amount;
                $account_transaction->transaction_date = $request->pay_date;
                $account_transaction->title = 'Fee Payment - Quick Received';
                $account_transaction->description = 'Quick fee payment';
                $account_transaction->payment_method = $request->payment_method;
                $account_transaction->reference_type = 'fees';
                $account_transaction->reference_id = $fee->id;
                $account_transaction->balance_after = $new_balance;
                $account_transaction->created_by = Auth::guard('web')->user()->id;
                $account_transaction->save();
                
                // Update account balance
                $payment_account->current_balance = $new_balance;
                $payment_account->save();
            }

            DB::commit();


            Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

            return redirect()->back();
        }
        catch(\Exception $e){

            Flasher::addError(__('msg_created_error'), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Public verification page for fee receipt (no authentication required)
     *
     * @param  string  $receipt_number
     * @return \Illuminate\Http\Response
     */
    public function verify($receipt_number)
    {
        //
        $data['title'] = __('verify_receipt');
        
        // Find receipt by ID or receipt number
        $fee = Fee::where('id', $receipt_number)
            ->orWhere(function($query) use ($receipt_number) {
                // If receipt has a specific format, handle it here
                $query->whereRaw('CONCAT(?, LPAD(id, 6, "0")) = ?', [
                    PrintSetting::where('slug', 'fees-receipt')->value('prefix') ?? '',
                    $receipt_number
                ]);
            })
            ->with(['studentEnroll.student', 'studentEnroll.program', 'studentEnroll.session', 
                    'studentEnroll.semester', 'studentEnroll.section', 'category', 
                    'paymentPlan.installments.payments',
                    'resitRequest.subject', 'resitRequest.session',
                    'resitRequest.resitSession', 'resitRequest.resitSemester'])
            ->first();

        if (!$fee) {
            $data['receipt_found'] = false;
            $data['receipt_number'] = $receipt_number;
        } else {
            $data['receipt_found'] = true;
            $data['row'] = $fee;
            $data['print'] = PrintSetting::where('slug', 'fees-receipt')->first();
        }

        return view('verify-receipt', $data);
    }
}
