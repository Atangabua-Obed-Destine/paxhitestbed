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
            // Add academic_level field to differentiate program levels
            // A = Undergraduate (Bachelor, Associate, etc.)
            // M = Masters (Postgraduate)
            // D = Doctoral (PhD)
            $table->char('academic_level', 1)->default('A')->after('degree_type_id');
            
            // Add index for filtering
            $table->index('academic_level');
        });
        
        // Update existing programs based on degree type
        // If degree type is_postgraduate = true, set to M (Masters)
        // If title contains PhD or Doctor, set to D (Doctoral)
        // Otherwise keep as A (Undergraduate)
        DB::statement("
            UPDATE programs p
            INNER JOIN degree_types dt ON p.degree_type_id = dt.id
            SET p.academic_level = CASE
                WHEN dt.title LIKE '%PhD%' OR dt.title LIKE '%Doctor%' OR dt.level LIKE '%Doctor%' THEN 'D'
                WHEN dt.is_postgraduate = 1 OR dt.level LIKE '%Postgraduate%' OR dt.level LIKE '%Master%' THEN 'M'
                ELSE 'A'
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropIndex(['academic_level']);
            $table->dropColumn('academic_level');
        });
    }
};
