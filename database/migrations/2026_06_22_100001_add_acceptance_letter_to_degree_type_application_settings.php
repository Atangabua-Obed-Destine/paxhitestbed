<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-degree-type acceptance letter: an enable toggle and the rich HTML letter
 * body (with [placeholder] tokens). Rendered to PDF and emailed to the applicant
 * automatically when their application is converted to a student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('degree_type_application_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('degree_type_application_settings', 'acceptance_letter_enabled')) {
                $table->boolean('acceptance_letter_enabled')->default(0)->after('fee_instructions');
            }
            if (!Schema::hasColumn('degree_type_application_settings', 'acceptance_letter_html')) {
                $table->text('acceptance_letter_html')->nullable()->after('acceptance_letter_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('degree_type_application_settings', function (Blueprint $table) {
            foreach (['acceptance_letter_enabled', 'acceptance_letter_html'] as $col) {
                if (Schema::hasColumn('degree_type_application_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
