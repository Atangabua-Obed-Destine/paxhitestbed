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
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->boolean('is_dependent')->default(false)->after('tax_group_id')
                  ->comment('Whether this tax calculates from another tax result instead of salary');
            $table->string('depends_on_type', 20)->nullable()->after('is_dependent')
                  ->comment('Source type: tax_group or tax_setting');
            $table->unsignedBigInteger('depends_on_id')->nullable()->after('depends_on_type')
                  ->comment('ID of the source tax group or standalone tax setting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->dropColumn(['is_dependent', 'depends_on_type', 'depends_on_id']);
        });
    }
};
