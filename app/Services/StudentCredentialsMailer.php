<?php

namespace App\Services;

use App\Mail\SendPassword;
use App\Models\MailSetting;
use App\Models\Student;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a newly admitted student their portal login.
 *
 * The counterpart of StaffCredentialsMailer, and the same reasoning: a student
 * who never receives this cannot sign in, register courses or see their fees,
 * and nothing else in the system will notice. It used to be sent inside the
 * conversion's transaction with its failure written only to the log, so the
 * administrator who admitted them was told the admission succeeded and nothing
 * about the login never arriving.
 *
 * Never throws. The student record is what matters; the e-mail can be sent
 * again from the student's own page.
 */
class StudentCredentialsMailer
{
    /**
     * @param  string|null  $password  the plain password, when the caller has
     *                                 just generated it; otherwise it is read
     *                                 back from the student's stored copy.
     * @return array{sent: bool, reason: ?string}
     */
    public function send(Student $student, ?string $password = null): array
    {
        if (trim((string) $student->email) === '') {
            return $this->no(__('this student has no email address'));
        }

        $mail = MailSetting::where('status', '1')->first();

        if (!$mail || !filled($mail->sender_email) || !filled($mail->sender_name)) {
            return $this->no(__('the school\'s mail settings are not configured'));
        }

        $password = $password ?? $this->storedPassword($student);

        if ($password === null || $password === '') {
            return $this->no(__('this student has no recoverable password; set a new one first'));
        }

        try {
            Mail::to($student->email, trim($student->first_name . ' ' . $student->last_name))
                ->send(new SendPassword([
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    // The template keys off student_id to show the student's
                    // greeting and point the login button at the student portal
                    // rather than the staff one.
                    'student_id' => $student->student_id,
                    'email' => $student->email,
                    'password' => $password,
                    'subject' => __('msg_your_login_credentials'),
                    'from' => $mail->sender_email,
                    'sender' => $mail->sender_name,
                ]));
        } catch (\Throwable $e) {
            // Logged in full, reported in brief: an SMTP reply can quote the
            // credentials back, and the reason is shown on screen.
            Log::error('Student credentials email failed for student ' . $student->id . ': ' . $e->getMessage());

            return $this->no(__('the mail server would not accept it'));
        }

        return ['sent' => true, 'reason' => null];
    }

    private function storedPassword(Student $student): ?string
    {
        if (!filled($student->password_text)) {
            return null;
        }

        try {
            return Crypt::decryptString($student->password_text);
        } catch (\Throwable $e) {
            // A changed APP_KEY makes every stored password undecryptable.
            Log::warning('Could not decrypt stored password for student ' . $student->id . ': ' . $e->getMessage());

            return null;
        }
    }

    private function no(string $reason): array
    {
        return ['sent' => false, 'reason' => $reason];
    }
}
