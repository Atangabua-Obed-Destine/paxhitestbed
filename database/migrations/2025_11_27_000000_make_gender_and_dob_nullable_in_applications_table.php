<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE applications MODIFY gender TINYINT NULL');
        DB::statement('ALTER TABLE applications MODIFY dob DATE NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('applications', function (Blueprint $table) {
            // We cannot easily revert this without knowing the original data state, 
            // but we can try to set them back to not null if we assume data is fixed.
            // For safety in this context, we might leave them nullable or just define the change back.
            // However, changing back to NOT NULL might fail if there are null values.
            // So we will leave the down method empty or just comment it out to avoid issues.
        });
    }
};
