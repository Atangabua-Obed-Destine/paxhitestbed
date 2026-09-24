<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school-specific transcript rule, off unless a school turns it on.
 *
 * With it on, a resit the student passed is shown against the semester the
 * course was first taken, in place of the fail, and drops out of the resit
 * semester. A resit failed again is left exactly as it is.
 *
 * It changes the transcript and nothing else: no mark is rewritten, and mark
 * sheets, results, progression and the student portal read what they always did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marksheet_settings', function (Blueprint $table) {
            $table->boolean('resit_replaces_original')->default(false)->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('marksheet_settings', function (Blueprint $table) {
            $table->dropColumn('resit_replaces_original');
        });
    }
};
