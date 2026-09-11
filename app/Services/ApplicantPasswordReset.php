<?php

namespace App\Services;

use App\Mail\ApplicantForgotPassword;
use App\Models\Applicant;
use App\Models\MailSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Email an applicant a link to choose a new password.
 *
 * One implementation for both doors: the "forgot password" page on the
 * application portal, and the "email a reset link" button on the admin
 * Applicants screen. Two copies of this would drift — a changed token rule or
 * email in one and not the other — and the applicant would get different
 * results depending on who asked.
 *
 * The admin never sees or chooses the password this way; the applicant does.
 */
class ApplicantPasswordReset
{
    public const SENT = 'sent';
    public const MAIL_NOT_CONFIGURED = 'mail_not_configured';
    public const FAILED = 'failed';

    public function send(Applicant $applicant): string
    {
        $mail = MailSetting::where('status', '1')->first();

        if (!$mail || !$mail->sender_email || !$mail->sender_name) {
            return self::MAIL_NOT_CONFIGURED;
        }

        try {
            $token = bin2hex(random_bytes(32));

            // One live link per applicant: asking again replaces the old one.
            DB::table('password_resets')->where('email', $applicant->email)->delete();
            DB::table('password_resets')->insert([
                'email' => $applicant->email,
                'token' => $token,
                'created_at' => now(),
            ]);

            Mail::to($applicant->email)->send(new ApplicantForgotPassword([
                'first_name' => $applicant->first_name,
                'last_name' => $applicant->last_name,
                'email' => $applicant->email,
                'token' => $token,
                'subject' => __('Application Portal - Password Reset Request'),
                'from' => $mail->sender_email,
                'sender' => $mail->sender_name,
                'reset_url' => route('application.password.reset', [$token, $applicant->email]),
            ]));

            return self::SENT;
        } catch (\Exception $e) {
            report($e);

            return self::FAILED;
        }
    }
}
