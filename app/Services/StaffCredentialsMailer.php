<?php

namespace App\Services;

use App\Mail\SendPassword;
use App\Models\MailSetting;
use App\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a member of staff their login details.
 *
 * Used both when the account is created and when an administrator asks for the
 * details to be sent again, so the two cannot drift apart — one of them would
 * otherwise end up with a fix the other never got.
 *
 * Never throws. A mail server that is down must not look like a staff account
 * that failed to save: the account is the thing that matters, and the e-mail
 * can be sent again from the staff list at any time. Callers are told whether
 * it went and, when it did not, a reason they can act on.
 */
class StaffCredentialsMailer
{
    /**
     * @return array{sent: bool, reason: ?string}
     */
    public function send(User $user): array
    {
        if (trim((string) $user->email) === '') {
            return $this->no(__('this member of staff has no email address'));
        }

        $mail = MailSetting::where('status', '1')->first();

        if (!$mail || !filled($mail->sender_email) || !filled($mail->sender_name)) {
            return $this->no(__('the school\'s mail settings are not configured'));
        }

        // The account stores its generated password encrypted so it can be sent
        // again later. Accounts made before that, or whose record was changed
        // by hand, have nothing recoverable — and a password reset is the right
        // answer then, not a blank e-mail.
        $password = $this->password($user);

        if ($password === null) {
            return $this->no(__('this account has no recoverable password; set a new one first'));
        }

        try {
            Mail::to($user->email, trim($user->first_name . ' ' . $user->last_name))
                ->send(new SendPassword([
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'staff_id' => $user->staff_id,
                    'email' => $user->email,
                    'password' => $password,
                    'subject' => __('msg_your_login_credentials'),
                    'from' => $mail->sender_email,
                    'sender' => $mail->sender_name,
                ]));
        } catch (\Throwable $e) {
            // Logged in full, reported in brief: the SMTP reply can quote the
            // credentials back, and this reason is shown on screen.
            Log::error('Staff credentials email failed for user ' . $user->id . ': ' . $e->getMessage());

            return $this->no(__('the mail server would not accept it'));
        }

        return ['sent' => true, 'reason' => null];
    }

    /** The password as it was issued, or null when it cannot be recovered. */
    private function password(User $user): ?string
    {
        if (!filled($user->password_text)) {
            return null;
        }

        try {
            return Crypt::decryptString($user->password_text);
        } catch (\Throwable $e) {
            // A changed APP_KEY makes every stored password undecryptable. That
            // is worth a log line, not a crash on a staff screen.
            Log::warning('Could not decrypt stored password for user ' . $user->id . ': ' . $e->getMessage());

            return null;
        }
    }

    private function no(string $reason): array
    {
        return ['sent' => false, 'reason' => $reason];
    }
}
