<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let an applicant account be switched off without deleting anything.
 *
 * Deleting an account would take its applications with it — or orphan them —
 * and an application is a record the school keeps. Disabling stops the login
 * and leaves every application exactly where it is.
 *
 * A timestamp rather than a flag, so the list can say when it happened; who
 * did it and why sit beside it, because "why can't this person log in?" is the
 * first question anyone will ask about a disabled account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('portal_last_login_at');
            $table->unsignedBigInteger('disabled_by')->nullable()->after('disabled_at');
            $table->string('disabled_reason', 500)->nullable()->after('disabled_by');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['disabled_at', 'disabled_by', 'disabled_reason']);
        });
    }
};
