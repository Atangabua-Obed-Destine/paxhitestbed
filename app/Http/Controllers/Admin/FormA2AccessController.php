<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\FeesCategory;
use App\Models\Program;
use App\Models\Faculty;
use App\Models\Session;
use App\Models\Fee;

class FormA2AccessController extends Controller
{
    public function index(Request $request)
    {
        $data['title'] = 'Form A2 Access Control';
        
        // Get First Installment Category
        $firstInstallmentCategory = FeesCategory::where('is_first_installment', 1)->first();
        
        if (!$firstInstallmentCategory) {
            return redirect()->back()->with('error', 'First Installment Fee Category not configured.');
        }

        // Base Query: Year 1, Semester 1 Enrollments (excluding resit semesters)
        $query = StudentEnroll::whereHas('semester', function($q) {
            $q->where('year', 1)
              ->where('semester_type', 1)
              ->where(function($q2) {
                  $q2->whereNull('is_resit')->orWhere('is_resit', 0);
              });
        })->with(['student', 'program', 'semester', 'session']);

        // Session Filter - Important for separating academic years
        if ($request->has('session_id') && $request->session_id != '') {
            $query->where('session_id', $request->session_id);
        }

        // Filters
        if ($request->has('program_id') && $request->program_id != '') {
            $query->where('program_id', $request->program_id);
        }
        
        if ($request->has('faculty_id') && $request->faculty_id != '') {
            $query->whereHas('program', function($q) use ($request) {
                $q->where('faculty_id', $request->faculty_id);
            });
        }

        if ($request->has('bypass_status') && $request->bypass_status != '') {
            $query->where('bypass_payment_restriction', $request->bypass_status);
        }

        // Get all enrollments matching criteria
        $allEnrollments = $query->orderBy('matricule', 'asc')->get();
        
        // Process enrollments to add payment info and filter by payment status if needed
        $students = $allEnrollments->map(function($enrollment) use ($firstInstallmentCategory) {
            // Find the fee record for First Installment
            $fee = Fee::where('student_enroll_id', $enrollment->id)
                      ->where('category_id', $firstInstallmentCategory->id)
                      ->with('paymentPlan')
                      ->first();
            
            $enrollment->first_installment_fee = $fee;
            
            // Determine Status
            if (!$fee) {
                $enrollment->payment_status = 'Not Assigned';
                $enrollment->paid_amount = 0;
                $enrollment->total_amount = 0;
            } else {
                $enrollment->paid_amount = $fee->paid_amount;
                $enrollment->total_amount = $fee->fee_amount - $fee->discount_amount + $fee->fine_amount;
                
                if ($fee->status == 1) {
                    $enrollment->payment_status = 'Paid';
                } elseif ($fee->paid_amount > 0) {
                    $enrollment->payment_status = 'Partial';
                } else {
                    $enrollment->payment_status = 'Unpaid';
                }
            }
            
            return $enrollment;
        });

        // Filter by Payment Status if requested
        if ($request->has('payment_status') && $request->payment_status != '') {
            $students = $students->filter(function($student) use ($request) {
                return $student->payment_status == $request->payment_status;
            });
        }

        // KPIs
        $data['total_students'] = $students->count();
        $data['total_unpaid'] = $students->where('payment_status', 'Unpaid')->count();
        $data['total_partial'] = $students->where('payment_status', 'Partial')->count();
        $data['total_bypassed'] = $students->where('bypass_payment_restriction', 1)->count();
        $data['total_paid'] = $students->where('payment_status', 'Paid')->count();
        $data['total_not_assigned'] = $students->where('payment_status', 'Not Assigned')->count();

        $data['rows'] = $students;
        $data['faculties'] = Faculty::where('status', '1')->orderBy('title', 'asc')->get();
        $data['programs'] = Program::where('status', '1')->orderBy('title', 'asc')->get();
        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();
        
        $data['selected_session'] = $request->session_id;
        $data['selected_faculty'] = $request->faculty_id;
        $data['selected_program'] = $request->program_id;
        $data['selected_bypass'] = $request->bypass_status;
        $data['selected_payment_status'] = $request->payment_status;

        return view('admin.form-a2-access.index', $data);
    }

    public function update(Request $request, $id)
    {
        $enrollment = StudentEnroll::findOrFail($id);
        
        $enrollment->bypass_payment_restriction = $request->status;
        $enrollment->save();

        $statusMsg = $request->status == 1 ? 'Access Granted' : 'Access Revoked';
        
        return redirect()->back()->with('success', 'Student ' . $statusMsg . ' successfully.');
    }

    /**
     * Bulk update bypass status for multiple students
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'enrollment_ids' => 'required|array',
            'enrollment_ids.*' => 'exists:student_enrolls,id',
            'status' => 'required|in:0,1'
        ]);

        $count = StudentEnroll::whereIn('id', $request->enrollment_ids)
            ->update(['bypass_payment_restriction' => $request->status]);

        $statusMsg = $request->status == 1 ? 'Access Granted' : 'Access Revoked';
        
        return redirect()->back()->with('success', $count . ' student(s) ' . $statusMsg . ' successfully.');
    }
}
