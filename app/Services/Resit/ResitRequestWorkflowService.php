<?php

namespace App\Services\Resit;

use App\Models\ResitRequest;
use App\Models\ResitRequestWorkflowLog;
use App\Models\Semester;
use App\Models\Session;
use App\Services\Resit\ResitEnrollmentService;
use App\Services\Academic\SemesterProgressionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ResitRequestWorkflowService
{
    protected ResitEnrollmentService $resitEnrollmentService;
    protected SemesterProgressionService $progressionService;

    public function __construct(
        ResitEnrollmentService $resitEnrollmentService,
        SemesterProgressionService $progressionService
    ) {
        $this->resitEnrollmentService = $resitEnrollmentService;
        $this->progressionService = $progressionService;
    }
    /**
     * Ordered list of workflow states.
     */
    public const STATES = [
        ResitRequest::STATE_REQUESTED,
        ResitRequest::STATE_AWAITING_PAYMENT,
        ResitRequest::STATE_APPROVED,
        ResitRequest::STATE_SCHEDULED,
        ResitRequest::STATE_REJECTED,
    ];

    /**
     * Map of permissible transitions.
     */
    protected array $transitions = [
        ResitRequest::STATE_REQUESTED => [
            ResitRequest::STATE_AWAITING_PAYMENT,
            ResitRequest::STATE_APPROVED,
            ResitRequest::STATE_REJECTED,
        ],
        ResitRequest::STATE_AWAITING_PAYMENT => [
            ResitRequest::STATE_APPROVED,
            ResitRequest::STATE_REJECTED,
        ],
        ResitRequest::STATE_APPROVED => [
            ResitRequest::STATE_SCHEDULED,
        ],
        ResitRequest::STATE_SCHEDULED => [],
        ResitRequest::STATE_REJECTED => [],
    ];

    /**
     * Permissions required to move into a given state.
     */
    protected array $statePermissions = [
        ResitRequest::STATE_AWAITING_PAYMENT => 'resit-request-finance',
        ResitRequest::STATE_APPROVED => 'resit-request-approve',
        ResitRequest::STATE_SCHEDULED => 'resit-request-schedule',
        ResitRequest::STATE_REJECTED => 'resit-request-reject',
    ];

    /**
     * Attempt to move the resit request to the target state.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function transition(ResitRequest $request, string $toState, array $context = []): ResitRequest
    {
        $toState = Str::lower($toState);
        $this->assertValidState($toState);
        $this->assertAuthorized($toState, $context);

        $fromState = $request->workflow_state ?: ResitRequest::STATE_REQUESTED;
        if (!$this->canTransition($fromState, $toState, $request)) {
            throw ValidationException::withMessages([
                'state' => __('Invalid workflow transition.'),
            ]);
        }

        $userId = $context['actor_id'] ?? Auth::guard('web')->id();

        return DB::transaction(function () use ($request, $fromState, $toState, $userId, $context) {
            $this->assertBusinessRules($request, $fromState, $toState, $context);

            $request->workflow_state = $toState;
            $request->state_changed_at = now();
            $request->state_changed_by = $userId;

            if ($toState === ResitRequest::STATE_APPROVED) {
                $request->approved_by = $userId;
                $request->approved_at = now();
                
                // AUTO-SCHEDULE: After approval, automatically schedule to next available resit semester
                $autoScheduleResult = $this->autoScheduleResit($request);
                if ($autoScheduleResult['scheduled']) {
                    // Add session and semester to context for validation
                    $context['resit_session_id'] = $autoScheduleResult['session_id'];
                    $context['resit_semester_id'] = $autoScheduleResult['semester_id'];
                    
                    // Update state to scheduled
                    $toState = ResitRequest::STATE_SCHEDULED;
                    $request->workflow_state = ResitRequest::STATE_SCHEDULED;
                    $request->resit_session_id = $autoScheduleResult['session_id'];
                    $request->resit_semester_id = $autoScheduleResult['semester_id'];
                    
                    Log::info("Auto-scheduled resit request {$request->id} to session {$autoScheduleResult['session_id']}, semester {$autoScheduleResult['semester_id']}");
                } else {
                    // If auto-scheduling fails, stay in APPROVED state (don't throw error)
                    Log::warning("Could not auto-schedule resit request {$request->id} - staying in APPROVED state. Session: {$autoScheduleResult['session_id']}, Semester: {$autoScheduleResult['semester_id']}");
                }
            }

            if ($toState === ResitRequest::STATE_SCHEDULED) {
                $sessionId = $context['resit_session_id'] ?? null;
                $semesterId = $context['resit_semester_id'] ?? null;

                if (!$sessionId) {
                    throw ValidationException::withMessages([
                        'resit_session_id' => __('Resit session is required to schedule the resit.'),
                    ]);
                }

                if (!$semesterId) {
                    throw ValidationException::withMessages([
                        'resit_semester_id' => __('Resit semester is required to schedule the resit.'),
                    ]);
                }

                $semester = Semester::find($semesterId);
                if (!$semester || !$semester->is_resit) {
                    throw ValidationException::withMessages([
                        'resit_semester_id' => __('Selected semester must be marked as a resit semester.'),
                    ]);
                }

                $request->resit_session_id = (int) $sessionId;
                $request->resit_semester_id = $semester->id;
            }

            if (array_key_exists('notes', $context)) {
                $request->notes = $context['notes'] ?? null;
            }

            $request->save();

            ResitRequestWorkflowLog::create([
                'resit_request_id' => $request->id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'changed_by' => $userId,
                'notes' => $context['notes'] ?? null,
                'changed_at' => now(),
                'meta' => $this->buildMetaPayload($context),
            ]);
            
            if ($toState === ResitRequest::STATE_SCHEDULED) {
                // Auto-progress to resit semester and incrementally register courses
                $this->autoProgressToResitSemester($request);
            }

            return $request->refresh();
        });
    }

    public function canTransition(string $fromState, string $toState, ?ResitRequest $request = null): bool
    {
        $fromState = Str::lower($fromState);
        $toState = Str::lower($toState);

        if (!in_array($toState, $this->transitions[$fromState] ?? [], true)) {
            return false;
        }

        if ($request instanceof ResitRequest && $toState === ResitRequest::STATE_APPROVED) {
            return $request->paymentSettled();
        }

        return true;
    }

    public function getPermittedNextStatesFor(ResitRequest $request): array
    {
        $currentState = $request->workflow_state ?: ResitRequest::STATE_REQUESTED;

        return collect($this->transitions[$currentState] ?? [])
            ->filter(function (string $state) use ($request) {
                if (!$this->canTransition($request->workflow_state, $state, $request)) {
                    return false;
                }

                $permission = $this->getRequiredPermissionFor($state);
                if (!$permission) {
                    return true;
                }

                /** @var \App\User|null $user */
                $user = Auth::guard('web')->user();

                return $user && $user->can($permission);
            })
            ->values()
            ->all();
    }

    public function getRequiredPermissionFor(string $state): ?string
    {
        $state = Str::lower($state);

        return $this->statePermissions[$state] ?? null;
    }

    protected function assertBusinessRules(ResitRequest $request, string $fromState, string $toState, array $context): void
    {
        if ($toState === ResitRequest::STATE_APPROVED && !$request->paymentSettled()) {
            throw ValidationException::withMessages([
                'payment_status' => __('Resit fee must be settled before approval.'),
            ]);
        }

        if ($toState === ResitRequest::STATE_SCHEDULED) {
            if (empty($context['resit_session_id'])) {
                throw ValidationException::withMessages([
                    'resit_session_id' => __('Resit session is required to schedule the request.'),
                ]);
            }

            $semesterId = $context['resit_semester_id'] ?? null;
            if (!$semesterId) {
                throw ValidationException::withMessages([
                    'resit_semester_id' => __('Resit semester is required to schedule the request.'),
                ]);
            }

            $semester = Semester::find($semesterId);
            if (!$semester || !$semester->is_resit) {
                throw ValidationException::withMessages([
                    'resit_semester_id' => __('Selected semester must be a designated resit semester.'),
                ]);
            }
        }
    }

    protected function assertAuthorized(string $state, array $context = []): void
    {
        if (!empty($context['auto'])) {
            return;
        }

        $permission = $this->getRequiredPermissionFor($state);
        if (!$permission) {
            return;
        }

        /** @var \App\User|null $user */
        $user = Auth::guard('web')->user();

        if (!$user || !$user->can($permission)) {
            throw new AuthorizationException(__('You do not have permission to perform this transition.'));
        }
    }

    protected function assertValidState(string $state): void
    {
        if (!in_array($state, self::STATES, true)) {
            throw ValidationException::withMessages([
                'state' => __('Unknown workflow state.'),
            ]);
        }
    }

    protected function buildMetaPayload(array $context): ?array
    {
        $meta = [];

        if (isset($context['resit_session_id'])) {
            $meta['resit_session_id'] = (int) $context['resit_session_id'];
        }

        if (isset($context['resit_semester_id'])) {
            $meta['resit_semester_id'] = (int) $context['resit_semester_id'];
        }

        if (empty($meta)) {
            return null;
        }

        return $meta;
    }
    
    /**
     * Automatically schedule resit to the next available resit semester
     * 
     * @param ResitRequest $request
     * @return array ['scheduled' => bool, 'session_id' => int|null, 'semester_id' => int|null]
     */
    protected function autoScheduleResit(ResitRequest $request): array
    {
        try {
            $originalEnrollment = $request->studentEnroll;
            if (!$originalEnrollment) {
                Log::warning("Cannot auto-schedule resit {$request->id}: No original enrollment found");
                return ['scheduled' => false, 'session_id' => null, 'semester_id' => null];
            }
            
            // Find appropriate resit semester that matches the failed semester's year and type
            // For example: if failed in "SECOND SEMESTER Y1" (Year 1, Type 2), 
            // should get "2nd RESIT SEMESTER Y1" (Year 1, Type 2, is_resit=1)
            $failedSemester = $originalEnrollment->semester;
            
            $resitSemester = Semester::where('is_resit', 1)
                ->where('status', 1)
                ->where('year', $failedSemester->year)
                ->where('semester_type', $failedSemester->semester_type)
                ->whereHas('programs', function($query) use ($originalEnrollment) {
                    $query->where('program_id', $originalEnrollment->program_id);
                })
                ->first();
            
            // If no program-specific matching resit semester found, try to find any matching resit semester
            if (!$resitSemester) {
                Log::info("No program-specific resit semester for program {$originalEnrollment->program_id}, year {$failedSemester->year}, type {$failedSemester->semester_type}, trying to find any active matching resit semester");
                
                $resitSemester = Semester::where('is_resit', 1)
                    ->where('status', 1)
                    ->where('year', $failedSemester->year)
                    ->where('semester_type', $failedSemester->semester_type)
                    ->first();
                
                if (!$resitSemester) {
                    Log::warning("Cannot auto-schedule resit {$request->id}: No active resit semester found matching year {$failedSemester->year}, type {$failedSemester->semester_type}");
                    return ['scheduled' => false, 'session_id' => null, 'semester_id' => null];
                }
                
                Log::info("Using general resit semester {$resitSemester->id} ({$resitSemester->title}) for resit request {$request->id}");
            }
            
            // Use the same session as original enrollment (or find current active session)
            $sessionId = $originalEnrollment->session_id;
            $session = Session::find($sessionId);
            
            if (!$session) {
                // Fallback to latest active session
                $session = Session::where('status', 1)->orderByDesc('id')->first();
                if (!$session) {
                    Log::warning("Cannot auto-schedule resit {$request->id}: No active session found");
                    return ['scheduled' => false, 'session_id' => null, 'semester_id' => null];
                }
                $sessionId = $session->id;
            }
            
            Log::info("Auto-scheduling resit request {$request->id} to session {$sessionId}, semester {$resitSemester->id}");
            
            return [
                'scheduled' => true,
                'session_id' => $sessionId,
                'semester_id' => $resitSemester->id,
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to auto-schedule resit {$request->id}: " . $e->getMessage());
            return ['scheduled' => false, 'session_id' => null, 'semester_id' => null];
        }
    }
    
    /**
     * Auto-progress to resit semester and incrementally register courses
     * 
     * @param ResitRequest $request
     * @return void
     */
    protected function autoProgressToResitSemester(ResitRequest $request): void
    {
        try {
            $studentEnroll = $request->studentEnroll;
            if (!$studentEnroll) return;
            
            $student = $studentEnroll->student;
            $resitSemester = Semester::find($request->resit_semester_id);
            if (!$resitSemester) return;

            // Check if student is ALREADY in the resit semester
            $existingResitEnrollment = \App\Models\StudentEnroll::where('student_id', $student->id)
                ->where('program_id', $studentEnroll->program_id)
                ->where('semester_id', $request->resit_semester_id)
                ->where('session_id', $request->resit_session_id)
                ->first();

            $scheduledCourse = [
                'subject_id' => $request->subject_id,
                'subject_code' => $request->subject->code ?? '',
                'subject_title' => $request->subject->title ?? '',
                'resit_session_id' => $request->resit_session_id,
                'resit_semester_id' => $request->resit_semester_id,
            ];

            if ($existingResitEnrollment) {
                // Student is already in the resit semester. Just attach the course incrementally.
                $existingResitEnrollment->subjects()->syncWithoutDetaching([$request->subject_id]);

                // Inherit CA marks
                $this->progressionService->inheritParentSemesterData(
                    $existingResitEnrollment,
                    $resitSemester,
                    [$request->subject_id]
                );

                Log::info("Incrementally added course {$request->subject_id} to existing resit enrollment {$existingResitEnrollment->id}");
            } else {
                // They are not in the resit semester. Progress them now.
                Log::info("Auto-progressing student {$student->id} to resit semester {$resitSemester->id} with course {$request->subject_id}");
                
                $this->progressionService->progressToResitSemester(
                    $studentEnroll,
                    $resitSemester,
                    $request->resit_session_id,
                    [$scheduledCourse]
                );
            }
        } catch (\Exception $e) {
            Log::error("Failed to auto-progress to resit semester: " . $e->getMessage());
        }
    }
}
