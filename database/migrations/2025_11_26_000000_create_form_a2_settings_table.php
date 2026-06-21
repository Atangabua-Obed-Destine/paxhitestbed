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
        Schema::create('form_a2_settings', function (Blueprint $table) {
            $table->id();
            $table->string('finance_director_name')->nullable();
            $table->string('registrar_name')->nullable();
            $table->timestamps();
        });

        // Insert default record
        DB::table('form_a2_settings')->insert([
            'finance_director_name' => 'Director Name',
            'registrar_name' => 'Registrar Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_a2_settings');
    }
};
