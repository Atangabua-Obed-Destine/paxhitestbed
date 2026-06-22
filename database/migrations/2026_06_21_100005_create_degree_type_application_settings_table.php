<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-degree-type application settings: the intro / requirements blurb shown at
 * the top of that degree type's form, and its admission-fee configuration
 * (overrides the global env-based fee).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('degree_type_application_settings')) {
            return;
        }

        Schema::create('degree_type_application_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('degree_type_id')->unique();
            $table->text('intro_html')->nullable();
            $table->text('requirements_html')->nullable();
            $table->boolean('fee_enabled')->default(1);
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->unsignedInteger('fee_due_days')->nullable();
            $table->text('fee_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('degree_type_application_settings');
    }
};
