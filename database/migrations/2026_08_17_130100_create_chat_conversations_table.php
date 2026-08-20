<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One thread per actor per surface.
 *
 * actor_type is deliberately a plain string rather than a polymorphic relation:
 * the four actors live behind different auth guards (web / student / applicant)
 * plus anonymous guests, so there is no single parent model to point at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();

            $table->string('actor_type', 20)->comment('guest|applicant|student|user');
            $table->unsignedBigInteger('actor_id')->nullable()->comment('Null for guests');
            $table->string('session_key', 100)->nullable()->comment('Identifies a guest thread');

            $table->string('surface', 20)->comment('web|application|student|admin');
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['actor_type', 'actor_id'], 'chat_conversations_actor_index');
            $table->index('session_key');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
