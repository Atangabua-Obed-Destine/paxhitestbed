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
        Schema::table('exam_publishing_workflow_logs', function (Blueprint $table) {
            // Add missing 'action' column after 'to_state'
            if (!Schema::hasColumn('exam_publishing_workflow_logs', 'action')) {
                $table->string('action', 100)->nullable()->after('to_state');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_publishing_workflow_logs', function (Blueprint $table) {
            if (Schema::hasColumn('exam_publishing_workflow_logs', 'action')) {
                $table->dropColumn('action');
            }
        });
    }
};
