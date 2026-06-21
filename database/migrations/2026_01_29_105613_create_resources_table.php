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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('language_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('other'); // student_guide, calendarium, academic, forms, policies, handbook, other
            $table->string('icon')->nullable(); // Custom icon class (e.g., fas fa-book)
            $table->string('file_path');
            $table->string('file_name'); // Original file name
            $table->bigInteger('file_size')->default(0); // File size in bytes
            $table->string('file_type')->nullable(); // MIME type
            $table->integer('download_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            
            $table->foreign('language_id')->references('id')->on('languages')->onDelete('cascade');
            $table->index(['category', 'status']);
            $table->index('language_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
