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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->integer('faculty_id')->unsigned()->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('lead_researcher')->nullable();
            $table->string('theme')->nullable(); // Research theme/category
            $table->enum('status', ['ongoing', 'completed'])->default('ongoing');
            $table->text('attach')->nullable(); // Project image
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('featured')->default('0');
            $table->boolean('is_active')->default('1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
