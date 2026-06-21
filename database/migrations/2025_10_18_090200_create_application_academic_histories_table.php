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
        Schema::create('application_academic_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('institution_name');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('instruction_language')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('certificate_obtained')->nullable();
            $table->string('gce_ol_detail')->nullable();
            $table->string('gce_al_detail')->nullable();
            $table->string('probatoire_detail')->nullable();
            $table->string('baccalaureate_detail')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_academic_histories');
    }
};
