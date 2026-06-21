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
        Schema::create('exam_attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('minimum_attendance_percentage', 5, 2)->default(70.00)->comment('Minimum course attendance % required for exam eligibility');
            $table->boolean('is_enabled')->default(true)->comment('Whether attendance check is enabled');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attendance_settings');
    }
};
