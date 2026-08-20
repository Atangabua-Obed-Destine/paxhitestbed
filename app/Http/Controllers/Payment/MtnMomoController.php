<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Fee;
use App\Models\MomoTransaction;
use App\Services\Payment\ApplicantFeePaymentService;
use App\Services\Payment\MtnMomoClient;
use App\Traits\FeesStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MTN Mobile Money (Collections) — payment endpoints.
 *
 * Route names live under the `payment.` group:
 *   payment.momo.mtn.initiate     POST  /payment/momo/mtn/initiate
 *   payment.momo.mtn.status       GET   /payment/momo/mtn/status/{reference}
 *   payment.momo.mtn.webhook      POST  /payment/momo/mtn/webhook
 *
 * The client (dashboard modal / student fee page) POSTs to `initiate` with the
 * fee_id and MSISDN, then polls `status` until it returns terminal (successful|failed).
 */
class MtnMomoController extends Controller
{
    use FeesStudent;

    public function __construct(
        protected MtnMomoClient $client,
        protected ApplicantFeePaymentService $feePayer,
    ) {}

    /**
     * Kick off a Request-to-Pay. Returns JSON with the reference id so the
     * front-end can poll status.
     */
    public function initiate(Request $request): JsonResponse
    {
        if (!$this->client->isEnabled()) {
            return response()->json(['ok' => false, 'error' => 'MTN Mobile Money is not enabled on this site.'], 400);
        }

        $data = $request->validate([
            'fee_id' => ['required', 'integer', 'exists:fees,id'],
            'msisdn' => ['required', 'string', 'max:20'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
        ]);

        $msisdn = $this->normalizeMsisdn($data['msisdn']);
        if (!$msisdn) {
            return response()->json(['ok' => false, 'error' => 'Invalid phone number.'], 422);
        }

        $fee = Fee::findOrFail($data['fee_id']);
        $amount = $this->netAmount($fee->id);
        if ($amount <= 0) {
            return response()->json(['ok' => false, 'error' => 'This fee is already fully paid.'], 422);
        }

        // Authorization: if an application is scoped, the authenticated applicant
        // must own it. If no application is scoped, an authenticated student must
        // own the enrollment. Otherwise reject.
        $applicationId = $data['application_id'] ?? null;
        if ($applicationId) {
            $application = Application::findOrFail($applicationId);
            $applicantId = Auth::guard('applicant')->id();
            if (!$applicantId || (int) $application->applicant_id !== (int) $applicantId) {
                abort(403);
            }

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
            if (!$studentId || !$ownerId || (int) $studentId !== (int) $ownerId) {
                abort(403);
            }
        }

        $referenceId = (string) Str::uuid();
        $externalId  = 'FEE-' . $fee->id . '-' . now()->format('YmdHis');
        $currency    = config('momo.currency', 'XAF');

        $tx = new MomoTransaction();
        $tx->provider           = 'mtn';
        $tx->environment        = $this->client->environment();
        $tx->fee_id             = $fee->id;
        $tx->application_id     = $applicationId;
        $tx->initiated_by_type  = $applicationId ? 'applicant' : (Auth::guard('student')->check() ? 'student' : 'web');
        $tx->initiated_by_id    = Auth::guard('applicant')->id() ?? Auth::guard('student')->id() ?? Auth::guard('web')->id();
        $tx->reference_id       = $referenceId;
        $tx->external_id        = $externalId;
        $tx->msisdn             = $msisdn;
        $tx->amount             = $amount;
        $tx->currency           = $currency;
        $tx->status             = 'pending';
        $tx->requested_at       = now();
        $tx->raw_request        = [
            'msisdn' => $msisdn,
            'amount' => $amount,
            'currency' => $currency,
            'externalId' => $externalId,
        ];
        $tx->save();

        try {
            $response = $this->client->requestToPay(
                referenceId: $referenceId,
                externalId: $externalId,
                msisdn: $msisdn,
                amount: $amount,
                currency: $currency,
                payerMessage: 'Admission fee payment',
                payeeNote: 'Ref ' . $externalId
            );

            $tx->raw_response = ['requestToPay' => ['status' => $response->status(), 'body' => $response->body()]];
            $tx->save();

            if ($response->status() !== 202) {
                $this->feePayer->markMomoFailed($tx, 'requestToPay HTTP ' . $response->status(), ['body' => $response->body()]);
                return response()->json(['ok' => false, 'error' => 'Payment provider rejected the request.', 'reference' => $referenceId], 502);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->feePayer->markMomoFailed($tx, $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'Could not reach MTN MoMo. Please try again.', 'reference' => $referenceId], 502);
        }

        return response()->json([
            'ok'         => true,
            'reference'  => $referenceId,
            'amount'     => $amount,
            'currency'   => $currency,
            'poll_interval' => (int) config('momo.poll_interval_seconds', 3),
            'poll_timeout'  => (int) config('momo.poll_timeout_seconds', 90),
        ]);
    }

    /**
     * Front-end polls this until it returns `status: successful | failed`.
     * We ourselves poll MTN for the terminal state and, on success, credit the fee.
     */
    public function status(Request $request, string $reference): JsonResponse
    {
        $tx = MomoTransaction::where('provider', 'mtn')->where('reference_id', $reference)->firstOrFail();

        // If already resolved, return the cached terminal state.
        if (in_array($tx->status, ['successful', 'failed', 'timeout'], true)) {
            return $this->txResponse($tx);
        }

        try {
            $payload = $this->client->status($reference);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => true,
                'status' => 'pending',
                'reference' => $reference,
                'note' => 'Provider temporarily unreachable, will retry.',
            ]);
        }

