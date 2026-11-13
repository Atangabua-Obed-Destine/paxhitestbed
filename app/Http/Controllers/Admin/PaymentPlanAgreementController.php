<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentPlanAgreement;
use App\Models\Setting;
use App\Services\PaymentPlanAgreements;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A payment plan agreement, from preparation to the Vice Chancellor's decision.
 *
 * The bursar prepares it (PaymentPlanController@store), prints it for the
 * bursar's and the student's signatures, and takes it to the Vice Chancellor,
 * who signs it on paper and approves it here. Anyone with the approve
 * permission may record that decision; the VC role holds it.
 */
class PaymentPlanAgreementController extends Controller
{
    protected PaymentPlanAgreements $agreements;

    public function __construct(PaymentPlanAgreements $agreements)
    {
        $this->agreements = $agreements;

        $this->middleware('permission:payment-plan.show', ['only' => ['show', 'print']]);
        $this->middleware('permission:payment-plan.approve', ['only' => ['approve', 'reject']]);
        $this->middleware('permission:payment-plan.edit', ['only' => ['revise', 'reviseStore']]);
        $this->middleware('permission:payment-plan.cancel', ['only' => ['cancel']]);
    }

    protected function load($id): PaymentPlanAgreement
    {
        return PaymentPlanAgreement::with([
            'student', 'creator', 'approver', 'rejecter', 'events.user',
            'plans.installments.payments', 'plans.fee.category', 'plans.fee.studentEnroll.program.degreeType',
            'plans.fee.studentEnroll.session', 'plans.fee.studentEnroll.semester',
        ])->findOrFail($id);
    }

    protected function common(PaymentPlanAgreement $agreement): array
    {
        return [
            'title' => __('Payment Plan Agreement'),
            'route' => 'admin.payment-plan',
            'agreement' => $agreement,
            'setting' => Setting::first(),
            'currency' => app(\App\Services\FeeReceipt::class)->currency(),
            'mismatches' => $agreement->isClosed() ? [] : $this->agreements->mismatches($agreement),
            'changes' => $this->agreements->changesSinceApproval($agreement),
        ];
    }

    public function show($id)
    {
        $agreement = $this->load($id);

        return view('admin.payment-plan.agreement.show', $this->common($agreement) + [
            'approvalBlockers' => $agreement->isPending() ? $this->agreements->approvalBlockers($agreement) : [],
        ]);
    }

    /** The document the bursar, the student and the Vice Chancellor sign. */
    public function print($id)
    {
        $agreement = $this->load($id);
        $code = $this->agreements->verificationCode($agreement);

        return view('admin.payment-plan.agreement.print', $this->common($agreement) + [
            'verificationCode' => $code,
            'verificationUrl' => route('verify-plan-agreement', $agreement->id) . '?code=' . $code,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $agreement = $this->load($id);

        try {
            $this->agreements->approve($agreement, Auth::id());
        } catch (\DomainException $e) {
            Flasher::addError($e->getMessage());
            return redirect()->route('admin.payment-plan.agreement.show', $agreement->id);
        }

        Flasher::addSuccess(__('Agreement :ref approved. Its instalments can now be paid.', ['ref' => $agreement->reference]));

        return redirect()->route('admin.payment-plan.agreement.show', $agreement->id);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        $agreement = $this->load($id);

        try {
            $this->agreements->reject($agreement, $request->reason, Auth::id());
        } catch (\DomainException $e) {
            Flasher::addError($e->getMessage());
            return redirect()->route('admin.payment-plan.agreement.show', $agreement->id);
        }

        Flasher::addSuccess(__('Agreement :ref rejected. Its fees can be paid in the ordinary way again.', ['ref' => $agreement->reference]));

        return redirect()->route('admin.payment-plan.agreement.show', $agreement->id);
    }

    public function cancel(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        $agreement = $this->load($id);

        try {
            $this->agreements->cancel($agreement, $request->reason, Auth::id());
        } catch (\DomainException $e) {
            Flasher::addError($e->getMessage());
            return redirect()->route('admin.payment-plan.agreement.show', $agreement->id);
        }

        Flasher::addSuccess(__('Agreement :ref cancelled. Money already paid stays on the fees.', ['ref' => $agreement->reference]));

        return redirect()->route('admin.payment-plan.agreement.show', $agreement->id);
    }

    /** Change the schedule. Instalments that have taken money stay as they are. */
    public function revise($id)
    {
        $agreement = $this->load($id);

        if ($agreement->isClosed()) {
            Flasher::addWarning(__('This agreement is :status, so it can no longer be changed.', ['status' => $agreement->status_label]));
            return redirect()->route('admin.payment-plan.agreement.show', $agreement->id);
        }

        $locked = [];
        $expected = [];
        foreach ($agreement->plans as $plan) {
            $locked[$plan->id] = $this->agreements->lockedInstallments($plan)->pluck('id')->all();
            $expected[$plan->id] = $this->agreements->expectedPlanTotal($plan);
        }

        return view('admin.payment-plan.agreement.revise', $this->common($agreement) + [
            'locked' => $locked,
            'expected' => $expected,
        ]);
    }

    public function reviseStore(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
            'plans' => 'required|array',
        ]);

        $agreement = $this->load($id);
        $spec = [];

        foreach ($agreement->plans as $plan) {
            if (!$request->has('plans.' . $plan->id)) {
                continue;
            }

            $rows = (array) $request->input('plans.' . $plan->id . '.installments', []);
            $spec[] = ['fee_id' => (int) $plan->fee_id, 'installments' => array_values($rows)];
        }

        try {
            $this->agreements->revise($agreement, $spec, $request->reason, Auth::id());
        } catch (\DomainException $e) {
            Flasher::addError($e->getMessage());
            return redirect()->back()->withInput();
        }

        Flasher::addSuccess(__('Agreement :ref revised. Print the new version for signing; it goes back to the Vice Chancellor for approval.', ['ref' => $agreement->reference]));

        return redirect()->route('admin.payment-plan.agreement.print', $agreement->id);
    }

    /**
     * Check a printed agreement, publicly, from its QR code. It shows only
     * what is printed on the agreement itself, and where it stands now.
     */
    public function publicVerify(Request $request, $id)
    {
        $agreement = PaymentPlanAgreement::with(['student', 'approver', 'plans.installments', 'plans.fee.category'])->findOrFail($id);
        $supplied = strtoupper(trim((string) $request->query('code', '')));
        $version = $supplied !== '' ? $this->agreements->versionForCode($agreement, $supplied) : null;

        return view('admin.payment-plan.agreement.verify', [
            'agreement' => $agreement,
            'setting' => Setting::first(),
            'currency' => app(\App\Services\FeeReceipt::class)->currency(),
            'codeProvided' => $supplied !== '',
            'matchedVersion' => $version,
            'isCurrent' => $version !== null && $version === (int) $agreement->version,
        ]);
    }
}
