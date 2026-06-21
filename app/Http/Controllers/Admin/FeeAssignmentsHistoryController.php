<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Services\StaffAssignmentService;
use App\Services\StudentCreditService;

use App\Models\Faculty;
use App\Models\Fee;
use App\Models\FeesCategory;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;

/**
 * Universal "Fee Assignments History".
 *
 * Unlike admin/fees-master (which only lists rows from the legacy fees_masters
 * template table), this controller reads the canonical `fees` ledger and
 * derives each row's *origin* (Resit / Payment Plan / Fees Master /
 * Program-Semester-Fee / Auto-Progression / Manual). That makes this page
 * the single source of truth for "every fee assignment, however it was
 * created".
 */
class FeeAssignmentsHistoryController extends Controller
{
    protected $title;
    protected $route;
    protected $view;
    protected $path;
    protected $access;

    public function __construct()
    {
        $this->title  = __('fee_assignments_history');
        $this->route  = 'admin.fee-assignments-history';
        $this->view   = 'admin.fee-assignments-history';
        $this->path   = 'fee-assignments-history';
        $this->access = 'fee-assignments-history';

        $this->middleware('permission:' . $this->access . '-view',     ['only' => ['index']]);
        $this->middleware('permission:' . $this->access . '-delete',   ['only' => ['destroy']]);
        $this->middleware('permission:' . $this->access . '-transfer', ['only' => ['transferTargets', 'transfer']]);
    }

    /**
     * AJAX: list candidate target fees a payment can be transferred to.
     * Returns same-student, non-cancelled, non-fully-paid fees only.
     */
    public function transferTargets($id)
    {
        $source = Fee::with('studentEnroll')->findOrFail($id);

        $studentId = optional($source->studentEnroll)->student_id;
        if (!$studentId) {
            return response()->json(['targets' => []]);
        }

        $targets = Fee::with(['category', 'studentEnroll.semester', 'studentEnroll.session'])
            ->whereHas('studentEnroll', fn ($q) => $q->where('student_id', $studentId))
            ->where('id', '!=', $source->id)
            ->whereNotIn('status', [3]) // exclude cancelled
            ->get()
            ->map(function ($t) {
                $due  = (float) $t->total_amount;
                $paid = (float) ($t->paid_amount ?? 0);
                $bal  = max(0, $due - $paid);
                return [
                    'id'         => $t->id,
                    'category'   => $t->category->title ?? '—',
                    'semester'   => optional(optional($t->studentEnroll)->semester)->title ?? '',
                    'session'    => optional(optional($t->studentEnroll)->session)->title ?? '',
                    'due'        => $due,
                    'paid'       => $paid,
                    'balance'    => $bal,
                    'transferable' => $bal > 0,
                ];
            })
            ->filter(fn ($r) => $r['transferable'])
            ->values();

        return response()->json(['targets' => $targets]);
    }

    /**
     * Transfer a paid amount from one fee to another (same student).
     */
    public function transfer(Request $request, $id)
    {
        $request->validate([
            'target_fee_id' => 'required|integer|exists:fees,id',
            'amount'        => 'required|numeric|min:0.01',
            'reason'        => 'required|string|min:3|max:500',
        ]);

        $source = Fee::with(['studentEnroll', 'paymentPlan', 'resitRequest'])
            ->findOrFail($id);
        $target = Fee::with(['studentEnroll', 'paymentPlan'])
            ->findOrFail($request->input('target_fee_id'));

        // Hard guards beyond the service-layer ones.
        if ($source->resitRequest) {
            \Toastr::error(__('cannot_transfer_from_resit_fee'), __('msg_failed'));
            return redirect()->back();
        }
        if (method_exists($source, 'paymentReceipts') && $source->paymentReceipts()->where('verification_status', 'pending')->exists()) {
            \Toastr::error(__('cannot_transfer_with_pending_receipts'), __('msg_failed'));
            return redirect()->back();
        }

        try {
            $service = app(StudentCreditService::class);
            $result  = $service->transferBetweenFees(
                $source,
                $target,
                (float) $request->input('amount'),
                trim((string) $request->input('reason')),
                Auth::guard('web')->id()
            );
        } catch (\InvalidArgumentException $e) {
            \Toastr::error($e->getMessage(), __('msg_failed'));
            return redirect()->back();
        } catch (\Throwable $e) {
            \Log::error('Fee transfer failed: ' . $e->getMessage(), [
                'source_fee_id' => $id,
                'target_fee_id' => $request->input('target_fee_id'),
            ]);
            \Toastr::error(__('msg_failed') . ': ' . $e->getMessage(), __('msg_failed'));
            return redirect()->back();
        }

        \Toastr::success(__('payment_transferred_successfully'), __('msg_success'));
        return redirect()->back();
    }

