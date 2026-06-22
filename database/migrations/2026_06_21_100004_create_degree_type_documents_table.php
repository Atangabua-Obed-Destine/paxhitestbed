<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-degree-type document checklist. Replaces the single global hardcoded list
 * (App\Support\ApplicationDocumentRequirements) for the applicant form: each
 * degree type defines its own required/optional documents. Seeded from the
 * current global catalog so behaviour is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('degree_type_documents')) {
            return;
        }

        Schema::create('degree_type_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('degree_type_id')->index();
            $table->string('doc_key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('required')->default(0);
            $table->string('assign_to_column')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(1);
            $table->timestamps();

            $table->unique(['degree_type_id', 'doc_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('degree_type_documents');
    }
};
