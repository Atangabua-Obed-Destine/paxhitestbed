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
        Schema::create('program_semester_fee_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_semester_fee_id');
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->integer('order')->default(0)->comment('Display order');
            $table->timestamps();
            
            // Foreign key
            $table->foreign('program_semester_fee_id', 'psf_breakdown_fk')
                  ->references('id')
                  ->on('program_semester_fees')
                  ->onDelete('cascade');
            
            // Index for faster queries
            $table->index('program_semester_fee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_semester_fee_breakdowns');
    }
};
