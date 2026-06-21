<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Fee;
use App\Models\StudentEnroll;
use App\Models\Session;
use App\Models\Semester;
use App\Models\FeesCategory;
use Illuminate\Support\Facades\DB;
use Flasher\Prime\FlasherInterface;

class PartialPaymentReportController extends Controller
{
    /**
     * Display a listing of partially paid fees.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $title = __('partial_payment_report');
        $route = 'admin.partial-payment-report';
        $view = 'admin.partial-payment-report';

        // Base query for partially paid fees
        $query = Fee::with([
            'studentEnroll.student',
            'studentEnroll.session',
            'studentEnroll.semester',
            'studentEnroll.program',
            'category',
            'approvedReceipts'
        ])->where('status', 2); // Only partially paid

        // Filter by session
        if ($request->filled('session_id')) {
            $query->whereHas('studentEnroll', function($q) use ($request) {
                $q->where('session_id', $request->session_id);
            });
        }

        // Filter by semester
        if ($request->filled('semester_id')) {
            $query->whereHas('studentEnroll', function($q) use ($request) {
                $q->where('semester_id', $request->semester_id);
            });
        }

        // Filter by fee category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by student name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('studentEnroll.student', function($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('student_id', 'like', '%' . $search . '%');
            });
        }

        // Order by due date
        $fees = $query->orderBy('due_date', 'asc')->paginate(25)->appends($request->except('page'));

        // Calculate statistics
        $stats = [
            'total_fees' => Fee::where('status', 2)->count(),
            'total_due' => Fee::where('status', 2)->get()->sum(function($fee) {
                return $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
            }),
            'total_paid' => Fee::where('status', 2)->sum('paid_amount'),
            'total_remaining' => Fee::where('status', 2)->get()->sum(function($fee) {
                return $fee->remaining_balance;
            }),
        ];

        // Get filter options
        $sessions = Session::where('status', 1)->orderBy('id', 'desc')->get();
        $semesters = Semester::where('status', 1)->orderBy('id', 'desc')->get();
        $categories = FeesCategory::where('status', 1)->orderBy('title', 'asc')->get();

        return view($view . '.index', compact(
            'title',
            'route',
            'view',
            'fees',
            'stats',
            'sessions',
            'semesters',
            'categories'
        ));
    }

    /**
     * Export partially paid fees report.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        // Get partially paid fees
        $fees = Fee::with([
            'studentEnroll.student',
            'studentEnroll.session',
            'studentEnroll.semester',
            'category',
            'approvedReceipts'
        ])->where('status', 2)->get();

        $fileName = 'partial_payment_report_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function() use ($fees) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, [
                'Student ID',
                'Student Name',
                'Session',
                'Semester',
                'Fee Category',
                'Total Amount',
                'Paid Amount',
                'Remaining Balance',
                'Due Date',
                'Payments Count'
            ]);

            // Data rows
            foreach ($fees as $fee) {
                $student = $fee->studentEnroll->student;
                $studentName = $student->first_name . ' ' . $student->last_name;
                
                fputcsv($file, [
                    $student->student_id ?? 'N/A',
                    $studentName,
                    $fee->studentEnroll->session->title ?? 'N/A',
                    $fee->studentEnroll->semester->title ?? 'N/A',
                    $fee->category->title ?? 'N/A',
                    number_format($fee->total_amount, 2, '.', ''),
                    number_format($fee->paid_amount, 2, '.', ''),
                    number_format($fee->remaining_balance, 2, '.', ''),
                    date('Y-m-d', strtotime($fee->due_date)),
                    $fee->approvedReceipts->count()
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
