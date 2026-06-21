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
        
        return view('admin.platform-fee.settings', compact('setting', 'systemSetting'));
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
            'is_enabled' => 'boolean',
        ]);

        $setting = PlatformFeeSetting::first();
        $setting->update([
            'title' => $request->title,
            'welcome_message' => $request->welcome_message,
            'fee_amount' => $request->fee_amount,
            'payment_instructions' => $request->payment_instructions,
            'is_enabled' => $request->has('is_enabled'),
        ]);

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
                ->addColumn('student_note', function ($row) {
                    return $row->student_note;
                })
                ->addColumn('actions', function ($row) {
                    $actions = '<button onclick="viewPayment(' . $row->id . ')" class="btn btn-action btn-view"><i class="fas fa-eye"></i> View</button> ';
                    if ($row->status == 'pending') {
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
        ]);
    }

    /**
     * Approve payment.
     */
    public function approvePayment(Request $request, $id)
    {
        $payment = PlatformFeePayment::findOrFail($id);
        
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
     * Display statistics.
     */
    public function statistics()
    {
        $setting = PlatformFeeSetting::first();
        
        $totalPayments = PlatformFeePayment::count();
        $pendingPayments = PlatformFeePayment::where('status', 'pending')->count();
        $approvedPayments = PlatformFeePayment::where('status', 'approved')->count();
        $rejectedPayments = PlatformFeePayment::where('status', 'rejected')->count();
        
        $totalCollected = PlatformFeePayment::where('status', 'approved')->sum('fee_amount');
        
        // Get sessions with payment stats
        $sessionStats = DB::table('platform_fee_payments')
            ->join('sessions', 'platform_fee_payments.session_id', '=', 'sessions.id')
            ->select(
                'sessions.title as session_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN platform_fee_payments.status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN platform_fee_payments.status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN platform_fee_payments.status = "rejected" THEN 1 ELSE 0 END) as rejected'),
                DB::raw('SUM(CASE WHEN platform_fee_payments.status = "approved" THEN platform_fee_payments.fee_amount ELSE 0 END) as revenue')
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
