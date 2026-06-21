<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->timestamp('portal_last_login_at')->nullable()->after('email_verified_at');
            $table->string('stage', 64)->default('submitted')->after('status');
            $table->unsignedTinyInteger('progress')->default(10)->after('stage');
            $table->json('portal_meta')->nullable()->after('progress');
            $table->timestamp('completed_at')->nullable()->after('portal_meta');
            $table->timestamp('decision_at')->nullable()->after('completed_at');
        });

        DB::table('applications')->where('status', 2)->update([
            'stage' => 'decision_approved',
            'progress' => 100,
            'decision_at' => now(),
            'completed_at' => now(),
        ]);

        DB::table('applications')->where('status', 0)->update([
            'stage' => 'decision_rejected',
            'progress' => 100,
            'decision_at' => now(),
        ]);

        DB::table('applications')->where('status', 1)->update([
            'stage' => 'under_review',
            'progress' => 30,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'remember_token',
                'email_verified_at',
                'portal_last_login_at',
                'stage',
                'progress',
                'portal_meta',
                'completed_at',
                'decision_at',
            ]);
        });
    }
};
