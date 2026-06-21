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
            $table->string('workflow_state')->default('draft')->after('status');
            $table->timestamp('state_changed_at')->nullable()->after('workflow_state');
            $table->unsignedBigInteger('state_changed_by')->nullable()->after('state_changed_at');
            $table->decimal('resolved_exam_weight', 5, 2)->nullable()->after('activities');
            $table->decimal('resolved_ca_weight', 5, 2)->nullable()->after('resolved_exam_weight');
            $table->decimal('resolved_attendance_weight', 5, 2)->nullable()->after('resolved_ca_weight');
            $table->boolean('validated')->default(true)->after('total_marks');
        });

        Schema::create('subject_marking_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subject_marking_id');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();

            $table->foreign('subject_marking_id')->references('id')->on('subject_markings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_marking_workflow_logs');

        Schema::table('subject_markings', function (Blueprint $table) {
            $table->dropColumn([
                'workflow_state',
                'state_changed_at',
                'state_changed_by',
                'resolved_exam_weight',
                'resolved_ca_weight',
                'resolved_attendance_weight',
                'validated',
            ]);
        });
    }
};
