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
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->integer('previous_program_id')->unsigned()->nullable()->after('program_id');
            $table->text('program_change_reason')->nullable()->after('previous_program_id');
            $table->boolean('is_program_change')->default(0)->after('program_change_reason');
            
            $table->foreign('previous_program_id')->references('id')->on('programs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->dropForeign(['previous_program_id']);
            $table->dropColumn(['previous_program_id', 'program_change_reason', 'is_program_change']);
        });
    }
};
