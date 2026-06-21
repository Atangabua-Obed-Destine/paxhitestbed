<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResitRequest;
use App\Models\ResitRequestWorkflowLog;
use App\Models\Semester;
use App\Models\Session;
use App\Models\StudentEnroll;
use App\Services\Resit\ResitRequestWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Flasher\Laravel\Facade\Flasher;

class ResitRequestController extends Controller
{
    protected ResitRequestWorkflowService $workflowService;

    public function __construct(ResitRequestWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;

        $this->middleware('permission:resit-request-view', ['only' => ['index']]);
        $this->middleware('permission:resit-request-transition', ['only' => ['transition']]);
        $this->middleware('permission:resit-request-approve', ['only' => ['iGrade', 'cancelResit']]);
    }

    public function index(Request $request)
    {
        // Default to the current active session when no session filter is provided
        $currentSession = Session::where('current', 1)->where('status', 1)->first();
        $sessionId = $request->has('session_id')
            ? ($request->filled('session_id') ? $request->integer('session_id') : null)
            : optional($currentSession)->id;

        $requests = ResitRequest::with([
                'studentEnroll.student',
                'studentEnroll.program',
                'studentEnroll.semester',
                'subject',
                'session',
                'resitSession',
                'approver',
            ])
            ->when($sessionId, function ($query) use ($sessionId) {
                $query->where('session_id', $sessionId);
            })
            ->when($request->filled('year'), function ($query) use ($request) {
                $year = $request->input('year');
                $query->whereHas('studentEnroll.semester', function ($q) use ($year) {
                    $q->where('year', $year);
                });
            })
            ->when($request->filled('semester_type'), function ($query) use ($request) {
                $semesterType = $request->integer('semester_type');
                $query->whereHas('studentEnroll.semester', function ($q) use ($semesterType) {
                    $q->where('semester_type', $semesterType);
                });
            })
            ->when($request->filled('state'), function ($query) use ($request) {
                $query->where('workflow_state', Str::lower($request->input('state')));
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        $workflowMeta = $requests->mapWithKeys(function (ResitRequest $resitRequest) {
            $state = $resitRequest->workflow_state ?: ResitRequest::STATE_REQUESTED;

            return [
                $resitRequest->id => [
                    'state' => $state,
                    'label' => $this->labelForState($state),
                    'badge' => $this->badgeForState($state),
                    'changed_display' => optional($resitRequest->state_changed_at)->format('d M Y H:i'),
                    'transitions' => $this->workflowService->getPermittedNextStatesFor($resitRequest),
                    'can_cancel_resit' => $this->canCancelResit($resitRequest),
                ],
            ];
        });

        // Gather distinct years from semesters for the year filter
        $years = Semester::regular()
            ->whereNotNull('year')
            ->orderBy('year')
            ->distinct()
            ->pluck('year');

        return view('admin.resit-requests.index', [
            'requests'        => $requests,
            'sessions'        => Session::orderByDesc('id')->get(['id', 'title', 'current']),
            'resitSemesters'  => Semester::resit()->orderBy('title')->get(['id', 'title']),
            'years'           => $years,
            'semesterTypes'   => [
                Semester::TYPE_FIRST  => __('First Semester'),
                Semester::TYPE_SECOND => __('Second Semester'),
            ],
            'states'          => ResitRequestWorkflowService::STATES,
            'workflowMeta'    => $workflowMeta,
            'currentSession'  => $currentSession,
            'filters'         => [
                'session_id'    => $sessionId ? (string) $sessionId : '',
                'year'          => $request->input('year'),
                'semester_type' => $request->input('semester_type'),
                'state'         => $request->filled('state') ? Str::lower($request->input('state')) : null,
            ],
        ]);
    }

    public function transition(Request $request, ResitRequest $resitRequest)
    {
        $request->validate([
            'state' => 'required|string',
            'notes' => 'nullable|string|max:500',
            'resit_session_id' => 'nullable|integer|exists:sessions,id',
            'resit_semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $context = $request->only(['notes', 'resit_session_id', 'resit_semester_id']);

        $this->workflowService->transition($resitRequest, $request->input('state'), $context);

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    public function iGrade(Request $request, ResitRequest $resitRequest)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (!in_array($resitRequest->workflow_state, [
            ResitRequest::STATE_REQUESTED,
            ResitRequest::STATE_AWAITING_PAYMENT,
        ], true)) {
            Flasher::addError(__('I-Grade can only be applied to requests awaiting payment.'));
            return redirect()->back();
        }

        DB::transaction(function () use ($resitRequest, $request) {
            // Delete the assigned fee record
            if ($resitRequest->fee_id && $resitRequest->fee) {
                $resitRequest->fee->delete();
            }

            // Waive the fee on the resit request
            $resitRequest->fee_id = null;
            $resitRequest->fee_amount = 0;
            $resitRequest->payment_status = ResitRequest::PAYMENT_WAIVED;
            $resitRequest->save();

            // Transition to APPROVED (auto-schedules to next resit semester)
            $this->workflowService->transition($resitRequest, ResitRequest::STATE_APPROVED, [
                'notes' => 'I-Grade waiver: ' . $request->input('reason'),
                'auto'  => true,
            ]);
        });

        Flasher::addSuccess(__('I-Grade applied successfully. Resit fee waived and student scheduled.'));
        return redirect()->back();
    }

    public function cancelResit(Request $request, ResitRequest $resitRequest)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if ($resitRequest->workflow_state !== ResitRequest::STATE_SCHEDULED) {
            Flasher::addError(__('Only scheduled resit requests can be cancelled.'));
            return redirect()->back();
        }

        // Check student hasn't already progressed to the resit semester
        if ($this->hasStudentProgressedToResit($resitRequest)) {
            Flasher::addError(__('Cannot cancel — the student has already been progressed to the resit semester.'));
            return redirect()->back();
        }

        DB::transaction(function () use ($resitRequest, $request) {
            $oldSessionId = $resitRequest->resit_session_id;
            $oldSemesterId = $resitRequest->resit_semester_id;

            $resitRequest->workflow_state = ResitRequest::STATE_CANCELLED;
            $resitRequest->resit_session_id = null;
            $resitRequest->resit_semester_id = null;
            $resitRequest->state_changed_at = now();
            $resitRequest->state_changed_by = Auth::guard('web')->id();
            $resitRequest->notes = 'Resit cancelled by admin: ' . $request->input('reason');
            $resitRequest->save();

            ResitRequestWorkflowLog::create([
                'resit_request_id' => $resitRequest->id,
                'from_state' => ResitRequest::STATE_SCHEDULED,
                'to_state' => ResitRequest::STATE_CANCELLED,
                'changed_by' => Auth::guard('web')->id(),
                'notes' => 'Resit cancelled by admin: ' . $request->input('reason'),
                'changed_at' => now(),
                'meta' => [
                    'previous_resit_session_id' => $oldSessionId,
                    'previous_resit_semester_id' => $oldSemesterId,
                ],
            ]);
        });

        Flasher::addSuccess(__('Resit request cancelled successfully.'));
        return redirect()->back();
    }

    protected function canCancelResit(ResitRequest $resitRequest): bool
    {
        if ($resitRequest->workflow_state !== ResitRequest::STATE_SCHEDULED) {
            return false;
        }

        return !$this->hasStudentProgressedToResit($resitRequest);
    }

    protected function hasStudentProgressedToResit(ResitRequest $resitRequest): bool
    {
        if (!$resitRequest->resit_semester_id || !$resitRequest->resit_session_id) {
            return false;
        }

        $enrollment = $resitRequest->studentEnroll;
        if (!$enrollment) {
            return false;
        }

        return StudentEnroll::where('student_id', $enrollment->student_id)
            ->where('program_id', $enrollment->program_id)
            ->where('semester_id', $resitRequest->resit_semester_id)
            ->where('session_id', $resitRequest->resit_session_id)
            ->exists();
    }

    protected function labelForState(string $state): string
    {
        return Str::title(str_replace('_', ' ', $state));
    }

    protected function badgeForState(string $state): string
    {
        return match ($state) {
            ResitRequest::STATE_REQUESTED => 'secondary',
            ResitRequest::STATE_AWAITING_PAYMENT => 'warning',
            ResitRequest::STATE_APPROVED => 'primary',
            ResitRequest::STATE_SCHEDULED => 'success',
            ResitRequest::STATE_REJECTED => 'danger',
            default => 'secondary',
        };
    }
}
