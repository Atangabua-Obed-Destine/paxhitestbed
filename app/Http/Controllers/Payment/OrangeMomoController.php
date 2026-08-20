<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Fee;
use App\Models\MomoTransaction;
use App\Services\Payment\ApplicantFeePaymentService;
use App\Services\Payment\OrangeMomoClient;
use App\Traits\FeesStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orange Money (Cameroon) — Web Payment endpoints.
 *
 * Flow: initiate → redirect user to Orange payment_url → user pays → Orange calls
 * notif_url (webhook) and redirects user to return_url. We also poll status for
 * safety on the return URL.
 */
class OrangeMomoController extends Controller
{
    use FeesStudent;

    public function __construct(
        protected OrangeMomoClient $client,
        protected ApplicantFeePaymentService $feePayer,
    ) {}

    /** POST /payment/momo/orange/initiate */
    public function initiate(Request $request): JsonResponse
    {
        if (!$this->client->isEnabled()) {
            return response()->json(['ok' => false, 'error' => 'Orange Money is not enabled on this site.'], 400);
        }

        $data = $request->validate([
            'fee_id' => ['required', 'integer', 'exists:fees,id'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
        ]);

        $fee = Fee::findOrFail($data['fee_id']);
        $amount = $this->netAmount($fee->id);
        if ($amount <= 0) {
            return response()->json(['ok' => false, 'error' => 'This fee is already fully paid.'], 422);
        }

        $applicationId = $data['application_id'] ?? null;
        if ($applicationId) {
            $application = Application::findOrFail($applicationId);
            $applicantId = Auth::guard('applicant')->id();
            if (!$applicantId || (int) $application->applicant_id !== (int) $applicantId) abort(403);

            // The applicant may not pay until the application is complete:
            // approving this fee submits it, with no further chance to review.
            $outstanding = \App\Services\ApplicationCompleteness::missingLabels($application);
            if ($outstanding) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Please complete your application before paying. Still outstanding: ' . implode(', ', $outstanding),
                    'missing' => $outstanding,
                ], 422);
            }
        } else {
            $studentId = Auth::guard('student')->id();
            $ownerId = $fee->studentEnroll->student_id ?? null;
            if (!$studentId || !$ownerId || (int) $studentId !== (int) $ownerId) abort(403);
        }

        $referenceId = (string) Str::uuid();
        $orderId     = 'OM-' . $fee->id . '-' . now()->format('YmdHis');
        $currency    = config('momo.currency', 'XAF');

        $tx = new MomoTransaction();
        $tx->provider          = 'orange';
        $tx->environment       = $this->client->environment();
        $tx->fee_id            = $fee->id;
        $tx->application_id    = $applicationId;
        $tx->initiated_by_type = $applicationId ? 'applicant' : (Auth::guard('student')->check() ? 'student' : 'web');
        $tx->initiated_by_id   = Auth::guard('applicant')->id() ?? Auth::guard('student')->id() ?? Auth::guard('web')->id();
        $tx->reference_id      = $referenceId;
        $tx->external_id       = $orderId;
        $tx->msisdn            = '';
        $tx->amount            = $amount;
        $tx->currency          = $currency;
        $tx->status            = 'pending';
        $tx->requested_at      = now();
        $tx->raw_request       = ['order_id' => $orderId, 'amount' => $amount, 'currency' => $currency];
        $tx->save();

        try {
            $response = $this->client->initWebPayment(
                orderId: $orderId,
                amount: $amount,
                currency: $currency,
                returnUrl: route('payment.momo.orange.return', ['reference' => $referenceId]),
                cancelUrl: route('payment.momo.orange.return', ['reference' => $referenceId, 'cancelled' => 1]),
                notifUrl: url('/payment/momo/orange/webhook'),
                reference: $orderId,
            );
            $tx->raw_response = ['initWebPayment' => ['status' => $response->status(), 'body' => $response->json()]];
            $tx->save();

            $body = $response->json() ?? [];
            $payUrl = $body['payment_url'] ?? null;
            if (!$response->successful() || !$payUrl) {
                $this->feePayer->markMomoFailed($tx, 'initWebPayment HTTP ' . $response->status(), $body);
                return response()->json(['ok' => false, 'error' => 'Payment provider rejected the request.'], 502);
            }
            // Persist tokens for status polling.
            $tx->raw_request = array_merge($tx->raw_request ?? [], [
                'pay_token' => $body['pay_token'] ?? null,
                'notif_token' => $body['notif_token'] ?? null,
            ]);
            $tx->save();

            return response()->json([
                'ok'          => true,
                'reference'   => $referenceId,
                'payment_url' => $payUrl,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->feePayer->markMomoFailed($tx, $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'Could not reach Orange Money. Please try again.'], 502);
        }
    }

    /** GET /payment/momo/orange/return/{reference} */
    public function return(Request $request, string $reference): RedirectResponse
    {
        $tx = MomoTransaction::where('provider', 'orange')->where('reference_id', $reference)->firstOrFail();

        if ($request->boolean('cancelled')) {
            $this->feePayer->markMomoFailed($tx, 'Cancelled by user');
        } else {
            $this->refreshStatus($tx);
        }

        // Applicant flow → back to dashboard; student flow → back to fees list.
        if ($tx->application_id && Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard')
                ->with('momo_status', $tx->status);
        }
        return redirect()->to(url()->previous())->with('momo_status', $tx->status);
    }

    /** POST /payment/momo/orange/webhook */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('Orange Money webhook received', $request->all());
        $orderId = $request->input('order_id') ?: $request->input('reference');
        $tx = MomoTransaction::where('provider', 'orange')
            ->where(function ($q) use ($orderId) {
                $q->where('external_id', $orderId)->orWhere('reference_id', $orderId);
            })
            ->first();
        if (!$tx) {
            return response()->json(['ok' => false], 404);
        }
        $status = strtoupper($request->input('status', 'PENDING'));
        if ($status === 'SUCCESS' || $status === 'SUCCESSFUL') {
            $this->feePayer->markMomoPaid($tx, $request->all());
        } elseif (in_array($status, ['FAILED', 'EXPIRED', 'CANCELLED'], true)) {
            $this->feePayer->markMomoFailed($tx, $status, $request->all());
        }
        return response()->json(['ok' => true]);
    }

    /** GET /payment/momo/orange/status/{reference} */
    public function status(Request $request, string $reference): JsonResponse
    {
        $tx = MomoTransaction::where('provider', 'orange')->where('reference_id', $reference)->firstOrFail();
        if (in_array($tx->status, ['successful', 'failed', 'timeout'], true)) {
            return response()->json(['ok' => true, 'reference' => $reference, 'status' => $tx->status]);
        }
        $this->refreshStatus($tx);
        return response()->json(['ok' => true, 'reference' => $reference, 'status' => $tx->refresh()->status]);
    }

    protected function refreshStatus(MomoTransaction $tx): void
    {
        $payToken = $tx->raw_request['pay_token'] ?? null;
        if (!$payToken) return;

        try {
            $payload = $this->client->transactionStatus($tx->external_id, $tx->amount, $payToken);
        } catch (\Throwable $e) {
            report($e);
            return;
        }
        $tx->raw_response = array_merge($tx->raw_response ?? [], ['status' => $payload]);
        $tx->save();
        $s = strtoupper($payload['status'] ?? 'PENDING');
        if ($s === 'SUCCESS' || $s === 'SUCCESSFUL') {
            $this->feePayer->markMomoPaid($tx, $payload);
        } elseif (in_array($s, ['FAILED', 'EXPIRED', 'CANCELLED'], true)) {
            $this->feePayer->markMomoFailed($tx, $s, $payload);
        }
    }
}
