<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeeSetting;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeExemption;
use App\Models\StudentEnroll;
use App\Models\Session;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PlatformFeeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:platform-fee-view', ['only' => ['settings', 'verifications', 'statistics', 'exemptions', 'getPaymentsData', 'getPayment', 'getPendingCount']]);
        $this->middleware('permission:platform-fee-action', ['only' => ['updateSettings', 'approvePayment', 'rejectPayment', 'storeExemption']]);
        $this->middleware('permission:platform-fee-delete', ['only' => ['deleteExemption']]);
    }

    /**
     * Display platform fee settings.
     */
    public function settings()
    {
        $setting = PlatformFeeSetting::firstOrCreate([]);
        $systemSetting = Setting::where('status', '1')->first();

        // Online payment needs three separate things to be true, and two of
        // them live in .env rather than here. Handing the screen the reason it
        // is not available saves an admin ticking a box and wondering why
        // nothing changed.
        $gateway = app(\App\Services\Payment\FapshiClient::class);
        $gatewayBlocker = $setting->onlinePaymentBlocker();

        return view('admin.platform-fee.settings', compact('setting', 'systemSetting', 'gateway', 'gatewayBlocker'));
    }

    /**
     * Update platform fee settings.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'welcome_message' => 'nullable|string',
            'fee_amount' => 'required|numeric|min:0',
            'payment_instructions' => 'nullable|string',
            // A shortcode a student taps rather than types. It must carry the
            // amount placeholder: a pattern with the amount already written into
            // it would keep charging that figure after the fee changed.
            'ussd_template' => [
                'nullable',
                'string',
                'max:191',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        return;
                    }

                    $hasPlaceholder = false;
                    foreach (PlatformFeeSetting::AMOUNT_PLACEHOLDERS as $placeholder) {
                        if (str_contains($value, $placeholder)) {
                            $hasPlaceholder = true;
                            break;
                        }
                    }

                    if (!$hasPlaceholder) {
                        $fail(__('The shortcode must include {amount} where the fee goes, for example *126*4*123456*{amount}#'));
                    }

                    if (!str_starts_with(trim($value), '*')) {
                        $fail(__('A shortcode starts with *, for example *126*4*123456*{amount}#'));
                    }

                    if (!str_ends_with(trim($value), '#')) {
                        $fail(__('A shortcode ends with #, for example *126*4*123456*{amount}#'));
                    }
                },
            ],
            'merchant_name' => 'nullable|string|max:191',
            'merchant_number' => 'nullable|string|max:50',
            'is_enabled' => 'boolean',

            // The gateway passes its commission on to the student. Held as a
            // percentage rather than a flat figure because a flat figure
            // silently becomes wrong the moment the fee changes.
            'gateway_fee_percent' => 'nullable|numeric|min:0|max:100',
            'gateway_fee_fixed' => 'nullable|numeric|min:0',
            'online_payment_enabled' => 'boolean',
        ]);

        $setting = PlatformFeeSetting::first();
        $setting->update([
            'title' => $request->title,
            'welcome_message' => $request->welcome_message,
            'fee_amount' => $request->fee_amount,
            'payment_instructions' => $request->payment_instructions,
            'ussd_template' => $request->filled('ussd_template') ? trim($request->ussd_template) : null,
            'merchant_name' => $request->merchant_name,
            'merchant_number' => $request->merchant_number,
            'is_enabled' => $request->has('is_enabled'),
            'gateway_fee_percent' => (float) $request->input('gateway_fee_percent', 0),
            'gateway_fee_fixed' => (float) $request->input('gateway_fee_fixed', 0),
            'online_payment_enabled' => $request->has('online_payment_enabled'),
        ]);

        // Ticking the box is not the same as it working, and an admin who is
        // told so now will not find out from a student later.
        if ($setting->online_payment_enabled && ($blocker = $setting->fresh()->onlinePaymentBlocker())) {
            return redirect()->back()->with('warning',
                'Settings saved, but students cannot pay online yet: ' . $blocker);
        }

        return redirect()->back()->with('success', 'Platform fee settings updated successfully!');
    }

    /**
     * Display payment verifications list.
     */
    public function verifications()
    {
        return view('admin.platform-fee.verifications');
    }

    /**
     * Get payments data for DataTables.
     */
    public function getPaymentsData(Request $request)
    {
        $query = PlatformFeePayment::with(['studentEnroll.student', 'session', 'verifiedBy'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('student_name', function ($row) {
                    return $row->studentEnroll->student->first_name . ' ' . $row->studentEnroll->student->last_name;
                })
                ->addColumn('student_id', function ($row) {
                    return $row->studentEnroll->student->student_id ?? 'N/A';
                })
                ->addColumn('session', function ($row) {
                    return $row->session->title ?? 'N/A';
                })
                ->editColumn('fee_amount', function ($row) {
                    return number_format($row->fee_amount, 2);
                })
                ->editColumn('payment_date', function ($row) {
                    return $row->payment_date ? $row->payment_date->format('M d, Y') : 'N/A';
                })
                ->editColumn('status', function ($row) {
                    return $row->status_badge;
                })
                ->editColumn('created_at', function ($row) {
                    return $row->created_at->diffForHumans();
                })
                ->addColumn('receipt_path', function ($row) {
                    return $row->receipt_path;
                })
                ->addColumn('receipt_url', function ($row) {
                    return $row->receipt_path ? asset('uploads/platform-fees/' . $row->receipt_path) : null;
                })
                ->addColumn('paid_via', function ($row) {
                    return $row->paid_via;
                })
                // A payment the gateway settled has no screenshot to look at.
                // Giving the column something to say keeps the list from
                // rendering an empty cell where every other row has a link.
                ->addColumn('proof', function ($row) {
                    if ($row->isGatewayPaid()) {
                        return 'Paid online · ref ' . e((string) $row->gateway_trans_id);
                    }

                    return $row->receipt_path ? 'receipt' : '—';
                })
                ->addColumn('student_note', function ($row) {
                    return $row->student_note;
                })
                ->addColumn('actions', function ($row) {
                    $actions = '<button onclick="viewPayment(' . $row->id . ')" class="btn btn-action btn-view"><i class="fas fa-eye"></i> View</button> ';
                    // A payment the gateway confirmed is not a human's to
                    // approve or reject. The buttons are withheld here and the
                    // actions themselves refuse as well, because a hidden
                    // button is a courtesy and not a control.
                    if ($row->status == 'pending' && !$row->isGatewayPaid()) {
                        $actions .= '<button onclick="approvePayment(' . $row->id . ')" class="btn btn-action btn-approve"><i class="fas fa-check"></i> Approve</button> ';
                        $actions .= '<button onclick="rejectPayment(' . $row->id . ')" class="btn btn-action btn-reject"><i class="fas fa-times"></i> Reject</button>';
                    }
                    return $actions;
                })
                ->rawColumns(['status', 'actions'])
                ->make(true);
        }
    }

    /**
     * Get single payment details.
     */
    public function getPayment($id)
    {
        $payment = PlatformFeePayment::with(['student', 'session'])->findOrFail($id);
        
        return response()->json([
            'id' => $payment->id,
            'student_name' => $payment->student ? $payment->student->first_name . ' ' . $payment->student->last_name : 'N/A',
            'student_id' => $payment->student ? $payment->student->student_id : 'N/A',
            'session' => $payment->session ? $payment->session->title : 'N/A',
            'fee_amount' => number_format($payment->fee_amount, 2),
            'payment_date' => $payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A',
            'created_at' => $payment->created_at->format('M d, Y h:i A'),
            'status' => $payment->status,
            'student_note' => $payment->student_note,
            'receipt_path' => $payment->receipt_path,
            'receipt_url' => $payment->receipt_path ? asset('uploads/platform-fees/' . $payment->receipt_path) : null,
            'paid_via' => $payment->paid_via,
            'paid_online' => $payment->isGatewayPaid(),
            'paid_amount' => number_format((float) $payment->paid_amount, 2),
            'gateway_trans_id' => $payment->gateway_trans_id,
            // Carries the reference and what the student was actually charged,
            // which for an online payment is the only record of it on this
            // screen.
            'admin_note' => $payment->admin_note,
        ]);
    }

    /**
     * Approve payment.
     */
    public function approvePayment(Request $request, $id)
    {
        $payment = PlatformFeePayment::findOrFail($id);

        if ($blocked = $this->refuseIfGatewayPaid($payment)) {
            return $blocked;
        }

        $payment->update([
            'status' => 'approved',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'admin_note' => $request->admin_note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment approved successfully!'
        ]);
    }

    /**
     * Reject payment.
     */
    public function rejectPayment(Request $request, $id)
    {
        $request->validate([
            'admin_note' => 'required|string'
        ]);

        $payment = PlatformFeePayment::findOrFail($id);

        if ($blocked = $this->refuseIfGatewayPaid($payment)) {
            return $blocked;
        }

        $payment->update([
            'status' => 'rejected',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'admin_note' => $request->admin_note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment rejected successfully!'
        ]);
    }

    /**
     * A payment the gateway settled is not a human's to overturn here.
     *
     * Rejecting one would leave the school holding money it had told the
     * student was refused, and approving one already approved would say a
     * member of staff checked something they never saw. Either correction
     * belongs with a refund, not a status flip.
     *
     * The buttons are hidden for these rows as well; this is the control, and
     * that is the courtesy.
     */
    protected function refuseIfGatewayPaid(PlatformFeePayment $payment)
    {
        if (!$payment->isGatewayPaid()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'This fee was paid online and confirmed by the payment gateway, so it cannot be '
                . 'approved or rejected by hand. Reference ' . $payment->gateway_trans_id . '.',
        ], 422);
    }

    /**
     * Display statistics.
     */
    public function statistics()
    {
        $setting = PlatformFeeSetting::first();
        
        $totalPayments = PlatformFeePayment::count();
        $pendingPayments = PlatformFeePayment::where('status', 'pending')->count();
        $approvedPayments = PlatformFeePayment::where('status', 'approved')->count();
        $rejectedPayments = PlatformFeePayment::where('status', 'rejected')->count();
        
        /*
         * What was actually collected, which is not one column.
         *
         * A manual row records no received figure at all — approvePayment()
         * sets a status and nothing else — so for those the fee is the best
         * available answer. A gateway row records exactly what the student
         * paid, including the commission passed on to them, so paid_amount is
         * the true one and the only one that will reconcile against a bank
         * statement.
         *
         * Mixing the two is deliberate rather than accidental, and is why it
         * is written out here instead of being a sum() on one column.
         */
        $collectedExpression = 'SUM(CASE WHEN paid_via = "fapshi" THEN paid_amount ELSE fee_amount END)';

        $totalCollected = PlatformFeePayment::where('status', 'approved')
            ->value(DB::raw($collectedExpression)) ?? 0;

        $onlinePayments = PlatformFeePayment::where('status', 'approved')->where('paid_via', 'fapshi')->count();
        $manualPayments = PlatformFeePayment::where('status', 'approved')->where('paid_via', '!=', 'fapshi')->count();

        $onlineCollected = PlatformFeePayment::where('status', 'approved')
            ->where('paid_via', 'fapshi')
            ->sum('paid_amount');

        // What the students paid over and above the fee, i.e. the gateway's
        // commission that was passed on to them. Reported separately so it is
        // never mistaken for the school's income.
        $gatewayCommission = \App\Models\FapshiTransaction::where('status', 'successful')
            ->get()
            ->sum(fn ($transaction) => (float) $transaction->commissionTaken());

        $needingAttention = \App\Models\FapshiTransaction::whereIn('status',
            \App\Models\FapshiTransaction::NEEDS_ATTENTION_STATUSES)->count();

        // Get sessions with payment stats
        $sessionStats = DB::table('platform_fee_payments')
            ->join('sessions', 'platform_fee_payments.session_id', '=', 'sessions.id')
            ->select(
                'sessions.title as session_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN platform_fee_payments.status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN platform_fee_payments.status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN platform_fee_payments.status = "rejected" THEN 1 ELSE 0 END) as rejected'),
                DB::raw('SUM(CASE WHEN platform_fee_payments.status = "approved"'
                    . ' THEN (CASE WHEN platform_fee_payments.paid_via = "fapshi"'
                    . ' THEN platform_fee_payments.paid_amount ELSE platform_fee_payments.fee_amount END)'
                    . ' ELSE 0 END) as revenue')
            )
            ->groupBy('sessions.id', 'sessions.title')
            ->get();
        
        $systemSetting = Setting::select('currency_symbol')->first();
        
        $statistics = [
            'total_payments' => $totalPayments,
            'pending_payments' => $pendingPayments,
            'approved_payments' => $approvedPayments,
            'rejected_payments' => $rejectedPayments,
            'total_collected' => $totalCollected,
            'online_payments' => $onlinePayments,
            'manual_payments' => $manualPayments,
            'online_collected' => $onlineCollected,
            'gateway_commission' => $gatewayCommission,
            'needing_attention' => $needingAttention,
            'session_breakdown' => $sessionStats
        ];
        
        return view('admin.platform-fee.statistics', compact('statistics', 'systemSetting'));
    }

    /**
     * Display exemptions management.
     */
    public function exemptions()
    {
        $exemptions = PlatformFeeExemption::with(['studentEnroll.student', 'session', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        $sessions = Session::where('status', 1)->orderBy('title', 'desc')->get();
        $studentEnrollments = StudentEnroll::with('student', 'session')
            ->whereHas('student')
            ->whereHas('session')
            ->get();
        
        return view('admin.platform-fee.exemptions', compact('exemptions', 'sessions', 'studentEnrollments'));
    }

    /**
     * Store new exemption.
     */
    public function storeExemption(Request $request)
    {
        $request->validate([
            'exemption_type' => 'required|in:student,session',
            'student_enroll_id' => 'required_if:exemption_type,student',
            'session_id' => 'required_if:exemption_type,session',
            'reason' => 'nullable|string',
        ]);

        PlatformFeeExemption::create([
            'exemption_type' => $request->exemption_type,
            'student_enroll_id' => $request->exemption_type == 'student' ? $request->student_enroll_id : null,
            'session_id' => $request->exemption_type == 'session' ? $request->session_id : null,
            'reason' => $request->reason,
            'created_by' => Auth::id(),
            'status' => 1,
        ]);

        return redirect()->back()->with('success', 'Exemption created successfully!');
    }

    /**
     * Delete exemption.
     */
    public function deleteExemption($id)
    {
        $exemption = PlatformFeeExemption::findOrFail($id);
        $exemption->delete();

        return redirect()->back()->with('success', 'Exemption deleted successfully!');
    }

    /**
     * Get pending payments count for badge.
     */
    public function getPendingCount()
    {
        return PlatformFeePayment::where('status', 'pending')->count();
    }
}
