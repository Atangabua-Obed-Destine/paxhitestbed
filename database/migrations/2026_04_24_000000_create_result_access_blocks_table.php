<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('result_access_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('program_id');
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('semester_id');
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('blocked_by')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->unsignedBigInteger('unblocked_by')->nullable();
            $table->timestamp('unblocked_at')->nullable();
            $table->text('unblock_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'program_id', 'session_id', 'semester_id', 'is_active'], 'rab_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_access_blocks');
    }
};
