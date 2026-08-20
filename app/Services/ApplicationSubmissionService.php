<?php

namespace App\Services;

use App\Models\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Submitting an application without a form post.
 *
 * The applicant fills the wizard, and only once it is complete are they allowed
 * to pay (see ApplicationCompleteness and the payment gate that uses it). When
 * the admission fee is then approved — by mobile money, by an admin verifying an
 * uploaded receipt, or by a walk-in payment taken at the counter — there is
 * nothing left for the applicant to do, so the application submits itself.
 *
 * Web\ApplicationController::update() is the manual equivalent, but it cannot be
 * reused: it is built around a request, validating and persisting a posted form.
 * By the time this runs the data is already saved and there is no request at all
 * — the trigger is an admin approving a payment. So this performs only the state
 * transition, and re-checks every condition update() checks before making it.
 */
class ApplicationSubmissionService
{
    /**
     * Submit an application that has met every condition, and report whether it did.
     *
     * Safe to call as often as you like: it is a no-op for anything that is not a
     * draft, so a fee saved twice cannot submit twice.
     *
     * @param  string  $reason  Recorded on the timeline, e.g. 'admission fee approved'.
     */
    public function autoSubmit(Application $application, string $reason): bool
    {
        foreach ($this->blockers($application) as $blocker) {
            // Not an error: most calls land here, because most fees are saved for
            // applications that are already submitted or not yet finished.
            Log::debug('Auto-submit skipped', [
                'application_id' => $application->id,
                'blocker' => $blocker,
            ]);
            return false;
        }

        try {
            DB::transaction(function () use ($application, $reason) {
                // Re-read inside the transaction and lock, so two payment
                // callbacks arriving together cannot both see a draft.
                $fresh = Application::whereKey($application->getKey())->lockForUpdate()->first();
                if (!$fresh || $fresh->stage !== 'draft') {
                    return;
                }

                $meta = $fresh->portal_meta;
                $meta = is_array($meta) ? $meta : (array) json_decode((string) $meta, true);
                $meta['submitted_automatically'] = true;
                $meta['submitted_reason'] = $reason;
                $meta['submitted_at'] = now()->toDateTimeString();
                // Deliberately no submitted_ip / submitted_user_agent: nobody
                // browsed anything. Recording the admin's address as though it
                // were the applicant's would be worse than recording nothing.

                $fresh->status = 1;
                $fresh->stage = 'submitted';
                // The date the application was made. The admin register filters
                // on it, so an application submitted without one is invisible
                // there however complete it is.
                $fresh->apply_date = $fresh->apply_date ?: now()->toDateString();
                $fresh->progress = Application::stageProgressMap()['submitted'];
                $fresh->portal_meta = $meta;
                $fresh->save();

                $fresh->recordStatus(
                    'submitted',
                    __('Your application was submitted automatically once your :reason.', ['reason' => $reason]),
                    1,
                    __('application_stage.submitted'),
                    null,
                    'system'
                );

                $application->setRawAttributes($fresh->getAttributes(), true);
            });
        } catch (\Throwable $e) {
            // A payment must never fail because the submission that follows it
            // did. Report and leave the application a draft; the applicant can
            // still submit by hand, and the fee stays paid either way.
            report($e);
            return false;
        }

        return $application->stage === 'submitted';
    }

    /**
     * Why this application cannot be submitted right now — empty when it can.
     *
     * The same list gates the payment step, so an applicant is never allowed to
     * pay for something that could not then be submitted.
     *
     * @return array<int, string>
     */
    public function blockers(Application $application): array
    {
        $blockers = [];

        if ($application->stage !== 'draft') {
            $blockers[] = 'not a draft (stage: ' . $application->stage . ')';
            // Nothing below is meaningful for an application already in flight.
            return $blockers;
        }

        $intake = $application->session;
        if (!$intake || !$intake->applications_open) {
            $blockers[] = 'intake is closed';
        }

        if (!$this->admissionFeeIsSettled($application)) {
            $blockers[] = 'admission fee is not settled';
        }

        $missing = ApplicationCompleteness::missingLabels($application);
        if ($missing) {
            $blockers[] = 'incomplete: ' . implode(', ', $missing);
        }

        return $blockers;
    }

    /**
     * Whether the admission fee is fully paid.
     *
     * Mirrors Web\ApplicationController::admissionFeeIsSettled(), including its
     * rule that a degree type charging no fee counts as settled. It is repeated
     * rather than shared because that one is a protected controller method; if a
     * third caller appears, move both onto the Application model.
     */
    public function admissionFeeIsSettled(Application $application): bool
    {
        $settings = DegreeTypeFormConfig::settings($application->degreeType);
        if (empty($settings['fee_enabled'])) {
            return true;
        }

        $fee = $application->admissionFee()->first();

        return $fee && (int) $fee->status === 1;
    }
}
