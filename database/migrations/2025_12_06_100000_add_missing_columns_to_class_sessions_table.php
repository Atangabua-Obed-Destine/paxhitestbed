<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToClassSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            // Add is_extra_class column if it doesn't exist
            if (!Schema::hasColumn('class_sessions', 'is_extra_class')) {
                $table->boolean('is_extra_class')->default(false)->after('is_scheduled');
            }
            
            // Add scheduled_routine_id if class_routine_id exists (rename scenario)
            if (!Schema::hasColumn('class_sessions', 'scheduled_routine_id') && Schema::hasColumn('class_sessions', 'class_routine_id')) {
                // We'll use the existing class_routine_id, just add an alias approach in model
            }
            
            // Add topic_covered if missing
            if (!Schema::hasColumn('class_sessions', 'topic_covered')) {
                $table->string('topic_covered', 500)->nullable()->after('status');
            }
            
            // Add learning_objectives if missing
            if (!Schema::hasColumn('class_sessions', 'learning_objectives')) {
                $table->text('learning_objectives')->nullable()->after('topic_covered');
            }
            
            // Add teaching_methods if missing
            if (!Schema::hasColumn('class_sessions', 'teaching_methods')) {
                $table->text('teaching_methods')->nullable()->after('learning_objectives');
            }
            
            // Add materials_used if missing
            if (!Schema::hasColumn('class_sessions', 'materials_used')) {
                $table->text('materials_used')->nullable()->after('teaching_methods');
            }
            
            // Add assignments_given if missing
            if (!Schema::hasColumn('class_sessions', 'assignments_given')) {
                $table->text('assignments_given')->nullable()->after('materials_used');
            }
            
            // Add remarks if missing
            if (!Schema::hasColumn('class_sessions', 'remarks')) {
                $table->text('remarks')->nullable()->after('assignments_given');
            }
            
            // Add class_rep_enroll_id if missing
            if (!Schema::hasColumn('class_sessions', 'class_rep_enroll_id')) {
                $table->bigInteger('class_rep_enroll_id')->unsigned()->nullable()->after('remarks');
            }
            
            // Add hod_user_id if missing
            if (!Schema::hasColumn('class_sessions', 'hod_user_id')) {
                $table->bigInteger('hod_user_id')->unsigned()->nullable()->after('class_rep_enroll_id');
            }
            
            // Add lecturer_attendance_synced if missing
            if (!Schema::hasColumn('class_sessions', 'lecturer_attendance_synced')) {
                $table->boolean('lecturer_attendance_synced')->default(false)->after('lecturer_attendance_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $columns = [
                'is_extra_class',
                'topic_covered',
                'learning_objectives',
                'teaching_methods',
                'materials_used',
                'assignments_given',
                'remarks',
                'class_rep_enroll_id',
                'hod_user_id',
                'lecturer_attendance_synced',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('class_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
