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
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            // Add 'analytical' to account_type enum
            \DB::statement("ALTER TABLE chart_of_accounts MODIFY COLUMN account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense', 'other', 'analytical')");
            
            // Update class_number comment to reflect 1-9 OHADA classes
            \DB::statement("ALTER TABLE chart_of_accounts MODIFY COLUMN class_number TINYINT COMMENT '1-9 for OHADA classes'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            // Revert account_type enum to original values
            \DB::statement("ALTER TABLE chart_of_accounts MODIFY COLUMN account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense', 'other')");
            
            // Revert class_number comment
            \DB::statement("ALTER TABLE chart_of_accounts MODIFY COLUMN class_number TINYINT COMMENT '1-8 for OHADA classes'");
        });
    }
};
