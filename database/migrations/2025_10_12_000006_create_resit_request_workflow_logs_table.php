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
        Schema::create('resit_request_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resit_request_id');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('resit_request_id')->references('id')->on('resit_requests')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resit_request_workflow_logs');
    }
};
