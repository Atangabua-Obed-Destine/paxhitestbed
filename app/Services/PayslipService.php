<?php

namespace App\Services;

use App\Models\MailSetting;
use App\Models\Payroll;
use App\Models\PrintSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * A payroll's payslip: what it shows, as a PDF, and by email.
 *
 * The printed page, the staff member's own copy and the emailed PDF all take
 * their figures from data() so they cannot disagree.
 */
class PayslipService
{
    /**
     * Everything the payslip views need.
     *
     * The taxes are the ones this payroll was actually charged, from its own
     * itemised lines, never recomputed from today's rates.
     */
    public function data(Payroll $payroll): array
    {
        $payroll->loadMissing(['user.department', 'user.designation', 'details', 'bankAccount', 'taxLines', 'approver']);

        $lines = $payroll->taxLines->map(fn ($l) => [
            'label' => $l->label,
            'employee' => (float) $l->employee_amount,
            'employer' => (float) $l->employer_amount,
        ])->all();

        // A payroll saved before taxes were itemised has no lines. Work them
        // out as at its own month and show them only if they add up to what
        // was actually withheld.
        $reconcile = true;
        if (empty($lines) && ((float) $payroll->tax > 0 || (float) $payroll->employer_tax > 0)) {
            $breakdown = app(PayrollTaxBreakdownService::class);
            $computed = $breakdown->forPayroll($payroll);
            $reconcile = $breakdown->reconciles($payroll, $computed);
            $lines = $reconcile ? $computed : [];
        }

        return [
            'row' => $payroll,
            'print' => PrintSetting::where('slug', 'pay-slip')->first(),
            'path' => 'print-setting',
            'tax_lines' => $lines,
            'tax_lines_reconcile' => $reconcile,
        ];
    }

    /** The payslip as a PDF document (binary string). */
    public function pdf(Payroll $payroll): string
    {
        return Pdf::loadView('admin.payroll.payslip-pdf', $this->data($payroll))
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function filename(Payroll $payroll): string
    {
        $who = preg_replace('/[^A-Za-z0-9]+/', '-', trim(($payroll->user->staff_id ?? $payroll->user_id)));

        return 'payslip-' . trim($who, '-') . '-' . date('Y-m', strtotime($payroll->salary_month)) . '.pdf';
    }

    /**
     * Email a paid payroll's payslip to the staff member.
     *
     * @return true|string  true when sent, otherwise why it was not
     */
    public function email(Payroll $payroll)
    {
        $payroll->loadMissing('user');

        if ((int) $payroll->status !== 1) {
            return __('Only a paid payroll has a payslip to send.');
        }

        $to = trim((string) ($payroll->user->email ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return __('The staff member has no valid email address on their record.');
        }

        $mail = MailSetting::where('status', '1')->first();
        $from = $mail->sender_email ?? config('mail.from.address');
        $sender = $mail->sender_name ?? config('mail.from.name');

        if (!$from) {
            return __('No sender email is configured in the mail settings.');
        }

        try {
            $month = date('F Y', strtotime($payroll->salary_month));
            $pdf = $this->pdf($payroll);
            $filename = $this->filename($payroll);

            Mail::to($to)->send(new \App\Mail\PayslipMail([
                'from' => $from,
                'sender' => $sender,
                'subject' => __('Your payslip for :month', ['month' => $month]),
                'first_name' => $payroll->user->first_name,
                'month' => $month,
                'net' => (float) $payroll->net_salary,
                'pdf' => $pdf,
                'filename' => $filename,
            ]));

            $payroll->forceFill(['payslip_emailed_at' => now()])->saveQuietly();

            return true;
        } catch (\Throwable $e) {
            Log::error('Payslip email failed for payroll #' . $payroll->id . ': ' . $e->getMessage());

            return __('The email could not be sent: :error', ['error' => $e->getMessage()]);
        }
    }
}
