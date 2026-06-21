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
        Schema::create('program_semester_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('program_id');
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('fees_category_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('fees_category_id')->references('id')->on('fees_categories')->onDelete('cascade');
            
            // Unique constraint: one fee amount per program-semester-category combination
            $table->unique(['program_id', 'semester_id', 'fees_category_id'], 'program_semester_fee_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_semester_fees');
    }
};
