<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default security settings
        DB::table('security_settings')->insert([
            [
                'key' => 'max_login_attempts',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum number of failed login attempts before lockout',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'lockout_duration',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Lockout duration in minutes after max attempts reached',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'auto_block_threshold',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Number of failed attempts before permanent account block',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'session_timeout',
                'value' => '720',
                'type' => 'integer',
                'description' => 'Session timeout in minutes',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'enable_ip_whitelist',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable IP whitelisting for admin access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'min_password_length',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Minimum password length requirement',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'require_password_complexity',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require uppercase, lowercase, numbers, and special characters in passwords',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('security_settings');
    }
};
