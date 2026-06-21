<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('two_factor_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code', 6);
            $table->string('user_type'); // admin, student, applicant
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['email', 'user_type']);
            $table->index('expires_at');
        });

        // Add 2FA settings to security_settings table
        DB::table('security_settings')->insert([
            [
                'key' => 'enable_2fa_admin',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable Two-Factor Authentication for Admin users',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'enable_2fa_student',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable Two-Factor Authentication for Student users',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => '2fa_code_expiry',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Two-Factor Authentication code expiry time in minutes',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => '2fa_mandatory_admin',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Make 2FA mandatory for all admin users',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => '2fa_mandatory_student',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Make 2FA mandatory for all student users',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_codes');
        
        DB::table('security_settings')->whereIn('key', [
            'enable_2fa_admin',
            'enable_2fa_student',
            '2fa_code_expiry',
            '2fa_mandatory_admin',
            '2fa_mandatory_student',
        ])->delete();
    }
};
