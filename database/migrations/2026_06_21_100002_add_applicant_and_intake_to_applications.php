<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link each Application to its owning Applicant account, the degree type it is
 * for, and its intake session. Columns are nullable + indexed (no hard FK
 * constraints, to avoid engine type-mismatch issues on existing tables); the
 * backfill migration populates them for existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'applicant_id')) {
                $table->unsignedBigInteger('applicant_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('applications', 'degree_type_id')) {
                $table->unsignedBigInteger('degree_type_id')->nullable()->after('applicant_id')->index();
            }
            if (!Schema::hasColumn('applications', 'session_id')) {
                $table->unsignedInteger('session_id')->nullable()->after('degree_type_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            foreach (['applicant_id', 'degree_type_id', 'session_id'] as $col) {
                if (Schema::hasColumn('applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
