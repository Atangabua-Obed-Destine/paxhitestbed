<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffBankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Paying a payroll, one at a time from its row or many at once from the list.
 *
 * Refusals are RuntimeExceptions whose message is meant for the screen.
 */
class PayrollPayer
{
    public function __construct(private PayrollAccountingService $accounting)
    {
    }

    /**
     * @param  array{pay_date:string, payment_method:mixed, bank_account_id:mixed, note?:?string}  $data
     * @return array{journal: bool, emailed: ?bool, email_error: ?string}
     */
    public function pay(Payroll $payroll, array $data, int $byUserId): array
    {
        $settings = PayrollSetting::current();

        $result = DB::transaction(function () use ($payroll, $data, $byUserId, $settings) {
            $payroll = Payroll::whereKey($payroll->id)->lockForUpdate()->firstOrFail();

            // Paying twice would post the salary to the ledger twice.
            if ((int) $payroll->status === 1) {
                throw new \RuntimeException(__('This payroll has already been paid.'));
            }

            if ($settings->require_approval && !$payroll->isApproved()) {
                throw new \RuntimeException(__('This payroll has not been approved yet. It must be approved before it can be paid.'));
            }

            // The money goes to the staff member's own account, not anybody's.
            $account = StaffBankAccount::whereKey($data['bank_account_id'] ?? 0)->where('user_id', $payroll->user_id)->first();
            if (!$account) {
                throw new \RuntimeException(__('That bank account does not belong to this staff member.'));
            }

            $payroll->pay_date = $data['pay_date'];
            $payroll->payment_method = $data['payment_method'];
            $payroll->bank_account_id = $account->id;
            $payroll->note = $data['note'] ?? null;
            $payroll->status = '1';
            $payroll->updated_by = $byUserId;
            $payroll->save();

            $transaction = new Transaction;
            $transaction->transaction_id = Str::random(16);
            $transaction->amount = $payroll->net_salary;
            $transaction->type = '2';
            $transaction->created_by = $byUserId;
            $payroll->user->transactions()->save($transaction);

            // What each tax took, before the journal entry reads it to decide
            // which liability each withholding accrues to.
            $breakdown = app(PayrollTaxBreakdownService::class)->record($payroll);
            if (!$breakdown['reconciled']) {
                Log::warning('PayrollPayer — itemised taxes for payroll #' . $payroll->id . ' did not reconcile to the payroll total; no breakdown recorded.');
            }

            $journal = $this->accounting->createPayrollJournalEntry($payroll, $byUserId);

            return ['payroll' => $payroll, 'journal' => (bool) $journal];
        });

        $emailed = null;
        $error = null;

        // After the payment is committed: a mail server that is down must not
        // undo a payment that has happened.
        if ($settings->email_payslip_on_pay) {
            $sent = app(PayslipService::class)->email($result['payroll']);
            $emailed = $sent === true;
            $error = $sent === true ? null : $sent;
        }

        return ['journal' => $result['journal'], 'emailed' => $emailed, 'email_error' => $error];
    }
}
