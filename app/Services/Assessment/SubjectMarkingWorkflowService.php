<?php

namespace App\Services\Assessment;

use App\Models\SubjectMarking;
use App\Models\SubjectMarkingExamState;
use App\Models\SubjectMarkingWorkflowLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubjectMarkingWorkflowService
{
    /**
     * Ordered list of workflow states.
     */
    public const STATES = [
        SubjectMarking::STATE_DRAFT,
        SubjectMarking::STATE_SUBMITTED,
        SubjectMarking::STATE_CHECKED,
        SubjectMarking::STATE_APPROVED,
        SubjectMarking::STATE_PUBLISHED,
    ];

    /**
     * Map of permissible transitions.
     */
    protected array $transitions = [
        SubjectMarking::STATE_DRAFT => [SubjectMarking::STATE_SUBMITTED],
        SubjectMarking::STATE_SUBMITTED => [SubjectMarking::STATE_CHECKED],
        SubjectMarking::STATE_CHECKED => [SubjectMarking::STATE_APPROVED],
        SubjectMarking::STATE_APPROVED => [SubjectMarking::STATE_PUBLISHED],
        SubjectMarking::STATE_PUBLISHED => [],
    ];

    /**
     * Permission required to drive a marking into the target state.
     */
    protected array $statePermissions = [
        SubjectMarking::STATE_SUBMITTED => 'subject-marking-submit',
        SubjectMarking::STATE_CHECKED => 'subject-marking-check',
        SubjectMarking::STATE_APPROVED => 'subject-marking-approve',
        SubjectMarking::STATE_PUBLISHED => 'subject-marking-publish',
    ];

    /**
     * Attempt to move the marking to the target state.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function transition(SubjectMarking $marking, string $toState, ?string $notes = null): SubjectMarking
    {
        $toState = Str::lower($toState);
        $this->assertValidState($toState);
        $this->assertAuthorized($toState);

        $fromState = $marking->workflow_state ?: SubjectMarking::STATE_DRAFT;
        if (!$this->canTransition($fromState, $toState)) {
            throw ValidationException::withMessages([
                'state' => __('Invalid workflow transition.'),
            ]);
        }

        $userId = Auth::guard('web')->id();

        return DB::transaction(function () use ($marking, $fromState, $toState, $userId, $notes) {
            $marking->workflow_state = $toState;
            $marking->state_changed_at = now();
            $marking->state_changed_by = $userId;

            if ($toState === SubjectMarking::STATE_APPROVED) {
                $marking->reviewed_at = now();
                $marking->reviewed_by = $userId;
                $marking->review_notes = $notes;
            }

            if ($toState === SubjectMarking::STATE_PUBLISHED) {
                $marking->publish_date = $marking->publish_date ?? now()->toDateString();
                $marking->publish_time = $marking->publish_time ?? now()->format('H:i:s');
            }

            $marking->save();

            SubjectMarkingWorkflowLog::create([
                'subject_marking_id' => $marking->id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'changed_by' => $userId,
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            // Cascade transition to all child exam states to maintain consistency
            $marking->load('examStates');
            foreach ($marking->examStates as $examState) {
                if ($examState->workflow_state !== $toState) {
                    $oldExamState = $examState->workflow_state ?: SubjectMarking::STATE_DRAFT;
                    
                    $examState->workflow_state = $toState;
                    $examState->state_changed_at = now();
                    $examState->state_changed_by = $userId;

                    if ($toState === SubjectMarking::STATE_APPROVED) {
                        $examState->reviewed_at = now();
                        $examState->reviewed_by = $userId;
                        $examState->review_notes = $notes;
                    }

                    if ($toState === SubjectMarking::STATE_PUBLISHED) {
                        $examState->publish_date = $examState->publish_date ?? now()->toDateString();
                        $examState->publish_time = $examState->publish_time ?? now()->format('H:i:s');
                    }

                    $examState->save();

                    SubjectMarkingWorkflowLog::create([
                        'subject_marking_id' => $marking->id,
                        'subject_marking_exam_state_id' => $examState->id,
                        'exam_type_id' => $examState->exam_type_id,
                        'from_state' => $oldExamState,
                        'to_state' => $toState,
                        'changed_by' => $userId,
                        'notes' => $notes ? $notes . ' (Cascaded from Overall)' : 'Cascaded from Overall',
                        'changed_at' => now(),
                    ]);
                }
            }

            return $marking->refresh();
        });
    }

    public function transitionExamState(SubjectMarkingExamState $examState, string $toState, ?string $notes = null): SubjectMarkingExamState
    {
        $toState = Str::lower($toState);
        $this->assertValidState($toState);
        $this->assertAuthorized($toState);

        $fromState = $examState->workflow_state ?: SubjectMarking::STATE_DRAFT;
        if (!$this->canTransition($fromState, $toState)) {
            throw ValidationException::withMessages([
                'state' => __('Invalid workflow transition.'),
            ]);
        }

        $userId = Auth::guard('web')->id();

        return DB::transaction(function () use ($examState, $fromState, $toState, $userId, $notes) {
            $marking = $examState->subjectMarking()->lockForUpdate()->first();

            $examState->workflow_state = $toState;
            $examState->state_changed_at = now();
            $examState->state_changed_by = $userId;

            if ($toState === SubjectMarking::STATE_APPROVED) {
                $examState->reviewed_at = now();
                $examState->reviewed_by = $userId;
                $examState->review_notes = $notes;
            }

            if ($toState === SubjectMarking::STATE_PUBLISHED) {
                $examState->publish_date = $examState->publish_date ?? now()->toDateString();
                $examState->publish_time = $examState->publish_time ?? now()->format('H:i:s');
            }

            $examState->save();

            SubjectMarkingWorkflowLog::create([
                'subject_marking_id' => $examState->subject_marking_id,
                'subject_marking_exam_state_id' => $examState->id,
                'exam_type_id' => $examState->exam_type_id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'changed_by' => $userId,
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            if ($marking) {
                $this->syncAggregateState($marking, $userId);
            }

            return $examState->refresh();
        });
    }

    /**
     * Retrieve the next states the current user may transition to.
     */
    public function getPermittedNextStatesFor(SubjectMarking $marking): array
    {
        $currentState = $marking->workflow_state ?: SubjectMarking::STATE_DRAFT;

        return collect($this->getNextStates($currentState))
            ->filter(function (string $state) {
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

    public function getPermittedNextStatesForExamState(SubjectMarkingExamState $examState): array
    {
        $currentState = $examState->workflow_state ?: SubjectMarking::STATE_DRAFT;

        return collect($this->getNextStates($currentState))
            ->filter(function (string $state) {
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

    public function canTransition(string $fromState, string $toState): bool
    {
        $fromState = Str::lower($fromState);
        $toState = Str::lower($toState);

        return in_array($toState, $this->transitions[$fromState] ?? [], true);
    }

    public function ensureExamStates(SubjectMarking $marking, array $examTypeIds): void
    {
        $examTypeIds = array_values(array_unique(array_filter($examTypeIds)));

        if (empty($examTypeIds)) {
            return;
        }

        $existing = $marking->examStates()
            ->whereIn('exam_type_id', $examTypeIds)
            ->pluck('exam_type_id')
            ->all();

        $missing = array_diff($examTypeIds, $existing);

        if (empty($missing)) {
            return;
        }

        $now = now();

        foreach ($missing as $examTypeId) {
            $marking->examStates()->create([
                'exam_type_id' => $examTypeId,
                'workflow_state' => $marking->workflow_state ?: SubjectMarking::STATE_DRAFT,
                'state_changed_at' => $marking->state_changed_at ?? $now,
                'state_changed_by' => $marking->state_changed_by,
            ]);
        }
    }

    public function getNextStates(string $fromState): array
    {
        $fromState = Str::lower($fromState);

        return $this->transitions[$fromState] ?? [];
    }

    public function getRequiredPermissionFor(string $state): ?string
    {
        $state = Str::lower($state);

        return $this->statePermissions[$state] ?? null;
    }

    protected function assertValidState(string $state): void
    {
        if (!in_array($state, self::STATES, true)) {
            throw ValidationException::withMessages([
                'state' => __('Unknown workflow state.'),
            ]);
        }
    }

    protected function assertAuthorized(string $state): void
    {
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

    protected function syncAggregateState(SubjectMarking $marking, ?int $userId = null): void
    {
        $marking->loadMissing('examStates');

        if ($marking->examStates->isEmpty()) {
            return;
        }

        $orderMap = array_flip(self::STATES);

        $minOrder = $marking->examStates
            ->map(function (SubjectMarkingExamState $state) use ($orderMap) {
                return $orderMap[$state->workflow_state] ?? 0;
            })
            ->min();

        $targetState = self::STATES[$minOrder] ?? SubjectMarking::STATE_DRAFT;

        if ($marking->workflow_state === $targetState) {
            return;
        }

        $marking->workflow_state = $targetState;
        $marking->state_changed_at = now();
        $marking->state_changed_by = $userId;

        if ($targetState === SubjectMarking::STATE_APPROVED) {
            $marking->reviewed_at = now();
            $marking->reviewed_by = $userId;
            $marking->review_notes = null;
        }

        if ($targetState === SubjectMarking::STATE_PUBLISHED) {
            $marking->publish_date = $marking->publish_date ?? now()->toDateString();
            $marking->publish_time = $marking->publish_time ?? now()->format('H:i:s');
        }

        $marking->save();
    }
}
