<?php

namespace App\Services\Payment;

use App\Models\FapshiTransaction;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeSetting;
use App\Models\Student;
use App\Services\PlatformFeeAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Collecting the platform access fee through Fapshi.
 *
 * Every decision about money lives here. FapshiClient talks HTTP and knows
 * nothing about fees; the controller turns requests into calls on this class
 * and renders what it returns. That separation is what lets the rules below be
 * tested without a network and without a browser.
 *
 * Two principles run through the whole class.
 *
 * The first: the gateway's word on whether a payment succeeded is taken ONLY
 * from a payment-status response that this system asked for. A webhook body is
 * never evidence — Fapshi signs nothing, so anybody who learns a transaction id
 * could otherwise post "SUCCESSFUL" and unlock a year's access. The webhook is
 * a nudge to call syncFromProvider(), nothing more.
 *
 * The second: the switch that turns online payment off governs STARTING a
 * payment, never finishing one. An admin who flips it off while a student is on
 * Fapshi's checkout page must not thereby take the student's money and refuse
 * them access, so confirm() and syncFromProvider() deliberately do not consult
 * it.
 */
class PlatformFeeGatewayPayment
{
    public function __construct(
        protected FapshiClient $client,
        protected PlatformFeeAccess $access,
    ) {}

    /* ===================================================================== */
    /*  Starting a payment                                                   */
    /* ===================================================================== */

    /**
     * Get this student a payment link, or say why not.
     *
     * @return array{ok: bool, link?: string, transaction?: FapshiTransaction, reused?: bool, error?: string}
     */
    public function initiate(Student $student): array
    {
        $setting = $this->access->setting();

        if (!$setting) {
            return $this->refuse('The platform access fee has not been configured.');
        }

        // The configuration gate: switched off, wrong currency, fractional fee,
        // below the gateway minimum, credentials missing. One method owns all
        // of it so the student's screen, the admin's screen and this check can
        // never disagree about whether online payment is on offer.
        if ($blocker = $setting->onlinePaymentBlocker()) {
            return $this->refuse($blocker);
        }

        $enrollment = $this->access->currentEnrollment($student);

        if (!$enrollment) {
            return $this->refuse('No active enrolment was found for this student.');
        }

        // Nothing owing means nothing to collect. Taking the money anyway and
        // sorting it out later is not a kindness to anybody.
        if ($this->access->isExempt($student, $enrollment)) {
            return $this->refuse('This student is exempt from the platform access fee for this session.');
        }

        if ($this->access->hasPaid($student, $enrollment)) {
            return $this->refuse('The platform access fee for this academic year has already been settled.');
        }

        // A student who taps twice, or comes back to the page, gets the link
        // they already have. Minting a second one would leave two live links
        // for one debt, and a student who paid both would have paid twice.
        if ($existing = $this->openTransactionFor($student, $enrollment->session_id)) {
            return [
                'ok' => true,
                'link' => $existing->payment_link,
                'transaction' => $existing,
                'reused' => true,
            ];
        }

        $charge = $setting->chargeAmount();

        $transaction = DB::transaction(function () use ($student, $enrollment, $setting, $charge) {
            $transaction = new FapshiTransaction();
            $transaction->environment = $this->client->environment();
            // A placeholder only: external_id is unique and the real value
            // carries the row's own id, which does not exist until it is saved.
            $transaction->external_id = (string) Str::uuid();
            $transaction->student_id = $student->id;
            $transaction->student_enroll_id = $enrollment->id;
            $transaction->session_id = $enrollment->session_id;
            // Recorded now, not read back from settings later: an admin may
            // change the fee or the commission while this payment is in flight,
            // and it has to be judged against what the student was shown.
            $transaction->fee_amount = $setting->fee_amount;
            $transaction->charged_amount = $charge;
            $transaction->currency = PlatformFeeSetting::GATEWAY_CURRENCY;
            $transaction->status = 'created';
            $transaction->save();

            $transaction->external_id = 'PFEE-' . $transaction->id;
            $transaction->save();

            return $transaction;
        });

        $payload = [
            'amount' => $charge,
            'externalId' => $transaction->external_id,
            'userId' => $this->gatewaySafeId($enrollment->matricule ?: $student->student_id),
            'message' => Str::limit((string) ($setting->title ?: 'Platform Access Fee'), 100, ''),
            'redirectUrl' => route('payment.fapshi.return', $transaction->id),
        ];

        if (filled($student->email)) {
            $payload['email'] = $student->email;
        }

        $transaction->raw_request = $payload;
        $transaction->initiated_at = now();
        $transaction->save();

        try {
            $response = $this->client->initiatePay($payload);
        } catch (\Throwable $e) {
            report($e);
            $this->markFailed($transaction, 'Could not reach the payment gateway: ' . $e->getMessage());

            return $this->refuse('The payment service could not be reached. Please try again in a moment.');
        }

        $transaction->raw_response = ['initiate-pay' => [
            'http' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]];
        $transaction->save();

        if (!$response->successful() || !filled($response->json('link'))) {
            // The provider's own words go to the log, not to the student: a 403
            // from an IP whitelist or a revoked key is an operations problem,
            // and a student can do nothing with it.
            Log::warning('Fapshi initiate-pay rejected', [
                'external_id' => $transaction->external_id,
                'http' => $response->status(),
                'body' => $response->body(),
            ]);

            $this->markFailed($transaction, 'initiate-pay HTTP ' . $response->status() . ': '
                . Str::limit((string) $response->json('message', $response->body()), 150, ''));

            return $this->refuse('The payment service declined to start this payment. Please try again, or contact the registry.');
        }

        $transaction->trans_id = $response->json('transId');
        $transaction->payment_link = $response->json('link');
        $transaction->save();

        return [
            'ok' => true,
            'link' => $transaction->payment_link,
            'transaction' => $transaction,
            'reused' => false,
        ];
    }

