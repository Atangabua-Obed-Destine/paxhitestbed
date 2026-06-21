<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentCredit;
use App\Models\CreditApplication;
use App\Models\Student;
use App\Services\StudentCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Flasher\Prime\FlasherInterface;

class StudentCreditController extends Controller
{
    protected $title, $route, $view, $access;
    protected $creditService;

    public function __construct(StudentCreditService $creditService)
    {
        $this->title = trans_choice('module_student_credits', 2);
        $this->route = 'admin.student-credits';
        $this->view = 'admin.student-credits';
        $this->access = 'student-credits';
        $this->creditService = $creditService;
    }

    /**
     * Display a listing of credits.
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        // Filter inputs (must match the form names in the index view).
        $data['selected_status']      = $status      = $request->input('status');
        $data['selected_source_type'] = $sourceType  = $request->input('source_type');
        $data['selected_search']      = $search      = trim((string) $request->input('search'));
        $data['selected_date_from']   = $dateFrom    = $request->input('date_from');
        $data['selected_date_to']     = $dateTo      = $request->input('date_to');

        $query = StudentCredit::with(['student', 'sourceFee.category', 'createdBy', 'applications']);

        // Status / refund-state filter. The blade exposes both real enum
        // values (available, partially_applied, fully_applied, refunded,
        // expired) and refund pseudo-states (refund_pending, refund_approved,
        // refund_rejected) which live on the boolean refund_* columns.
        if ($status) {
            switch ($status) {
                case 'refund_pending':
                    $query->pendingRefund();
                    break;
                case 'refund_approved':
                    $query->approvedRefund();
                    break;
                case 'refund_rejected':
                    $query->rejectedRefund();
                    break;
                case 'applied': // legacy view value -> real enum
                    $query->where('status', StudentCredit::STATUS_FULLY_APPLIED);
                    break;
                default:
                    $query->where('status', $status);
            }
        }

        // Source type. View uses 'adjustment' as a shorthand for the real
        // enum value 'admin_adjustment'.
        if ($sourceType) {
            $query->where('source_type', $sourceType === 'adjustment'
                ? StudentCredit::SOURCE_ADMIN_ADJUSTMENT
                : $sourceType);
        }

        // Free-text search across student id / first / last name.
        if ($search !== '') {
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('student_id', 'LIKE', "%{$search}%")
                  ->orWhere('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $data['credits'] = $query->orderBy('created_at', 'desc')
            ->paginate(25)
            ->appends($request->query());

        // Statistics. Keys MUST match the cards in resources/views/admin/
        // student-credits/index.blade.php (total_credits / available_credits
        // / pending_refunds / total_amount).
        $data['stats'] = [
            'total_credits'     => StudentCredit::count(),
            'available_credits' => StudentCredit::available()->count(),
            'total_amount'      => StudentCredit::available()->sum('remaining_amount'),
            'pending_refunds'   => StudentCredit::pendingRefund()->count(),
            'approved_refunds'  => StudentCredit::approvedRefund()->count(),
        ];

        // Get setting for currency
        $data['setting'] = \App\Models\Setting::first();

        return view($this->view . '.index', $data);
    }

    /**
     * Show credit details.
     */
    public function show($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['credit'] = StudentCredit::with([
            'student', 
            'sourceFee.category', 
            'sourceFee.studentEnroll.program',
            'applications.fee.category',
            'createdBy',
            'refundRequestedBy',
            'refundApprovedBy',
            'refundProcessedBy'
        ])->findOrFail($id);

        // Get setting for currency
        $data['setting'] = \App\Models\Setting::first();

        return view($this->view . '.show', $data);
    }

    /**
     * Create manual credit form.
     */
    public function create()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['students'] = Student::where('status', 1)
            ->orderBy('first_name')
            ->get();

