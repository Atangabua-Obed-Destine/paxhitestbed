<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail. Every question, every tool the model chose, the arguments it
 * supplied and what came back — this is what makes a data-access assistant
 * reviewable after the fact.
 *
 * acting_user_id records the REAL staff member when an admin is impersonating a
 * student (student.impersonate): the answer is scoped to the student, but the
 * audit must not lose who actually asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('chat_conversations')
                ->cascadeOnDelete();

            $table->string('role', 20)->comment('user|assistant|tool');
            $table->longText('content')->nullable();

            // Populated on tool rows.
            $table->string('tool_name', 100)->nullable();
            $table->json('tool_args')->nullable();
            $table->json('tool_result')->nullable();
            $table->string('tool_status', 20)->nullable()->comment('ok|denied|error');

            $table->unsignedBigInteger('acting_user_id')->nullable()
                ->comment('Real staff user when impersonating');

            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index('tool_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
