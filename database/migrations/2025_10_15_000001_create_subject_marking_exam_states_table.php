<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('subject_marking_workflow_logs')) {
            $foreignKeys = [
                'sm_logs_exam_state_fk',
                'subject_marking_workflow_logs_subject_marking_exam_state_id_foreign',
                'sm_logs_exam_type_fk',
                'subject_marking_workflow_logs_exam_type_id_foreign',
            ];

            foreach ($foreignKeys as $foreignKey) {
                try {
                    DB::statement("ALTER TABLE subject_marking_workflow_logs DROP FOREIGN KEY {$foreignKey}");
                } catch (\Throwable $e) {
                    // Ignore missing keys during idempotent reruns
                }
            }

            $columnsToDrop = [];

            if (Schema::hasColumn('subject_marking_workflow_logs', 'subject_marking_exam_state_id')) {
                $columnsToDrop[] = 'subject_marking_exam_state_id';
            }

            if (Schema::hasColumn('subject_marking_workflow_logs', 'exam_type_id')) {
                $columnsToDrop[] = 'exam_type_id';
            }

            if (!empty($columnsToDrop)) {
                Schema::table('subject_marking_workflow_logs', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }

        Schema::dropIfExists('subject_marking_exam_states');

        Schema::create('subject_marking_exam_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subject_marking_id');
            $table->unsignedInteger('exam_type_id');
            $table->string('workflow_state')->default('draft');
            $table->timestamp('state_changed_at')->nullable();
            $table->unsignedBigInteger('state_changed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->date('publish_date')->nullable();
            $table->time('publish_time')->nullable();
            $table->timestamps();

            $table->unique(['subject_marking_id', 'exam_type_id'], 'sm_exam_state_unique');
            $table->foreign('subject_marking_id', 'sm_exam_states_marking_fk')->references('id')->on('subject_markings')->onDelete('cascade');
            $table->foreign('exam_type_id', 'sm_exam_states_type_fk')->references('id')->on('exam_types')->onDelete('cascade');
        });

        Schema::table('subject_marking_workflow_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('subject_marking_workflow_logs', 'subject_marking_exam_state_id')) {
                $table->unsignedBigInteger('subject_marking_exam_state_id')->nullable()->after('subject_marking_id');
            }

            if (!Schema::hasColumn('subject_marking_workflow_logs', 'exam_type_id')) {
                $table->unsignedInteger('exam_type_id')->nullable()->after('subject_marking_exam_state_id');
            }

            $table->foreign('subject_marking_exam_state_id', 'sm_logs_exam_state_fk')->references('id')->on('subject_marking_exam_states')->onDelete('cascade');
            $table->foreign('exam_type_id', 'sm_logs_exam_type_fk')->references('id')->on('exam_types')->onDelete('cascade');
        });

        $markings = DB::table('subject_markings')->select([
            'id',
            'student_enroll_id',
            'subject_id',
            'workflow_state',
            'state_changed_at',
            'state_changed_by',
            'reviewed_at',
            'reviewed_by',
            'review_notes',
            'publish_date',
            'publish_time',
        ])->get();

        foreach ($markings as $marking) {
            $examTypeIds = DB::table('exams')
                ->where('student_enroll_id', $marking->student_enroll_id)
                ->where('subject_id', $marking->subject_id)
                ->pluck('exam_type_id')
                ->filter()
                ->unique();

            foreach ($examTypeIds as $examTypeId) {
                DB::table('subject_marking_exam_states')->insert([
                    'subject_marking_id' => $marking->id,
                    'exam_type_id' => $examTypeId,
                    'workflow_state' => $marking->workflow_state ?? 'draft',
                    'state_changed_at' => $marking->state_changed_at,
                    'state_changed_by' => $marking->state_changed_by,
                    'reviewed_at' => $marking->reviewed_at,
                    'reviewed_by' => $marking->reviewed_by,
                    'review_notes' => $marking->review_notes,
                    'publish_date' => $marking->publish_date,
                    'publish_time' => $marking->publish_time,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_marking_workflow_logs', function (Blueprint $table) {
            if (Schema::hasColumn('subject_marking_workflow_logs', 'subject_marking_exam_state_id')) {
                $table->dropForeign('sm_logs_exam_state_fk');
                $table->dropColumn('subject_marking_exam_state_id');
            }

            if (Schema::hasColumn('subject_marking_workflow_logs', 'exam_type_id')) {
                $table->dropForeign('sm_logs_exam_type_fk');
                $table->dropColumn('exam_type_id');
            }
        });

        Schema::dropIfExists('subject_marking_exam_states');
    }
};
