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
        Schema::create('dynamic_popups', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('summary')->nullable();
            $table->string('image')->nullable();
            $table->string('button_text', 50)->nullable();
            $table->string('button_color', 10)->default('#007bff');
            $table->enum('button_text_color', ['light', 'dark'])->default('light');
            $table->string('link')->nullable();
            $table->json('target_areas')->nullable()->comment('Array of areas: front_web, student_portal, applicant_portal, admin_portal, login_pages, all');
            $table->enum('display_frequency', ['once_ever', 'once_session', 'once_day', 'always'])->default('once_session');
            $table->enum('popup_position', ['center', 'bottom_right', 'bottom_left', 'top_right'])->default('center');
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->integer('priority')->default(0)->comment('Higher priority shows first');
            $table->boolean('is_dismissible')->default(true);
            $table->boolean('is_pinned')->default(false)->comment('Pinned popups cannot be bulk deleted');
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_popups');
    }
};
