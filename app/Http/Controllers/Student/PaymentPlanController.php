<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\Student;
use App\Models\PrintSetting;

class PaymentPlanController extends Controller
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
        $this->title = 'My Payment Plans';
        $this->route = 'student.payment-plan';
        $this->view = 'student.payment-plan';
        $this->path = 'payment-plan';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        
        // Add setting for currency symbol
        $data['setting'] = PrintSetting::where('slug', 'fees-receipt')->first();

        // Get authenticated student
        $student = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get all payment plans for this student
        $data['payment_plans'] = PaymentPlan::with(['fee.category', 'fee.studentEnroll.program', 'installments'])
            ->where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        $data['active_plans_count'] = $data['payment_plans']->where('status', 'active')->count();
        $data['total_paid'] = $data['payment_plans']->sum('total_paid');
        $data['total_remaining'] = $data['payment_plans']->where('status', 'active')->sum('remaining_balance');
        
        // Get overdue installments count
        $data['overdue_installments_count'] = PaymentPlanInstallment::whereHas('paymentPlan', function($query) use ($student) {
                $query->where('student_id', $student->id)->where('status', 'active');
            })
            ->where('status', 'overdue')
            ->count();

        // Get upcoming installments (next 30 days)
        $data['upcoming_installments'] = PaymentPlanInstallment::with(['paymentPlan.fee.category'])
            ->whereHas('paymentPlan', function($query) use ($student) {
                $query->where('student_id', $student->id)->where('status', 'active');
            })
            ->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<=', now()->addDays(30))
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data['title'] = 'Payment Plan Details';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        
        // Add setting for currency symbol
        $data['setting'] = PrintSetting::where('slug', 'fees-receipt')->first();

        // Get authenticated student
        $student = Student::where('id', Auth::guard('student')->user()->id)->firstOrFail();

        // Get payment plan (ensure it belongs to this student)
        $data['row'] = PaymentPlan::with([
                'fee.category',
                'fee.studentEnroll.program',
                'fee.studentEnroll.session',
                'fee.studentEnroll.semester',
                'installments.payments',
                'installments.paymentReceipts',
                'creator',
                'approver'
            ])
            ->where('student_id', $student->id)
            ->findOrFail($id);

        return view($this->view.'.show', $data);
    }
}
