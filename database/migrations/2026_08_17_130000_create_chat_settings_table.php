<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row configuration for the context-aware chat module, following the
 * same convention as platform_fee_settings / application_settings.
 *
 * The per-surface flags are what the widget partial reads, so turning the
 * assistant on or off anywhere is purely a settings change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_settings', function (Blueprint $table) {
            $table->id();

            $table->string('title')->default('Ask CATUC');
            $table->boolean('is_enabled')->default(false)->comment('Global kill switch');

            // Per-surface visibility.
            $table->boolean('enabled_web')->default(false)->comment('Public website');
            $table->boolean('enabled_application')->default(false)->comment('Application portal');
            $table->boolean('enabled_student')->default(false)->comment('Student portal');
            $table->boolean('enabled_admin')->default(false)->comment('Admin portal');

            // Provider configuration. Kept as columns rather than env so the
            // model can be changed without a deployment.
            $table->string('provider', 50)->default('gemini');
            $table->string('model', 100)->default('gemini-3.5-flash');
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->unsignedInteger('max_output_tokens')->default(1024);
            $table->unsignedTinyInteger('max_tool_calls')->default(3)
                ->comment('Tool round-trips per message; bounds cost and latency');

            // Operator-supplied guidance appended to the generated system prompt.
            $table->text('system_prompt')->nullable();

            $table->text('greeting_web')->nullable();
            $table->text('greeting_application')->nullable();
            $table->text('greeting_student')->nullable();
            $table->text('greeting_admin')->nullable();

            $table->unsignedInteger('rate_limit_per_minute')->default(10);
            $table->unsignedInteger('history_retention_days')->default(90);
            $table->boolean('escalation_enabled')->default(true);

            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        // Seed the single row so the settings screen always has something to edit.
        DB::table('chat_settings')->insert([
            'title' => 'Ask CATUC',
            'is_enabled' => false,
            'enabled_web' => false,
            'enabled_application' => false,
            'enabled_student' => false,
            'enabled_admin' => false,
            'provider' => 'gemini',
            'model' => 'gemini-3.5-flash',
            'temperature' => 0.20,
            'max_output_tokens' => 1024,
            'max_tool_calls' => 3,
            'greeting_web' => 'Hello! Ask me about our programmes, admission requirements or intake dates.',
            'greeting_application' => 'Hello! I can help with your application status, outstanding documents and the admission fee.',
            'greeting_student' => 'Hello! Ask me about your fees, results, attendance or timetable.',
            'greeting_admin' => 'Hello! Ask me about applications, students and fees, or how to use a module.',
            'rate_limit_per_minute' => 10,
            'history_retention_days' => 90,
            'escalation_enabled' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_settings');
    }
};
