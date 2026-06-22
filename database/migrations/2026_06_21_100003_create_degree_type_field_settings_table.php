<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-degree-type override of the global application form field/section toggles
 * (the `fields` table slugs). A row here overrides the global Field status for
 * that degree type; absence = inherit the global default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('degree_type_field_settings')) {
            return;
        }

        Schema::create('degree_type_field_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('degree_type_id')->index();
            $table->string('slug');
            $table->boolean('status')->default(1);
            $table->timestamps();

            $table->unique(['degree_type_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('degree_type_field_settings');
    }
};
