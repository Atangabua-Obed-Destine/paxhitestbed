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
        Schema::table('degree_types', function (Blueprint $table) {
            $table->string('code_append_to_student_matricule', 10)->nullable()->after('shortcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('degree_types', function (Blueprint $table) {
            $table->dropColumn('code_append_to_student_matricule');
        });
    }
};
