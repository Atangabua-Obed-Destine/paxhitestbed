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
        Schema::table('subject_markings', function (Blueprint $table) {
            // Per-student publish control
            $table->boolean('is_published_override')->nullable()->after('workflow_state')
                ->comment('NULL = follow section workflow, TRUE = force published, FALSE = force unpublished');
            
            // Unpublish tracking
            $table->text('unpublish_reason')->nullable()->after('is_published_override')
                ->comment('Reason for unpublishing individual student result');
            $table->unsignedBigInteger('unpublished_by')->nullable()->after('unpublish_reason');
            $table->timestamp('unpublished_at')->nullable()->after('unpublished_by');
            
            // Republish tracking
            $table->unsignedBigInteger('republished_by')->nullable()->after('unpublished_at');
            $table->timestamp('republished_at')->nullable()->after('republished_by');
            
            // Foreign keys
            $table->foreign('unpublished_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('republished_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_markings', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['unpublished_by']);
            $table->dropForeign(['republished_by']);
            
            // Drop columns
            $table->dropColumn([
                'is_published_override',
                'unpublish_reason',
                'unpublished_by',
                'unpublished_at',
                'republished_by',
                'republished_at',
            ]);
        });
    }
};
