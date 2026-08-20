<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions to the application form:
 *
 *  - Expiry dates for the identity documents, alongside the issue dates that
 *    already exist, so the Identification tab can capture a full document.
 *  - A certificate/result-slip file against each academic history entry, so the
 *    evidence sits with the qualification it proves instead of in a flat,
 *    application-wide document checklist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'national_id_expiry_date')) {
                $table->date('national_id_expiry_date')->nullable()->after('national_id_issue_place');
            }
            if (!Schema::hasColumn('applications', 'passport_expiry_date')) {
                $table->date('passport_expiry_date')->nullable()->after('passport_issue_country');
            }
        });

        Schema::table('application_academic_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('application_academic_histories', 'certificate_file')) {
                $table->string('certificate_file')->nullable()->after('certificate_obtained');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            foreach (['national_id_expiry_date', 'passport_expiry_date'] as $column) {
                if (Schema::hasColumn('applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('application_academic_histories', function (Blueprint $table) {
            if (Schema::hasColumn('application_academic_histories', 'certificate_file')) {
                $table->dropColumn('certificate_file');
            }
        });
    }
};
