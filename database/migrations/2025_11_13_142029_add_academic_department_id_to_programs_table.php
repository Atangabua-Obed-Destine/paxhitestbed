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
        Schema::table('programs', function (Blueprint $table) {
            $table->integer('academic_department_id')->unsigned()->nullable()->after('faculty_id');
            
            $table->foreign('academic_department_id')
                  ->references('id')->on('academic_departments')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['academic_department_id']);
            $table->dropColumn('academic_department_id');
        });
    }
};
