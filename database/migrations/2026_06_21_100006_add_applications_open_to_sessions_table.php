<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a session (intake term) is currently accepting online applications.
 * The applicant intake step only offers sessions with applications_open = 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('sessions', 'applications_open')) {
                $table->boolean('applications_open')->default(0)->after('current');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            if (Schema::hasColumn('sessions', 'applications_open')) {
                $table->dropColumn('applications_open');
            }
        });
    }
};