        $tx->raw_response = array_merge($tx->raw_response ?? [], ['status' => $payload]);
        $tx->save();

        $providerStatus = strtoupper($payload['status'] ?? 'PENDING');

        if ($providerStatus === 'SUCCESSFUL') {
            $this->feePayer->markMomoPaid($tx, $payload);
        } elseif ($providerStatus === 'FAILED') {
            $reason = (string) ($payload['reason']['code'] ?? $payload['reason'] ?? 'Payment failed');
            Log::warning('MTN MoMo payment FAILED', ['ref' => $reference, 'reason' => $reason, 'payload' => $payload]);
            $this->feePayer->markMomoFailed($tx, $reason, $payload);
        }

        return $this->txResponse($tx->refresh());
    }

    /**
     * Optional callback endpoint (registered with MTN as X-Callback-Url).
     * MTN posts the same shape as `status()` returns. We just replay through
     * the same crediting path.
     */
    public function webhook(Request $request): JsonResponse
    {
        $reference = $request->input('externalId') ?: $request->header('X-Reference-Id');
        Log::info('MTN MoMo webhook received', ['ref' => $reference, 'payload' => $request->all()]);

        // Prefer looking up by X-Reference-Id (our UUID) first, fall back to externalId.
        $tx = MomoTransaction::where('provider', 'mtn')
            ->where(function ($q) use ($request, $reference) {
                $q->where('reference_id', $request->header('X-Reference-Id'))
                  ->orWhere('external_id', $reference);
            })
            ->first();

        if (!$tx) {
            return response()->json(['ok' => false, 'error' => 'unknown reference'], 404);
        }

        $providerStatus = strtoupper($request->input('status', 'PENDING'));
        if ($providerStatus === 'SUCCESSFUL') {
            $this->feePayer->markMomoPaid($tx, $request->all());
        } elseif ($providerStatus === 'FAILED') {
            $this->feePayer->markMomoFailed($tx, (string) $request->input('reason', 'Payment failed'), $request->all());
        }

        return response()->json(['ok' => true]);
    }

    /* --------------------------------------------------------------------- */

    protected function txResponse(MomoTransaction $tx): JsonResponse
    {
        return response()->json([
            'ok'         => true,
            'reference'  => $tx->reference_id,
            'status'     => $tx->status,
            'amount'     => (float) $tx->amount,
            'currency'   => $tx->currency,
            'reason'     => $tx->failure_reason,
            'financial_transaction_id' => $tx->financial_transaction_id,
        ]);
    }

    /**
     * Turn a user-entered phone number into a MoMo-compatible MSISDN.
     * Accepts "670000000", "+237670000000", "237 670 000 000". Assumes Cameroon (+237) if no country code.
     */
    protected function normalizeMsisdn(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') return null;
        // Strip leading 00
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        // Assume Cameroon if only 9 digits (local format).
        if (strlen($digits) === 9) {
            $digits = '237' . $digits;
        }
        // Basic sanity: MSISDN 10–15 digits.
        if (strlen($digits) < 10 || strlen($digits) > 15) return null;
        return $digits;
    }

    /**
     * DEV-ONLY: force a MoMo payment to succeed without hitting MTN.
     * Enabled only when APP_ENV=local AND environment=sandbox. Used so operators
     * can validate the receipt/enrollment flow when MTN's sandbox is unreliable.
     */
    public function sandboxMarkPaid(Request $request): JsonResponse
    {
        if (!app()->environment('local') || $this->client->environment() !== 'sandbox') {
            abort(404);
        }

        $data = $request->validate([
            'fee_id' => ['required', 'integer', 'exists:fees,id'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
        ]);

        $fee = Fee::findOrFail($data['fee_id']);
        $amount = $this->netAmount($fee->id);
        if ($amount <= 0) {
            return response()->json(['ok' => false, 'error' => 'Fee is already fully paid.'], 422);
        }

        // Authorization mirrors initiate().
        $applicationId = $data['application_id'] ?? null;
        if ($applicationId) {
            $application = Application::findOrFail($applicationId);
            $applicantId = Auth::guard('applicant')->id();
            if (!$applicantId || (int) $application->applicant_id !== (int) $applicantId) {
                abort(403);
            }
        }

        $referenceId = (string) Str::uuid();
        $tx = new MomoTransaction();
        $tx->provider           = 'mtn';
        $tx->environment        = 'sandbox';
        $tx->fee_id             = $fee->id;
        $tx->application_id     = $applicationId;
        $tx->initiated_by_type  = $applicationId ? 'applicant' : (Auth::guard('student')->check() ? 'student' : 'web');
        $tx->initiated_by_id    = Auth::guard('applicant')->id() ?? Auth::guard('student')->id() ?? Auth::guard('web')->id();
        $tx->reference_id       = $referenceId;
        $tx->external_id        = 'DEVTEST-' . $fee->id . '-' . now()->format('YmdHis');
        $tx->msisdn             = 'DEV-TEST';
        $tx->amount             = $amount;
        $tx->currency           = config('momo.currency', 'XAF');
        $tx->status             = 'pending';
        $tx->requested_at       = now();
        $tx->raw_request        = ['note' => 'Dev sandbox mark-as-paid'];
        $tx->save();

        $receipt = $this->feePayer->markMomoPaid($tx, [
            'status' => 'SUCCESSFUL',
            'financialTransactionId' => 'DEVTEST-' . time(),
            'reason' => 'Marked paid via sandbox dev helper.',
        ]);

        return response()->json([
            'ok' => true,
            'status' => 'successful',
            'reference' => $referenceId,
            'receipt_id' => $receipt->id,
        ]);
    }
}