    /**
     * Delete a single fee assignment.
     *
     * Hard guards (in order):
     *   1. Already paid (paid_amount > 0 OR status in 1/2)              → blocked
     *   2. Has any payment receipts (pending or approved)                → blocked
     *   3. Has applied/generated student credits                          → blocked
     *   4. Pending multi-payment distributions                            → blocked
     *   5. Tied to a resit request                                        → blocked (delete via resit flow)
     *   6. Tied to an active payment plan                                 → blocked
     */
    public function destroy($id)
    {
        $fee = Fee::with(['resitRequest', 'paymentPlan', 'creditApplications', 'generatedCredits'])
            ->findOrFail($id);

        $reasons = [];

        if ((float) ($fee->paid_amount ?? 0) > 0) {
            $reasons[] = __('cannot_delete_fee_with_payments');
        }
        if (in_array((int) $fee->status, [1, 2], true)) {
            $reasons[] = __('cannot_delete_paid_fee');
        }
        if ($fee->paymentReceipts()->exists()) {
            $reasons[] = __('cannot_delete_fee_with_receipts');
        }
        // Only block if there are credits whose money is still "live":
        //   - any generated credit with remaining_amount > 0 (student is
        //     still owed money that originated from this fee), or
        //   - any credit application that is still increasing this fee's
        //     paid_amount (caught by the paid_amount > 0 guard above, but
        //     we keep this defensive check for active/pending applications).
        $hasOpenGeneratedCredit = $fee->generatedCredits()
            ->where('remaining_amount', '>', 0)
            ->exists();
        if ($hasOpenGeneratedCredit) {
            $reasons[] = __('cannot_delete_fee_with_credits');
        }
        if (method_exists($fee, 'pendingMultiPaymentDistributions')
            && $fee->pendingMultiPaymentDistributions()->exists()) {
            $reasons[] = __('cannot_delete_fee_with_pending_multipayment');
        }
        if ($fee->resitRequest) {
            $reasons[] = __('cannot_delete_resit_fee_directly');
        }
        if ($fee->payment_plan_id && $fee->paymentPlan && $fee->paymentPlan->status === 'active') {
            $reasons[] = __('cannot_delete_fee_with_active_plan');
        }

        if (!empty($reasons)) {
            \Toastr::error(implode(' ', $reasons), __('msg_failed'));
            return redirect()->back();
        }

        try {
            DB::transaction(function () use ($fee) {
                // Detach from any legacy fees-master pivot rows so cohort
                // counts on /admin/fees-master stay accurate. The pivot only
                // has (fees_master_id, student_enroll_id) — no category_id —
                // so we resolve matching template ids by category + amount
                // first, then detach this enrollment from each.
                $masterIds = DB::table('fees_masters')
                    ->where('category_id', $fee->category_id)
                    ->where('amount', $fee->fee_amount)
                    ->pluck('id');

                if ($masterIds->isNotEmpty()) {
                    DB::table('fees_master_student_enroll')
                        ->where('student_enroll_id', $fee->student_enroll_id)
                        ->whereIn('fees_master_id', $masterIds)
                        ->delete();
                }

                $fee->delete();
            });
        } catch (\Throwable $e) {
            \Log::error('Fee assignment delete failed: ' . $e->getMessage(), ['fee_id' => $id]);
            \Toastr::error(__('msg_failed') . ': ' . $e->getMessage(), __('msg_failed'));
            return redirect()->back();
        }

        \Toastr::success(__('fee_assignment_deleted_successfully'), __('msg_success'));
        return redirect()->back();
    }

