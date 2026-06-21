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
        Schema::table('resit_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('resit_requests', 'resit_semester_id')) {
                $table->unsignedInteger('resit_semester_id')->nullable()->after('resit_session_id');
                $table->foreign('resit_semester_id')->references('id')->on('semesters')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resit_requests', function (Blueprint $table) {
            if (Schema::hasColumn('resit_requests', 'resit_semester_id')) {
                $table->dropForeign(['resit_semester_id']);
                $table->dropColumn('resit_semester_id');
            }
        });
    }
};
