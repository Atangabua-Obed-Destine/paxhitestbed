<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_a3_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hnd_coordinator_name')->nullable();
            $table->string('dir_acad_name')->nullable();
            $table->timestamps();
        });

        // Insert default record
        DB::table('form_a3_settings')->insert([
            'hnd_coordinator_name' => 'HND Coordinator Name',
            'dir_acad_name' => 'Director of Academics Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_a3_settings');
    }
};
