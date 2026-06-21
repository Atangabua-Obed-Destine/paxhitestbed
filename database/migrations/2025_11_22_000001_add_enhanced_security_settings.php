<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert security settings for password policy
        $settings = [
            // Password Policy Settings
            [
                'key' => 'password_min_length',
                'value' => '8',
                'type' => 'integer',
                'description' => 'Minimum password length required',
            ],
            [
                'key' => 'password_require_uppercase',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require at least one uppercase letter',
            ],
            [
                'key' => 'password_require_lowercase',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require at least one lowercase letter',
            ],
            [
                'key' => 'password_require_numbers',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require at least one number',
            ],
            [
                'key' => 'password_require_symbols',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require at least one special character',
            ],
            [
                'key' => 'password_expiry_days',
                'value' => '90',
                'type' => 'integer',
                'description' => 'Password expiry in days (0 = never)',
            ],
            [
                'key' => 'password_history_count',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Number of previous passwords to remember',
            ],

            // File Upload Security
            [
                'key' => 'max_upload_size_mb',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Maximum file upload size in MB',
            ],
            [
                'key' => 'enable_file_upload_logging',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Log all file upload activities',
            ],
            [
                'key' => 'scan_uploads_for_viruses',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable virus scanning for uploads (requires ClamAV)',
            ],

            // Session Security
            [
                'key' => 'session_lifetime_minutes',
                'value' => '120',
                'type' => 'integer',
                'description' => 'Session lifetime in minutes',
            ],
            [
                'key' => 'force_https',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Force HTTPS for all requests',
            ],
            [
                'key' => 'enable_session_encryption',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Encrypt session data',
            ],

            // Login Security (Additional)
            [
                'key' => 'max_login_attempts',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum failed login attempts before lockout',
            ],
            [
                'key' => 'lockout_duration',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Lockout duration in minutes',
            ],
            [
                'key' => 'auto_block_threshold',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Auto-block account after this many failed attempts',
            ],

            // System-wide 2FA Settings
            [
                'key' => 'enable_2fa_admin',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable 2FA for all admin users',
            ],
            [
                'key' => 'enable_2fa_student',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable 2FA for all students',
            ],
            [
                'key' => '2fa_mandatory_admin',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Make 2FA mandatory for admin users',
            ],
            [
                'key' => '2fa_mandatory_student',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Make 2FA mandatory for students',
            ],

            // Security Monitoring
            [
                'key' => 'enable_security_email_alerts',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Send email alerts for security events',
            ],
            [
                'key' => 'security_alert_email',
                'value' => 'admin@paxhi.org',
                'type' => 'string',
                'description' => 'Email address for security alerts',
            ],
            [
                'key' => 'log_all_login_attempts',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Log all login attempts (successful and failed)',
            ],
        ];

        foreach ($settings as $setting) {
            // Check if setting already exists
            $exists = DB::table('security_settings')
                ->where('key', $setting['key'])
                ->exists();

            if (!$exists) {
                DB::table('security_settings')->insert(array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the security settings added
        $keys = [
            'password_min_length',
            'password_require_uppercase',
            'password_require_lowercase',
            'password_require_numbers',
            'password_require_symbols',
            'password_expiry_days',
            'password_history_count',
            'max_upload_size_mb',
            'enable_file_upload_logging',
            'scan_uploads_for_viruses',
            'session_lifetime_minutes',
            'force_https',
            'enable_session_encryption',
            'max_login_attempts',
            'lockout_duration',
            'auto_block_threshold',
            'enable_2fa_admin',
            'enable_2fa_student',
            '2fa_mandatory_admin',
            '2fa_mandatory_student',
            'enable_security_email_alerts',
            'security_alert_email',
            'log_all_login_attempts',
        ];

        DB::table('security_settings')->whereIn('key', $keys)->delete();
    }
};