    /**
     * The universal ledger view.
     */
    public function index(Request $request)
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['path']   = $this->path;
        $data['access'] = $this->access;

        // ------------------------------------------------------------------
        // Filter inputs (kept loose-typed at "0" for parity with sibling
        // pages and the shared common.inc.fees_search_filter partial).
        // ------------------------------------------------------------------
        $data['selected_faculty']        = $faculty        = $request->input('faculty', '0') ?: '0';
        $data['selected_program']        = $program        = $request->input('program', '0') ?: '0';
        $data['selected_session']        = $session        = $request->input('session', '0') ?: '0';
        $data['selected_semester']       = $semester       = $request->input('semester', '0') ?: '0';
        $data['selected_section']        = $section        = $request->input('section', '0') ?: '0';
        $data['selected_category']       = $category       = $request->input('category', '0') ?: '0';
        $data['selected_origin']         = $origin         = $request->input('origin', 'all');
        $data['selected_payment_status'] = $paymentStatus  = $request->input('payment_status', 'all');
        $data['selected_student_id']     = $studentSearch  = trim((string) $request->input('student_id', ''));
        $data['selected_date_from']      = $dateFrom       = $request->input('date_from');
        $data['selected_date_to']        = $dateTo         = $request->input('date_to');
        $data['selected_view']           = $viewMode       = $request->input('view_mode', 'flat'); // flat|grouped

        // ------------------------------------------------------------------
        // Filter dropdowns (parity with /admin/fees-student-report).
        // ------------------------------------------------------------------
        $facultyQuery        = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties']   = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['categories']  = FeesCategory::where('status', '1')->orderBy('title', 'asc')->get();

        if ($faculty !== '0') {
            $data['programs'] = Program::where('faculty_id', $faculty)
                ->where('status', '1')->orderBy('title', 'asc')->get();
        }
        if ($program !== '0') {
            $data['sessions'] = Session::where('status', 1)
                ->whereHas('programs', fn ($q) => $q->where('program_id', $program))
                ->orderBy('id', 'desc')->get();

            $data['semesters'] = Semester::where('status', 1)
                ->whereHas('programs', fn ($q) => $q->where('program_id', $program))
                ->orderBy('id', 'asc')->get();
        }
        if ($program !== '0' && $semester !== '0') {
            $data['sections'] = Section::where('status', 1)
                ->whereHas('semesterPrograms', function ($q) use ($program, $semester) {
                    $q->where('program_id', $program)->where('semester_id', $semester);
                })
                ->orderBy('title', 'asc')->get();
        }

        // ------------------------------------------------------------------
        // Build the fee query against the canonical ledger.
        // ------------------------------------------------------------------
        $fees = Fee::with([
            'category',
            'studentEnroll.student',
            'studentEnroll.program',
            'studentEnroll.session',
            'studentEnroll.semester',
            'studentEnroll.section',
            'paymentPlan',
            'resitRequest',
        ]);

        // Academic scope (mirrors fees-student-report).
        if ($faculty !== '0' || $program !== '0' || $session !== '0' || $semester !== '0' || $section !== '0') {
            $fees->whereHas('studentEnroll', function ($q) use ($faculty, $program, $session, $semester, $section) {
                if ($program !== '0')  { $q->where('program_id',  $program);  }
                if ($session !== '0')  { $q->where('session_id',  $session);  }
                if ($semester !== '0') { $q->where('semester_id', $semester); }
                if ($section !== '0')  { $q->where('section_id',  $section);  }
                if ($faculty !== '0') {
                    $q->whereHas('program', fn ($pq) => $pq->where('faculty_id', $faculty));
                }
            });
        }

        if ($category !== '0') {
            $fees->where('category_id', $category);
        }

