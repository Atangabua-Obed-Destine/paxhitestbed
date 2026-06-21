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
        Schema::create('subject_marking_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subject_marking_id');
            $table->enum('action', ['unpublish', 'republish'])->comment('Action performed');
            $table->text('reason')->nullable()->comment('Reason for the action');
            $table->unsignedBigInteger('performed_by')->comment('User who performed the action');
            $table->string('previous_state')->nullable()->comment('Previous publish override state');
            $table->string('new_state')->nullable()->comment('New publish override state');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('subject_marking_id')->references('id')->on('subject_markings')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index('subject_marking_id');
            $table->index('performed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_marking_publish_logs');
    }
};
