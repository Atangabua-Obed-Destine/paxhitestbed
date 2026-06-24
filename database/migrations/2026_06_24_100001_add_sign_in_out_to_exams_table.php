<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Per-student exam Sign In / Sign Out verification (final-exam invigilation).
 * present (attendance = 1) is derived as sign_in AND sign_out. Existing present
 * rows are backfilled to both-verified so historical records stay consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'sign_in')) {
                $table->boolean('sign_in')->default(0)->after('attendance');
            }
            if (!Schema::hasColumn('exams', 'sign_out')) {
                $table->boolean('sign_out')->default(0)->after('sign_in');
            }
        });

        // Backfill: present records (attendance = 1) are treated as signed in + out.
        DB::table('exams')->where('attendance', 1)->update(['sign_in' => 1, 'sign_out' => 1]);
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            foreach (['sign_in', 'sign_out'] as $col) {
                if (Schema::hasColumn('exams', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
