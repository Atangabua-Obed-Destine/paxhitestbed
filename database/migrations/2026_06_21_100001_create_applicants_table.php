<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicant = the login account (one per person). It owns many applications.
 * This separates the login identity from a single Application submission so an
 * applicant can create and track multiple applications (one per degree type /
 * intake), Ellucian Recruit style.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applicants')) {
            return;
        }

        Schema::create('applicants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('portal_last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
