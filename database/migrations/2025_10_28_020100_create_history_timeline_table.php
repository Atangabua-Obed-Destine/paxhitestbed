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
        Schema::create('history_timeline', function (Blueprint $table) {
            $table->id();
            $table->string('year'); // e.g., "2005", "2010", "Present"
            $table->string('title'); // e.g., "The Founding", "First Graduating Class"
            $table->text('description');
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_timeline');
    }
};
