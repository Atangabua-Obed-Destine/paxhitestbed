<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationApproval;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way an application's approvals are written, and the only way its
 * stage moves.
 *
 * Before this, the stage was a dropdown on the edit form and another on the
 * status-update form: anyone who could edit an application could set it to
 * approved, and a student record could then be created from it. Both dropdowns
 * are gone. The stage is now a consequence of the four approvals, each of them
 * held by whoever the school gives the permission to.
 *
 * Three things are refused here rather than merely discouraged in the interface:
 * approving a step you do not hold the permission for, approving out of order,
 * and rejecting at a step that cannot reject. A disabled button is a courtesy;
 * this is the rule.
 */
class ApplicationApprovalService
{
    /**
     * What the screen shows: every step, its standing, and who signed it.
     *
     * @return array{steps: array<int, array>, current: ?string, rejected: bool, complete: bool}
     */
    public function state(Application $application): array
    {
        $application->load('approvals.decidedBy');

        $live = $application->liveApprovals();
        $current = $application->currentApprovalStep();
        $rejected = $application->isApprovalRejected();

        $steps = [];

        foreach (Application::approvalStepMap() as $key => $definition) {
            $approval = $live->last(fn ($row) => $row->step === $key && $row->decision === ApplicationApproval::APPROVED);

            $steps[] = [
                'key' => $key,
                'title' => Application::approvalStepTitle($key),
                'sequence' => $definition['sequence'],
                'permission' => $definition['permission'],
                'can_reject' => $definition['can_reject'],
                'approved' => $approval !== null,
                'approval' => $approval,
                'is_current' => $current === $key,
                'state' => $approval ? 'approved' : ($current === $key ? 'current' : 'waiting'),
            ];
        }

        return [
            'steps' => $steps,
            'current' => $current,
            'rejected' => $rejected,
            'complete' => $application->isFullyApproved(),
            'history' => $application->approvals,
        ];
    }

    /**
     * Give a step's approval.
     *
     * @throws ValidationException when the user may not, or the step is not the one waiting
     */
    public function approve(Application $application, string $step, User $user, ?string $note = null): ApplicationApproval
    {
        $definition = $this->definition($step);

        $this->assertMay($user, $definition['permission'], $step);
        $this->assertIsCurrent($application, $step);

        return DB::transaction(function () use ($application, $step, $definition, $user, $note) {
            $approval = $this->record($application, $step, ApplicationApproval::APPROVED, $user, $note);

            // The applicant's timeline and the audit trail stay where they have
            // always been. Nothing here keeps a second record of its own.
            //
            // The decision status travels with the final approval, and only
            // with it: the applications list offers the acceptance letter, and
            // filters by Approved, on that column.
            $application->recordStatus(
                $definition['stage'],
                $note ?: __('application_approval.approved_note', ['step' => Application::approvalStepTitle($step)]),
                $definition['status'] ?? $application->status,
                Application::approvalStepTitle($step),
                $user->id,
                'admin',
                (bool) $definition['visible']
            );

            return $approval;
        });
    }

    /**
     * Refuse the application outright. Only where the step allows it, and never
     * without a reason — the applicant is told, and the school has to be able
     * to say why.
     */
    public function reject(Application $application, string $step, User $user, string $reason): ApplicationApproval
    {
        $definition = $this->definition($step);

        $this->assertMay($user, $definition['permission'], $step);
        $this->assertIsCurrent($application, $step);

        if (!$definition['can_reject']) {
            throw ValidationException::withMessages([
                'decision' => __('application_approval.cannot_reject', ['step' => Application::approvalStepTitle($step)]),
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['note' => __('application_approval.reason_required')]);
        }

        return DB::transaction(function () use ($application, $step, $user, $reason) {
            $approval = $this->record($application, $step, ApplicationApproval::REJECTED, $user, $reason);

            $application->recordStatus(
                'decision_rejected',
                $reason,
                0,
                __('application_stage.decision_rejected'),
                $user->id,
                'admin',
                true
            );

            return $approval;
        });
    }

    /**
     * Send the application back to an earlier step.
     *
     * This is the answer to a missing document, not a rejection. Returning to
     * the documents step puts the application into documents_required, which is
     * the stage the applicant's own resubmission flow already watches for — it
     * moves the application back to under_review by itself once they upload.
     */
    public function returnTo(Application $application, string $fromStep, string $targetStep, User $user, string $reason): ApplicationApproval
    {
        $from = $this->definition($fromStep);
        $target = $this->definition($targetStep);

        $this->assertMay($user, $from['permission'], $fromStep);
        $this->assertIsCurrent($application, $fromStep);

        if ($target['sequence'] > $from['sequence']) {
            throw ValidationException::withMessages([
                'returned_to_step' => __('application_approval.return_forward'),
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['note' => __('application_approval.reason_required')]);
        }

        return DB::transaction(function () use ($application, $fromStep, $targetStep, $user, $reason) {
            $approval = $this->record(
                $application,
                $fromStep,
                ApplicationApproval::RETURNED,
                $user,
                $reason,
                $targetStep
            );

            $stage = $targetStep === 'documents' ? 'documents_required' : 'under_review';

            $application->recordStatus(
                $stage,
                $reason,
                $application->status,
                __('application_approval.returned_title', ['step' => Application::approvalStepTitle($targetStep)]),
                $user->id,
                'admin',
                true
            );

            return $approval;
        });
    }

    protected function record(
        Application $application,
        string $step,
        string $decision,
        User $user,
        ?string $note,
        ?string $returnedTo = null
    ): ApplicationApproval {
        return $application->approvals()->create([
            'step' => $step,
            'decision' => $decision,
            'decided_by' => $user->id,
            // Copied in, not looked up later: the record must keep saying what
            // was true when it was signed.
            'signed_name' => $user->name,
            'signed_position' => optional($user->designation)->title,
            'returned_to_step' => $returnedTo,
            'note' => $note,
            'decided_at' => now(),
        ]);
    }

    protected function definition(string $step): array
    {
        $map = Application::approvalStepMap();

        if (!array_key_exists($step, $map)) {
            throw ValidationException::withMessages(['step' => __('application_approval.unknown_step')]);
        }

        return $map[$step];
    }

    protected function assertMay(User $user, string $permission, string $step): void
    {
        if (!$user->can($permission)) {
            throw ValidationException::withMessages([
                'step' => __('application_approval.not_permitted', ['step' => Application::approvalStepTitle($step)]),
            ]);
        }
    }

    /**
     * No skipping ahead. A final approval given before the documents have been
     * verified is exactly what the paper form allowed and this replaces.
     */
    protected function assertIsCurrent(Application $application, string $step): void
    {
        $current = $application->currentApprovalStep();

        if ($current === $step) {
            return;
        }

        if ($application->isApprovalRejected()) {
            throw ValidationException::withMessages(['step' => __('application_approval.already_rejected')]);
        }

        if ($current === null) {
            throw ValidationException::withMessages(['step' => __('application_approval.already_complete')]);
        }

        throw ValidationException::withMessages([
            'step' => __('application_approval.not_current', [
                'step' => Application::approvalStepTitle($step),
                'current' => Application::approvalStepTitle($current),
            ]),
        ]);
    }
}