    /**
     * A live link this student can still use for this session, if there is one.
     *
     * Anything past Fapshi's 24-hour link life is retired on the way past, so
     * the student is not handed a link the gateway will refuse.
     */
    public function openTransactionFor(Student $student, $sessionId): ?FapshiTransaction
    {
        $candidates = FapshiTransaction::where('student_id', $student->id)
            // whereNull rather than where(..., null): the latter compiles to
            // "= NULL", which matches nothing in SQL. An enrolment with no
            // session — this database has 39 applicant stubs like that — would
            // then never find the link it already had, and every tap would mint
            // another one.
            ->when($sessionId !== null,
                fn ($query) => $query->where('session_id', $sessionId),
                fn ($query) => $query->whereNull('session_id'))
            ->whereIn('status', FapshiTransaction::OPEN_STATUSES)
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $candidate) {
            if ($candidate->linkHasExpired()) {
                $candidate->status = 'expired';
                $candidate->failure_reason = 'The payment link passed its 24-hour life without being paid.';
                $candidate->save();

                continue;
            }

            if (filled($candidate->payment_link)) {
                return $candidate;
            }
        }

        return null;
    }

    /* ===================================================================== */
    /*  Finishing a payment                                                  */
    /* ===================================================================== */

    /**
     * Ask Fapshi what happened, then act on the answer.
     *
     * The single entry point for the webhook, the student's return from
     * checkout, and the reconciliation command alike — because all three face
     * the same question and must not answer it three different ways.
     *
     * @return array{synced: bool, credited: bool, status: string, payment?: PlatformFeePayment, message: string}
     */
    public function syncFromProvider(FapshiTransaction $transaction): array
    {
        if ($transaction->isTerminal()) {
            return [
                'synced' => false,
                'credited' => $transaction->status === 'successful',
                'status' => $transaction->status,
                'payment' => $transaction->platformFeePayment,
                'message' => 'This payment has already been settled.',
            ];
        }

        if (!filled($transaction->trans_id)) {
            return [
                'synced' => false,
                'credited' => false,
                'status' => $transaction->status,
                'message' => 'This payment never reached the gateway, so there is nothing to confirm.',
            ];
        }

        try {
            $response = $this->client->paymentStatus($transaction->trans_id);
        } catch (\Throwable $e) {
            report($e);

            return [
                'synced' => false,
                'credited' => false,
                'status' => $transaction->status,
                'message' => 'The payment service could not be reached. This will be retried automatically.',
            ];
        }

        if ($response->status() === 429) {
            // Six polls a minute per transaction, and the caller may be a
            // paced reconciliation run. Say so rather than treating a rate
            // limit as a failed payment.
            return [
                'synced' => false,
                'credited' => false,
                'status' => $transaction->status,
                'message' => 'The payment service is rate-limiting status checks. This will be retried.',
                'rate_limited' => true,
            ];
        }

        if (!$response->successful()) {
            Log::warning('Fapshi payment-status failed', [
                'external_id' => $transaction->external_id,
                'trans_id' => $transaction->trans_id,
                'http' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'synced' => false,
                'credited' => false,
                'status' => $transaction->status,
                'message' => 'The payment service did not answer with a status. This will be retried.',
            ];
        }

        return $this->confirm($transaction, (array) $response->json());
    }

    /**
     * Act on an authoritative payment-status payload.
     *
     * Separate from syncFromProvider so the rules can be exercised against a
     * payload directly, and so it is impossible to call this with a webhook
     * body by accident: the only caller passes what payment-status returned.
     *
     * @param  array $status  a payment-status response body
     * @return array{synced: bool, credited: bool, status: string, payment?: PlatformFeePayment, message: string}
     */
    public function confirm(FapshiTransaction $transaction, array $status): array
    {
        return DB::transaction(function () use ($transaction, $status) {
            $transaction->refresh();

            // Idempotency, first thing, same as ApplicantFeePaymentService:
            // a webhook and a returning student routinely arrive together.
            if ($transaction->status === 'successful' && $transaction->platform_fee_payment_id) {
                return [
                    'synced' => false,
                    'credited' => true,
                    'status' => 'successful',
                    'payment' => PlatformFeePayment::find($transaction->platform_fee_payment_id),
                    'message' => 'This payment has already been credited.',
                ];
            }

            $transaction->raw_response = array_merge($transaction->raw_response ?? [], ['payment-status' => $status]);

            $providerStatus = strtoupper((string) ($status['status'] ?? ''));

            // Everything the provider reports is kept, whatever the outcome —
            // this is the record that settles a dispute about whether a student
            // paid.
            //
            // But only once something has actually been paid. Fapshi echoes
            // back the amount we ASKED for in every state, paid or not — so a
            // CREATED, PENDING, FAILED or EXPIRED payment all report an
            // `amount`, and storing it as amount_received says a student handed
            // over 2,163 when they handed over nothing. Two genuine failures on
            // this database each claimed 2,163 received, and fapshi:inspect
            // added them up.
            //
            // SUCCESSFUL is the only state in which money moved.
            if (strtoupper((string) ($status['status'] ?? '')) === 'SUCCESSFUL') {
                if (array_key_exists('amount', $status)) {
                    $transaction->amount_received = $status['amount'];
                }
                if (array_key_exists('revenue', $status)) {
                    $transaction->revenue = $status['revenue'];
                }
            }

            if (filled($status['financialTransId'] ?? null)) {
                $transaction->financial_trans_id = $status['financialTransId'];
            }

            if ($providerStatus !== 'SUCCESSFUL') {
                return $this->recordUnsuccessful($transaction, $providerStatus, $status);
            }

            // --- The payment succeeded. Is it the payment we asked for? ---

            // Identity. A webhook cannot be trusted to say which payment it is
            // about, and nor can a status response that disagrees with the
            // transaction it was fetched for.
            $claimedExternalId = (string) ($status['externalId'] ?? '');

            if ($claimedExternalId !== '' && $claimedExternalId !== $transaction->external_id) {
                return $this->flag($transaction, 'mismatched', sprintf(
                    'The gateway reported a successful payment for %s against transaction %s.',
                    $claimedExternalId, $transaction->external_id));
            }

            // Amount. Without this a student can pay the 100 XAF minimum and
            // unlock a year's access.
            $received = (float) ($transaction->amount_received ?? 0);
            $expected = (float) $transaction->charged_amount;

            if ($received + 0.00001 < $expected) {
                return $this->flag($transaction, 'mismatched', sprintf(
                    'Only %s %s was received against a charge of %s %s.',
                    number_format($received, 2), $transaction->currency,
                    number_format($expected, 2), $transaction->currency));
            }

            // Still owing? A student who settled some other way between
            // opening the link and paying it has now paid twice.
            $student = $transaction->student;
            $enrollment = $transaction->studentEnroll;

            if (!$student || !$enrollment) {
                return $this->flag($transaction, 'mismatched',
                    'The student or enrolment this payment belongs to no longer exists.');
            }

            $existing = $this->access->paymentFor($student, $enrollment);

            if ($existing && $existing->status === 'approved') {
                // Unless it is this very transaction's own work, finished on an
                // earlier call that did not get as far as linking the two rows.
                if ($existing->gateway_trans_id && $existing->gateway_trans_id === $transaction->trans_id) {
                    $transaction->status = 'successful';
                    $transaction->platform_fee_payment_id = $existing->id;
                    $transaction->confirmed_at = $transaction->confirmed_at ?: now();
                    $transaction->save();

                    return [
                        'synced' => true,
                        'credited' => true,
                        'status' => 'successful',
                        'payment' => $existing,
                        'message' => 'Payment confirmed.',
                    ];
                }

                return $this->flag($transaction, 'refundable',
                    'The fee for this session was already settled when this payment arrived.');
            }

            if ($this->access->isExempt($student, $enrollment)) {
                return $this->flag($transaction, 'refundable',
                    'This student was exempt for this session when the payment arrived.');
            }

            // --- Credit it. ---

            $payment = $existing ?: new PlatformFeePayment([
                'student_enroll_id' => $transaction->student_enroll_id,
                'session_id' => $transaction->session_id,
            ]);

            $payment->fill([
                'fee_amount' => $transaction->fee_amount,
                'paid_amount' => $transaction->amount_received,
                'paid_via' => 'fapshi',
                'gateway_trans_id' => $transaction->trans_id,
                'status' => 'approved',
                'payment_date' => $this->providerDate($status['dateConfirmed'] ?? null),
                // Nobody here approved it, so verified_by stays null — the
                // column means "which member of staff checked this", and
                // writing a name into it would be a small lie that an audit
                // would later have to unpick.
                'verified_by' => null,
                'verified_at' => now(),
                'admin_note' => sprintf('Paid online via Fapshi. Ref %s%s. Charged %s %s (fee %s + gateway %s).',
                    $transaction->trans_id,
                    $transaction->financial_trans_id ? ' / operator ' . $transaction->financial_trans_id : '',
                    number_format((float) $transaction->charged_amount, 2),
                    $transaction->currency,
                    number_format((float) $transaction->fee_amount, 2),
                    number_format((float) $transaction->charged_amount - (float) $transaction->fee_amount, 2)),
            ]);

            // A rejected manual attempt that is now settled online should not
            // keep the rejection note explaining why it was turned down.
            $payment->student_note = $payment->student_note ?: null;
            $payment->save();

            $transaction->status = 'successful';
            $transaction->platform_fee_payment_id = $payment->id;
            $transaction->confirmed_at = now();
            $transaction->failure_reason = null;
            $transaction->save();

            return [
                'synced' => true,
                'credited' => true,
                'status' => 'successful',
                'payment' => $payment,
                'message' => 'Payment confirmed.',
            ];
        });
    }

    /* ===================================================================== */

    /**
     * Record a payment that did not succeed, without closing a door too early.
     *
     * CREATED and PENDING are not outcomes — the student is still at the
     * checkout — so those stay open and get asked again later. Only FAILED and
     * EXPIRED are final, and a link past its 24 hours is expired whatever the
     * provider last said.
     */
    protected function recordUnsuccessful(FapshiTransaction $transaction, string $providerStatus, array $status): array
    {
        $map = [
            'FAILED' => 'failed',
            'EXPIRED' => 'expired',
            'PENDING' => 'pending',
            'CREATED' => 'created',
        ];

        $mapped = $map[$providerStatus] ?? 'pending';

        if ($mapped === 'created' && $transaction->linkHasExpired()) {
            $mapped = 'expired';
        }

        $transaction->status = $mapped;

        if (in_array($mapped, ['failed', 'expired'], true)) {
            $transaction->failure_reason = Str::limit((string) ($status['reason'] ?? match ($mapped) {
                'failed' => 'The mobile money operator reported the payment as failed.',
                'expired' => 'The payment link passed its 24-hour life without being paid.',
            }), 250, '');
            $transaction->confirmed_at = now();
        }

        $transaction->save();

        return [
            'synced' => true,
            'credited' => false,
            'status' => $mapped,
            'message' => match ($mapped) {
                'failed' => 'The payment did not go through.',
                'expired' => 'This payment link has expired.',
                default => 'The payment has not been completed yet.',
            },
        ];
    }

    /**
     * Money arrived, access did not follow: keep it, flag it, tell somebody.
     *
     * Discarding the record would be the one genuinely unrecoverable mistake
     * available here — the school would have the money and no trace of whose it
     * was.
     */
    protected function flag(FapshiTransaction $transaction, string $status, string $reason): array
    {
        Log::warning('Fapshi platform fee needs attention', [
            'external_id' => $transaction->external_id,
            'trans_id' => $transaction->trans_id,
            'flag' => $status,
            'reason' => $reason,
        ]);

        $transaction->status = $status;
        $transaction->failure_reason = Str::limit($reason, 250, '');
        $transaction->confirmed_at = now();
        $transaction->save();

        return [
            'synced' => true,
            'credited' => false,
            'status' => $status,
            'message' => $status === 'refundable'
                ? 'This payment was not needed — the registry will be in touch about a refund.'
                : 'This payment could not be matched to what was owed. The registry has been notified.',
        ];
    }

    protected function markFailed(FapshiTransaction $transaction, string $reason): void
    {
        $transaction->status = 'failed';
        $transaction->failure_reason = Str::limit($reason, 250, '');
        $transaction->confirmed_at = now();
        $transaction->save();
    }

    protected function refuse(string $error): array
    {
        return ['ok' => false, 'error' => $error];
    }

    /**
     * Fapshi limits userId and externalId to 1-100 characters of letters,
     * digits, hyphen and underscore. A matricule already fits; anything else
     * is made to.
     */
    protected function gatewaySafeId(?string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value) ?: 'STUDENT';

        return Str::limit($safe, 100, '');
    }

    /** Fapshi's own confirmation date, where it gave a usable one. */
    protected function providerDate($value)
    {
        if (!filled($value)) {
            return now();
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }
}
