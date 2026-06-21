<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Mail\TwoFactorCode as TwoFactorCodeMail;

class TwoFactorCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'user_type',
        'expires_at',
        'is_used',
        'used_at',
        'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    /**
     * Generate and send a new 2FA code
     */
    public static function generateAndSend(string $email, string $userType, string $userName): self
    {
        // Delete old unused codes
        static::where('email', $email)
            ->where('user_type', $userType)
            ->where('is_used', false)
            ->delete();

        // Generate 6-digit code
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Get expiry time from settings
        $expiryMinutes = SecuritySetting::getValue('2fa_code_expiry', 10);

        // Create new code
        $twoFactorCode = static::create([
            'email' => $email,
            'code' => $code,
            'user_type' => $userType,
            'expires_at' => now()->addMinutes($expiryMinutes),
            'ip_address' => request()->ip(),
        ]);

        // Send email using system mail settings
        try {
            $mail = \App\Models\MailSetting::where('status', '1')->first();

            if(isset($mail->sender_email) && isset($mail->sender_name)){
                // Prepare email data
                $data = [
                    'code' => $code,
                    'userName' => $userName,
                    'expiryMinutes' => $expiryMinutes,
                    'subject' => 'Two-Factor Authentication Code',
                    'from' => $mail->sender_email,
                    'sender' => $mail->sender_name,
                ];

                // Send mail
                Mail::to($email, $userName)->send(new TwoFactorCodeMail($data));
            } else {
                \Log::error('2FA email not sent: Mail settings not configured');
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send 2FA email: ' . $e->getMessage());
        }

        return $twoFactorCode;
    }

    /**
     * Verify a 2FA code
     */
    public static function verify(string $email, string $code, string $userType, string $ipAddress = null): bool
    {
        $twoFactorCode = static::where('email', $email)
            ->where('code', $code)
            ->where('user_type', $userType)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($twoFactorCode) {
            $twoFactorCode->update([
                'is_used' => true,
                'used_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check if code is expired
     */
    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    /**
     * Check if code is valid (not used and not expired)
     */
    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }
}
