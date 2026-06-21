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
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('bypass_course_attendance')->default(false)->after('attendance')->comment('Bypass minimum course attendance requirement');
            $table->unsignedBigInteger('bypassed_by')->nullable()->after('bypass_course_attendance');
            $table->timestamp('bypassed_at')->nullable()->after('bypassed_by');
            
            $table->foreign('bypassed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['bypassed_by']);
            $table->dropColumn(['bypass_course_attendance', 'bypassed_by', 'bypassed_at']);
        });
    }
};
