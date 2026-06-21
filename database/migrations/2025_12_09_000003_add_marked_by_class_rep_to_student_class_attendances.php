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
        Schema::table('student_class_attendances', function (Blueprint $table) {
            $table->boolean('marked_by_class_rep')->default(false)->after('clock_out_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_class_attendances', function (Blueprint $table) {
            $table->dropColumn('marked_by_class_rep');
        });
    }
};
