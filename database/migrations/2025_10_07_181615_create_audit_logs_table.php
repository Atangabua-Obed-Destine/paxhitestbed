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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // User who performed the action
            $table->string('user_type')->nullable(); // User type (e.g., Admin, Student)
            $table->string('event'); // created, updated, deleted, logged_in, etc.
            $table->string('auditable_type'); // Model type (Student, SubjectMarking, Fee, etc.)
            $table->unsignedBigInteger('auditable_id')->nullable(); // Model ID
            $table->text('old_values')->nullable(); // JSON of old values
            $table->text('new_values')->nullable(); // JSON of new values
            $table->string('ip_address', 45)->nullable(); // User's IP address
            $table->string('user_agent')->nullable(); // Browser/device info
            $table->string('url')->nullable(); // URL where action occurred
            $table->text('description')->nullable(); // Human-readable description
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['user_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('event');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
