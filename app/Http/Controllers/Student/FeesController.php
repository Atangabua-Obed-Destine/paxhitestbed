<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Models\FeesCategory;
use App\Models\Student;
use App\Models\Program;
use App\Models\Fee;

class FeesController extends Controller
{
    protected $title, $route, $view, $path;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title    = trans_choice('module_fees_report', 1);
        $this->route    = 'student.fees';
        $this->view     = 'student.fees';
        $this->path     = 'fees';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;


        $data['user'] = $user = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get ALL enrollments for this student (across all programs)
        $allEnrollments = StudentEnroll::where('student_id', $user->id)
            ->with(['program', 'semester', 'session'])
            ->orderBy('id', 'desc')
            ->get();

        // Build filter options from ALL enrollments
        $data['sessions'] = $allEnrollments->unique('session_id');
        $data['semesters'] = $allEnrollments->unique('semester_id');
        
        // Build program list for filter (unique programs by matricule)
        $data['programs'] = $allEnrollments->groupBy('matricule')->map(function ($group) {
            return $group->first(); // One representative enrollment per program track
        })->values();

        $enrollmentIds = $allEnrollments->pluck('id')->toArray();
        
        $data['categories'] = FeesCategory::where('status', '1')->orderBy('title', 'asc')->get();


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

        if(!empty($request->category) || $request->category != null){
            $data['selected_category'] = $category = $request->category;
        }
        else{
            $data['selected_category'] = '0';
        }

        // Program filter (by matricule)
        if(!empty($request->program) && $request->program != '0'){
            $data['selected_program'] = $selectedMatricule = $request->program;
            // Narrow enrollment IDs to just this program track
            $enrollmentIds = $allEnrollments->where('matricule', $selectedMatricule)->pluck('id')->toArray();
        } else {
            $data['selected_program'] = '0';
        }


        // Filter Fees - across ALL programs (or filtered by selected program)
        $fees = Fee::with(['studentEnroll.program', 'studentEnroll.session', 'studentEnroll.semester', 'paymentPlan', 'generatedCredits'])
            ->whereIn('student_enroll_id', $enrollmentIds)
            ->when($session != 0, function($query) use ($session) {
                $query->whereHas('studentEnroll', function($q) use ($session) {
                    $q->where('session_id', $session);
                });
            })
            ->when($semester != 0, function($query) use ($semester) {
                $query->whereHas('studentEnroll', function($q) use ($semester) {
                    $q->where('semester_id', $semester);
                });
            });
        
        if(!empty($request->category)){
            $fees->where('category_id', $category);
        }
        // Show unpaid (0), paid (1), and partially paid (2) fees — exclude only cancelled (3)
        $data['rows'] = $fees->whereIn('status', ['0', '1', '2'])->orderBy('assign_date', 'desc')->get();

        // Calculate Statistics - across ALL programs (or filtered)
        $all_fees = Fee::with('studentEnroll.program')
            ->whereIn('student_enroll_id', $enrollmentIds)
            ->when($session != 0, function($query) use ($session) {
                $query->whereHas('studentEnroll', function($q) use ($session) {
                    $q->where('session_id', $session);
                });
            })
            ->when($semester != 0, function($query) use ($semester) {
                $query->whereHas('studentEnroll', function($q) use ($semester) {
                    $q->where('semester_id', $semester);
                });
            });
        
        if(!empty($request->category)){
            $all_fees->where('category_id', $category);
        }
        // Include unpaid (0), paid (1), and partially paid (2) fees in statistics — exclude only cancelled (3)
        $statistics = $all_fees->whereIn('status', ['0', '1', '2'])->get();

        // Total Fees Assigned
        $data['total_fees'] = $statistics->count();
        
        // Total Net Amount (after discount + fine)
        $data['total_net_amount'] = $statistics->sum('total_amount');
        
        // Paid Fees (status = 1)
        // Cap each fee's "paid towards dues" at its total_amount so that any
        // overpayment (which is already extracted as a StudentCredit and may
        // have been applied to another fee) is not double-counted.
        $paid_fees = $statistics->where('status', '1');
        $data['paid_fees_count'] = $paid_fees->count();
        $data['total_paid_amount'] = $paid_fees->sum(function ($f) {
            return min((float) $f->paid_amount, (float) $f->total_amount);
        });

        // Partially Paid Fees (status = 2)
        $partial_fees = $statistics->where('status', '2');
        $data['partial_fees_count'] = $partial_fees->count();
        $data['partial_paid_amount'] = $partial_fees->sum(function ($f) {
            return min((float) $f->paid_amount, (float) $f->total_amount);
        });
        
        // Unpaid Fees (status = 0)
        $unpaid_fees = $statistics->where('status', '0');
        $data['unpaid_fees_count'] = $unpaid_fees->count();
        $data['unpaid_amount'] = $unpaid_fees->sum('total_amount');
        
        // Total Amount Paid (fully + partially)
        $data['total_amount_paid'] = $data['total_paid_amount'] + $data['partial_paid_amount'];
        
        // Total Remaining Balance
        $data['total_remaining_balance'] = $data['total_net_amount'] - $data['total_amount_paid'];


        return view($this->view.'.index', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function pay($id)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        $user = Auth::guard('student')->user()->id;

        // Filter Fees (allow both unpaid and partially paid)
        $fees = Fee::where('id', $id)->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($user){
            $query->where('student_id', $user);
        });
        $data['row'] = $fees->whereIn('status', ['0', '2'])->firstOrFail();

        return view($this->view.'.pay', $data);
    }

    /**
     * Display the specified resource for print.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        //
        $data['title'] = trans_choice('module_fees_report', 1);
        $data['path'] = 'print-setting';

        $user = Auth::guard('student')->user()->id;

        // View - Allow printing for fully paid, partially paid, and fees with payment plans
        $data['print'] = \App\Models\PrintSetting::where('slug', 'fees-receipt')->firstOrFail();
        
        $fees = Fee::with(['paymentPlan.installments.payments'])
            ->where('id', $id)
            ->whereHas('studentEnroll', function ($query) use ($user) {
                $query->where('student_id', $user);
            })
            ->where(function($query) {
                $query->whereIn('status', ['1', '2'])
                      ->orWhereNotNull('payment_plan_id');
            });
        
        $data['row'] = $fees->firstOrFail();

        return view('admin.fees-student.print', $data);
    }
}
