<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The in-system mailbox: conversations between staff and students.
 *
 * A member is a (type, id) pair: 'staff' = users.id, 'student' = students.id.
 * A conversation (thread) has participants; every participant sees every
 * message in it. A group send (broadcast) creates one private thread per
 * recipient, so each reply goes to the sender alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('sender_type', 16);
            $table->unsignedBigInteger('sender_id');
            $table->string('subject', 190);
            $table->text('body');
            $table->string('audience', 255);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamps();
            $table->index(['sender_type', 'sender_id']);
        });

        Schema::create('mailbox_threads', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 190);
            $table->foreignId('broadcast_id')->nullable()->constrained('mailbox_broadcasts')->nullOnDelete();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->string('created_by_type', 16);
            $table->unsignedBigInteger('created_by_id');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('mailbox_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('mailbox_threads')->cascadeOnDelete();
            $table->string('member_type', 16);
            $table->unsignedBigInteger('member_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['thread_id', 'member_type', 'member_id'], 'mailbox_participant_unique');
            $table->index(['member_type', 'member_id']);
        });

        Schema::create('mailbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('mailbox_threads')->cascadeOnDelete();
            $table->string('sender_type', 16);
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->timestamps();
            $table->index(['thread_id', 'created_at']);
        });

        Schema::create('mailbox_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('mailbox_messages')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        // Offices students can write to (Bursary, Registry, ...), each staffed
        // by named members of staff who all see the conversation.
        Schema::create('mailbox_offices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('mailbox_office_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('mailbox_offices')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['office_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_office_members');
        Schema::dropIfExists('mailbox_offices');
        Schema::dropIfExists('mailbox_attachments');
        Schema::dropIfExists('mailbox_messages');
        Schema::dropIfExists('mailbox_participants');
        Schema::dropIfExists('mailbox_threads');
        Schema::dropIfExists('mailbox_broadcasts');
    }
};
