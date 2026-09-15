<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\ApplicationApprovalService;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The admission approvals, given on the system.
 *
 * Every route requires application-edit to get this far, but that is not what
 * authorises the decision — each step has its own permission, checked in
 * ApplicationApprovalService against the step being decided. Holding
 * application-edit lets you see the panel; it does not let you sign anything.
 */
class ApplicationApprovalController extends Controller
{
    protected ApplicationApprovalService $approvals;

    public function __construct(ApplicationApprovalService $approvals)
    {
        $this->approvals = $approvals;

        $this->middleware('permission:application-edit');
    }

    public function approve(Request $request, Application $application)
    {
        $validated = $request->validate([
            'step' => ['required', Rule::in(Application::approvalStepKeys())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt(function () use ($application, $validated) {
            $this->approvals->approve(
                $application,
                $validated['step'],
                Auth::guard('web')->user(),
                $validated['note'] ?? null
            );

            Flasher::addSuccess(
                __(':step given.', ['step' => Application::approvalStepTitle($validated['step'])]),
                __('msg_success')
            );
        });
    }

    public function reject(Request $request, Application $application)
    {
        $validated = $request->validate([
            'step' => ['required', Rule::in(Application::approvalStepKeys())],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        return $this->attempt(function () use ($application, $validated) {
            $this->approvals->reject(
                $application,
                $validated['step'],
                Auth::guard('web')->user(),
                $validated['note']
            );

            Flasher::addSuccess(__('The application was refused and the applicant told.'), __('msg_success'));
        });
    }

    public function returnToStep(Request $request, Application $application)
    {
        $validated = $request->validate([
            'step' => ['required', Rule::in(Application::approvalStepKeys())],
            'returned_to_step' => ['required', Rule::in(Application::approvalStepKeys())],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        return $this->attempt(function () use ($application, $validated) {
            $this->approvals->returnTo(
                $application,
                $validated['step'],
                $validated['returned_to_step'],
                Auth::guard('web')->user(),
                $validated['note']
            );

            Flasher::addSuccess(
                __('Returned for :step.', [
                    'step' => Application::approvalStepTitle($validated['returned_to_step']),
                ]),
                __('msg_success')
            );
        });
    }

    /**
     * The service refuses by throwing, and those refusals are the point of the
     * screen — a permission the user does not hold, a step out of turn, a
     * rejection with no reason. They are shown as errors on the page rather
     * than as a stack trace, and the redirect goes back to the application.
     */
    protected function attempt(callable $action)
    {
        try {
            $action();
        } catch (ValidationException $e) {
            Flasher::addError(collect($e->errors())->flatten()->first(), __('msg_error'));

            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back();
    }
}
