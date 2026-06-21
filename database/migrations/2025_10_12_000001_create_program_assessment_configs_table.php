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
        Schema::dropIfExists('program_assessment_configs');

        Schema::create('program_assessment_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('program_id');
            $table->unsignedInteger('semester_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->decimal('exam_weight', 5, 2);
            $table->decimal('ca_weight', 5, 2);
            $table->decimal('attendance_weight', 5, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('program_id', 'pac_program_fk')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('semester_id', 'pac_semester_fk')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('subject_id', 'pac_subject_fk')->references('id')->on('subjects')->onDelete('cascade');

            $table->unique([
                'program_id',
                'semester_id',
                'subject_id',
                'effective_from',
            ], 'program_assessment_configs_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_assessment_configs');
    }
};
