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
            $table->boolean('bypass_payment_restriction')->default(0)->after('status')->comment('0: No, 1: Yes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->dropColumn('bypass_payment_restriction');
        });
    }
};
