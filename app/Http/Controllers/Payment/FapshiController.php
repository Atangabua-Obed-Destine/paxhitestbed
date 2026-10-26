<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\FapshiTransaction;
use App\Services\Payment\FapshiClient;
use App\Services\Payment\PlatformFeeGatewayPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Fapshi — the platform access fee only.
 *
 *   payment.fapshi.initiate   POST  /payment/fapshi/initiate
 *   payment.fapshi.return     GET   /payment/fapshi/return/{transaction}
 *   payment.fapshi.webhook    POST  /payment/fapshi/webhook
 *
 * All three go through PlatformFeeGatewayPayment, which owns every decision
 * about money. Nothing here reads or writes a payment row directly.
 */
class FapshiController extends Controller
{
    public function __construct(
        protected PlatformFeeGatewayPayment $gateway,
        protected FapshiClient $client,
    ) {}

    /**
     * Start a payment and send the student to Fapshi's checkout.
     *
     * A redirect rather than JSON: the student's screen is a plain form post,
     * and a browser that cannot run the script still works.
     */
    public function initiate(Request $request)
    {
        $student = Auth::guard('student')->user();

        if (!$student) {
            abort(403);
        }

        $result = $this->gateway->initiate($student);

        if (!$result['ok']) {
            return redirect()->route('student.platform-fee.payment')
                ->with('warning', $result['error']);
        }

        return redirect()->away($result['link']);
    }

    /**
     * Where Fapshi sends the student back to.
     *
     * This asks the gateway what happened rather than believing the redirect,
     * so a student who abandons the checkout and edits the URL cannot land on a
     * success page. It also means the whole flow is exercisable on localhost,
     * which no webhook can reach.
     */
    public function return(Request $request, FapshiTransaction $transaction)
    {
        $student = Auth::guard('student')->user();

        if (!$student) {
            abort(403);
        }

        // A transaction belongs to the student who started it. Without this,
        // the id in the URL would let anyone read anyone's payment.
        if ((int) $transaction->student_id !== (int) $student->id) {
            abort(404);
        }

        $result = $this->gateway->syncFromProvider($transaction);

        return view('student.platform-fee.return', [
            'transaction' => $transaction->refresh(),
            'result' => $result,
            'platformSetting' => app(\App\Services\PlatformFeeAccess::class)->setting(),
        ]);
    }

    /**
     * Fapshi's notification that a payment changed state.
     *
     * Deliberately minimal, and deliberately distrustful.
     *
     * Fapshi signs nothing. The only thing separating a real notification from
     * anybody's POST is a shared secret echoed in x-wh-secret, and that secret
     * is configured in a dashboard that will not show whether one is already
     * set. So the body is never read for anything but the transaction it points
     * at: the state is fetched from payment-status, over an authenticated
     * connection this system opened itself.
     *
     * That one rule disposes of a forged success, a replayed notification and a
     * tampered amount together — and it is exactly what MtnMomoController does
     * NOT do, which is recorded as a finding rather than copied.
     *
     * Fapshi sends each notification once and never retries, so a failure here
     * must not be the end of the story: fapshi:reconcile sweeps up anything
     * that never arrived.
     */
    public function webhook(Request $request): JsonResponse
    {
        if (!$this->client->webhookSecretMatches($request->header('x-wh-secret'))) {
            Log::warning('Fapshi webhook rejected: wrong or missing x-wh-secret', [
                'ip' => $request->ip(),
                'trans_id' => $request->input('transId'),
            ]);

            // 403 and nothing else. An unauthenticated caller learns neither
            // whether the transaction exists nor what state it is in.
            return response()->json(['ok' => false], 403);
        }

        $transId = (string) $request->input('transId');
        $externalId = (string) $request->input('externalId');

        $transaction = FapshiTransaction::query()
            ->when($transId !== '', fn ($query) => $query->where('trans_id', $transId))
            ->when($transId === '' && $externalId !== '', fn ($query) => $query->where('external_id', $externalId))
            ->first();

        if (!$transaction) {
            Log::warning('Fapshi webhook for an unknown transaction', [
                'trans_id' => $transId,
                'external_id' => $externalId,
            ]);

            // 200 regardless: Fapshi asks for a quick answer and does not
            // retry, and there is nothing it could usefully do with an error.
            return response()->json(['ok' => true]);
        }

        Log::info('Fapshi webhook received', [
            'trans_id' => $transaction->trans_id,
            'external_id' => $transaction->external_id,
            // What the body claimed, recorded so a discrepancy with the status
            // call is visible afterwards. Not acted upon.
            'claimed_status' => $request->input('status'),
        ]);

        $result = $this->gateway->syncFromProvider($transaction);

        return response()->json(['ok' => true, 'status' => $result['status']]);
    }
}