        return view($this->view . '.create', $data);
    }

    /**
     * Store manual credit.
     */
    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $credit = $this->creditService->createManualCredit(
                $request->student_id,
                $request->amount,
                $request->note,
                Auth::id()
            );

            \Flasher\Prime\Flasher::addSuccess(__('credit_created_successfully'));
            return redirect()->route($this->route . '.show', $credit->id);
        } catch (\Exception $e) {
            \Flasher\Prime\Flasher::addError($e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Request refund for a credit.
     */
    public function requestRefund(Request $request, $id)
    {
        $credit = StudentCredit::findOrFail($id);

        if (!$credit->canBeRefunded()) {
            \Flasher\Prime\Flasher::addError(__('credit_cannot_be_refunded'));
            return redirect()->back();
        }

        try {
            $reason = $request->input('reason');
            $this->creditService->requestRefund($credit, $reason, Auth::id());
            \Flasher\Prime\Flasher::addSuccess(__('refund_requested_successfully'));
        } catch (\Exception $e) {
            \Flasher\Prime\Flasher::addError($e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Approve refund request.
     */
    public function approveRefund($id)
    {
        $credit = StudentCredit::findOrFail($id);

        if (!$credit->refund_requested) {
            \Flasher\Prime\Flasher::addError(__('no_refund_request'));
            return redirect()->back();
        }

        try {
            $this->creditService->approveRefund($credit, Auth::id());
            \Flasher\Prime\Flasher::addSuccess(__('refund_approved_successfully'));
        } catch (\Exception $e) {
            \Flasher\Prime\Flasher::addError($e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Reject refund request.
     */
    public function rejectRefund(Request $request, $id)
    {
        $credit = StudentCredit::findOrFail($id);

        try {
            $this->creditService->rejectRefund($credit, $request->reason, Auth::id());
            \Flasher\Prime\Flasher::addSuccess(__('refund_rejected_successfully'));
        } catch (\Exception $e) {
            \Flasher\Prime\Flasher::addError($e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Show process refund form.
     */
    public function processRefundForm($id)
    {
        $data['title'] = __('process_refund');
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['row'] = StudentCredit::with(['student'])->findOrFail($id);

        if (!$data['row']->refund_approved) {
            \Flasher\Prime\Flasher::addError(__('refund_not_approved'));
            return redirect()->route($this->route . '.show', $id);
        }

        return view($this->view . '.process-refund', $data);
    }

    /**
     * Process the refund.
     */
    public function processRefund(Request $request, $id)
    {
        $request->validate([
            'refund_method' => 'required|in:cash,bank_transfer,cheque,mobile_money',
            'refund_reference' => 'nullable|string|max:100',
            'refund_note' => 'nullable|string|max:500',
        ]);

        $credit = StudentCredit::findOrFail($id);

        try {
            $this->creditService->processRefund(
                $credit,
                $request->refund_method,
                $request->refund_reference,
                $request->refund_note,
                Auth::id()
            );

            \Flasher\Prime\Flasher::addSuccess(__('refund_processed_successfully'));
            return redirect()->route($this->route . '.show', $id);
        } catch (\Exception $e) {
            \Flasher\Prime\Flasher::addError($e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * View student's credit summary.
     */
    public function studentCredits($studentId)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['student'] = Student::findOrFail($studentId);
        $data['summary'] = $this->creditService->getCreditSummary($studentId);

        return view($this->view . '.student-credits', $data);
    }

    /**
     * Pending refunds list (for finance approval).
     */
    public function pendingRefunds()
    {
        $data['title'] = __('pending_refunds');
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['rows'] = StudentCredit::with(['student', 'refundRequestedBy'])
            ->pendingRefund()
            ->orderBy('refund_requested_at', 'asc')
            ->paginate(25);

        return view($this->view . '.pending-refunds', $data);
    }

    /**
     * Approved refunds awaiting processing.
     */
    public function approvedRefunds()
    {
        $data['title'] = __('approved_refunds');
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['rows'] = StudentCredit::with(['student', 'refundApprovedBy'])
            ->approvedRefund()
            ->orderBy('refund_approved_at', 'asc')
            ->paginate(25);

        return view($this->view . '.approved-refunds', $data);
    }

    /**
     * Apply credit to a specific fee.
     */
    public function applyToFee(Request $request, $id)
    {
        $request->validate([
            'fee_id' => 'required|exists:fees,id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
        ]);

        $credit = StudentCredit::findOrFail($id);
        $fee = \App\Models\Fee::with('studentEnroll')->findOrFail($request->fee_id);

        // Ensure the fee belongs to the same student. Fee has no direct
        // student_id column — students live on student_enrolls.
        $feeStudentId = optional($fee->studentEnroll)->student_id;
        if (!$feeStudentId || $feeStudentId != $credit->student_id) {
            \Flasher\Prime\Flasher::addError(__('fee_does_not_belong_to_student'));
            return redirect()->back();
        }

        // Ensure amount doesn't exceed available credit
        if ($request->amount > $credit->remaining_amount) {
            \Flasher\Prime\Flasher::addError(__('amount_exceeds_available_credit'));
            return redirect()->back();
        }

        try {
            $this->creditService->applyToFee($credit, $fee, $request->amount, Auth::id(), $request->note);
            \Flasher\Prime\Flasher::addSuccess(__('credit_applied_successfully'));
        } catch (\Exception $e) {
            \Flasher\Prime\Flasher::addError($e->getMessage());
        }

        return redirect()->route($this->route . '.show', $id);
    }
}
