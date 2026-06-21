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
        Schema::create('support_services', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // e.g., "Student Counseling", "Career Services"
            $table->text('description');
            $table->string('icon')->nullable(); // Icon class or image
            $table->string('contact_info')->nullable(); // Email, phone, or location
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
        Schema::dropIfExists('support_services');
    }
};
