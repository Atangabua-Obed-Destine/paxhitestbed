<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give a resit request somewhere to finish.
 *
 * The workflow ended at 'scheduled' and nothing ever moved a request on, so a
 * resit that had been sat and marked months earlier still read as in progress.
 * Every screen that asks "is this course being handled?" took that at face value
 * and hid the course — a student could fail a resit and carry the course
 * invisibly.
 *
 * These columns record that the paper was sat and how it went. The outcome is a
 * convenience for reporting; the mark remains the record of record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resit_requests', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('state_changed_by');
            $table->string('outcome', 20)->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('resit_requests', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'outcome']);
        });
    }
};
