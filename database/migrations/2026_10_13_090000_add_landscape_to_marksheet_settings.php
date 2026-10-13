<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Print the transcript on its side, two semesters to a row.
 *
 * A portrait transcript stacks its semesters down the page, so a student with
 * four of them runs to three sheets and every semester is narrow enough that
 * course titles wrap. Turned sideways the page is 297mm instead of 210mm, which
 * is wide enough for two semester tables side by side.
 *
 * Off by default: portrait is what registrars, embassies and credential
 * services expect, and a school that does not turn this on sees no change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('marksheet_settings', 'landscape')) {
            return;
        }

        Schema::table('marksheet_settings', function (Blueprint $table) {
            $table->boolean('landscape')->default(false)->after('barcode');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('marksheet_settings', 'landscape')) {
            return;
        }

        Schema::table('marksheet_settings', function (Blueprint $table) {
            $table->dropColumn('landscape');
        });
    }
};
