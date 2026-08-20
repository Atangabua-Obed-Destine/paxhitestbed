<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-degree-type qualification cards for the application form.
 *
 * Mirrors degree_type_documents: a degree type declares which qualifications an
 * applicant must present (A-Level, O-Level, ...), and each row becomes one card
 * on the Academic Qualifications step. Documents are attached to a card through
 * degree_type_documents.qualification_group, so how many upload slots a card
 * shows is entirely an administrator's decision.
 *
 * Falls back to App\Support\ApplicationQualificationRequirements when a degree
 * type has no rows, the same contract degree_type_documents already uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('degree_type_qualifications')) {
            return;
        }

        Schema::create('degree_type_qualifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('degree_type_id')->index();
            $table->string('qual_key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('required')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();

            $table->unique(['degree_type_id', 'qual_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('degree_type_qualifications');
    }
};
