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
        Schema::create('degree_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 100);
            $table->string('shortcode', 20)->unique();
            $table->string('level', 50); // Undergraduate, Postgraduate, Certificate, Diploma
            $table->integer('duration_years')->nullable();
            $table->integer('min_credits')->nullable();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('degree_types');
    }
};
