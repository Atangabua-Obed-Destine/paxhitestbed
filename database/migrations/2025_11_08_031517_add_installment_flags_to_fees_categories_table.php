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
        Schema::table('fees_categories', function (Blueprint $table) {
            $table->boolean('is_first_installment')->default(0)->after('is_admission');
            $table->boolean('is_second_installment')->default(0)->after('is_first_installment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fees_categories', function (Blueprint $table) {
            $table->dropColumn(['is_first_installment', 'is_second_installment']);
        });
    }
};