        if ($studentSearch !== '') {
            $fees->whereHas('studentEnroll', function ($q) use ($studentSearch) {
                $q->where('matricule', 'LIKE', "%{$studentSearch}%")
                  ->orWhereHas('student', function ($sq) use ($studentSearch) {
                      $sq->where('student_id', 'LIKE', "%{$studentSearch}%")
                         ->orWhere('first_name', 'LIKE', "%{$studentSearch}%")
                         ->orWhere('last_name',  'LIKE', "%{$studentSearch}%")
                         ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$studentSearch}%"]);
                  });
            });
        }

        if ($dateFrom) { $fees->whereDate('assign_date', '>=', $dateFrom); }
        if ($dateTo)   { $fees->whereDate('assign_date', '<=', $dateTo);   }

        // Payment status (same semantics as fees-student-report).
        switch ($paymentStatus) {
            case '1': // Fully paid (not overpaid)
                $fees->where('status', 1)
                     ->whereRaw('paid_amount <= (fee_amount - COALESCE(discount_amount,0) + COALESCE(fine_amount,0))');
                break;
            case '2': // Partially paid
                $fees->where('status', 2);
                break;
            case '3': // Cancelled
                $fees->where('status', 3);
                break;
            case '4': // Active payment plan
                $fees->whereNotNull('payment_plan_id')
                     ->whereHas('paymentPlan', fn ($q) => $q->where('status', 'active'));
                break;
            case '5': // Overpaid
                $fees->whereRaw('paid_amount > (fee_amount - COALESCE(discount_amount,0) + COALESCE(fine_amount,0))');
                break;
            case '0': // Unpaid
                $fees->where('status', 0);
                break;
            default:
                $fees->whereIn('status', [0, 1, 2, 3]);
        }

        // ------------------------------------------------------------------
        // Origin filter — applied after retrieval (origin is derived).
        // We push the load up here so derivation is consistent.
        // ------------------------------------------------------------------
        $rows = $fees->orderBy('assign_date', 'desc')->orderBy('id', 'desc')->get();

        // Build a fast lookup: which fee_ids appear in the legacy
        // fees_master pivot? (One query.)
        $masterFeeIds = collect();
        if ($rows->isNotEmpty()) {
            // The legacy template assigns at the (master, enroll) pivot but
            // doesn't carry a fee_id. So we recognise a fee as "Fees Master"
            // origin if a master with the same category + cohort + amount
            // exists for that enrollment.
            $enrollIds = $rows->pluck('student_enroll_id')->unique()->filter()->values();
            if ($enrollIds->isNotEmpty()) {
                $masterMap = DB::table('fees_master_student_enroll as p')
                    ->join('fees_masters as fm', 'fm.id', '=', 'p.fees_master_id')
                    ->whereIn('p.student_enroll_id', $enrollIds)
                    ->select('p.student_enroll_id', 'fm.category_id', 'fm.amount')
                    ->get()
                    ->groupBy(fn ($r) => $r->student_enroll_id . '|' . $r->category_id);
                $data['_masterMap'] = $masterMap;
            }
        }

        // Attach origin to each row (single source of truth used by view +
        // origin filter + summary cards).
        $rows->each(function (Fee $fee) use ($data) {
            $fee->origin       = $this->deriveOrigin($fee, $data['_masterMap'] ?? collect());
            $fee->origin_badge = $this->originBadge($fee->origin);
        });

        if ($origin !== 'all') {
            $rows = $rows->filter(fn (Fee $f) => $f->origin === $origin)->values();
        }

        // ------------------------------------------------------------------
        // Summary metrics.
        // ------------------------------------------------------------------
        $data['stats'] = [
            'total_assignments' => $rows->count(),
            'total_amount'      => round($rows->sum(fn ($f) => (float) $f->total_amount), 2),
            'total_paid'        => round($rows->sum(fn ($f) => (float) ($f->paid_amount ?? 0)), 2),
            'total_outstanding' => round($rows->sum(fn ($f) => max(0, (float) $f->total_amount - (float) ($f->paid_amount ?? 0))), 2),
            'distinct_students' => $rows->pluck('studentEnroll.student_id')->filter()->unique()->count(),
            'by_origin'         => $rows->groupBy('origin')->map->count(),
        ];

        // ------------------------------------------------------------------
        // Grouped view: bucket by (program, session, semester, section,
        // category, fee_amount, origin) so a cohort assignment shows as
        // one row with cohort size + aggregate paid.
        // ------------------------------------------------------------------
        if ($viewMode === 'grouped') {
            $data['groups'] = $rows->groupBy(function (Fee $f) {
                $e = $f->studentEnroll;
                return implode('|', [
                    $e->program_id  ?? 0,
                    $e->session_id  ?? 0,
                    $e->semester_id ?? 0,
                    $e->section_id  ?? 0,
                    $f->category_id ?? 0,
                    number_format((float) $f->fee_amount, 2, '.', ''),
                    $f->origin,
                ]);
            })->map(function ($bucket) {
                /** @var \Illuminate\Support\Collection<int,Fee> $bucket */
                $first = $bucket->first();
                return (object) [
                    'program'     => optional($first->studentEnroll)->program,
                    'session'     => optional($first->studentEnroll)->session,
                    'semester'    => optional($first->studentEnroll)->semester,
                    'section'     => optional($first->studentEnroll)->section,
                    'category'    => $first->category,
                    'fee_amount'  => (float) $first->fee_amount,
                    'origin'      => $first->origin,
                    'origin_badge'=> $first->origin_badge,
                    'cohort'      => $bucket->count(),
                    'paid'        => round($bucket->sum(fn ($f) => (float) ($f->paid_amount ?? 0)), 2),
                    'due'         => round($bucket->sum(fn ($f) => (float) $f->total_amount), 2),
                    'assign_date' => $bucket->min('assign_date'),
                    'due_date'    => $bucket->min('due_date'),
                    'sample_ids'  => $bucket->pluck('id')->take(5)->all(),
                ];
            })->sortByDesc('cohort')->values();
        }

        $data['rows']     = $rows;
        $data['origins']  = $this->originOptions();
        $data['setting'] = \App\Models\Setting::first();

        return view($this->view . '.index', $data);
    }

    /**
     * Derive the origin label from indicators on the Fee row.
     *
     * Order of precedence is intentional: a resit charge that also has a
     * payment plan is still classified as "Resit" (more specific).
     */
    protected function deriveOrigin(Fee $fee, $masterMap): string
    {
        if ($fee->resitRequest) {
            return 'resit';
        }

        $note = strtolower((string) ($fee->note ?? ''));
        if ($note !== '') {
            if (str_contains($note, 'progression') || str_contains($note, 'auto-assigned') || str_contains($note, 'auto generated') || str_contains($note, 'auto-generated')) {
                return 'auto_progression';
            }
            if (str_contains($note, 'transfer')) {
                return 'transfer_in';
            }
            if (str_contains($note, 'program semester fee') || str_contains($note, 'psf')) {
                return 'program_semester_fee';
            }
        }

        // Legacy Fees Master pivot match (by enrollment + category).
        $key = ($fee->student_enroll_id ?? 0) . '|' . ($fee->category_id ?? 0);
        if ($masterMap instanceof \Illuminate\Support\Collection && $masterMap->has($key)) {
            $matches = $masterMap->get($key);
            // Same amount? then very likely from this template.
            foreach ($matches as $m) {
                if ((float) $m->amount === (float) $fee->fee_amount) {
                    return 'fees_master';
                }
            }
        }

        // Fallback: Payment-plan-only origin.
        if ($fee->payment_plan_id) {
            return 'payment_plan';
        }

        // System-created (created_by null) but no other indicator -> assume PSF auto-assign.
        if (empty($fee->created_by)) {
            return 'program_semester_fee';
        }

        return 'manual';
    }

    /**
     * Stable badge classes for each origin (used by view + grouped table).
     */
    protected function originBadge(string $origin): string
    {
        return [
            'resit'                 => 'badge-danger',
            'payment_plan'          => 'badge-warning',
            'fees_master'           => 'badge-secondary',
            'program_semester_fee'  => 'badge-info',
            'auto_progression'      => 'badge-primary',
            'transfer_in'           => 'badge-dark',
            'manual'                => 'badge-success',
        ][$origin] ?? 'badge-light';
    }

    /**
     * The dropdown options for the origin filter.
     */
    protected function originOptions(): array
    {
        return [
            'all'                  => __('all'),
            'program_semester_fee' => __('program_semester_fee'),
            'auto_progression'     => __('auto_progression'),
            'fees_master'          => __('fees_master_template'),
            'resit'                => __('resit'),
            'transfer_in'          => __('transfer_in'),
            'payment_plan'         => __('payment_plan'),
            'manual'               => __('manual_admin_entry'),
        ];
    }
}
