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
        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Staff member
            $table->string('assignable_type'); // Faculty, Program, Course
            $table->unsignedBigInteger('assignable_id'); // ID of faculty/program/course
            $table->unsignedBigInteger('created_by')->nullable(); // Who created this assignment
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index(['user_id', 'assignable_type', 'assignable_id'], 'staff_assignment_index');
            $table->index('user_id');
            $table->index(['assignable_type', 'assignable_id']);
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Prevent duplicate assignments
            $table->unique(['user_id', 'assignable_type', 'assignable_id'], 'unique_staff_assignment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_assignments');
    }
};
