<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let the school collect the platform fee online, and switch back when it wants.
 *
 * Paying meant dialling a shortcode, screenshotting the confirmation, uploading
 * it, and waiting for somebody to approve the row by eye. A gateway removes all
 * four steps — but a school that has just lost its internet, or whose gateway
 * account is in dispute, needs to go back to the manual route in one click and
 * with no code change. online_payment_enabled is that click.
 *
 * The credentials deliberately do NOT live here. They go in .env, like every
 * other gateway on this system, so a database dump never carries a key.
 *
 * The two gateway_fee_* columns exist because the school passes the gateway's
 * commission on to the student: a 2,000 fee is collected as 2,063. A flat
 * surcharge would silently become wrong the moment the fee changed, so the
 * percentage carries it and the fixed part is there for a provider that charges
 * one. fapshi:reconcile measures amount - revenue against these, so the figure
 * is checked against what the provider actually took rather than assumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_settings', function (Blueprint $table) {
            $table->boolean('online_payment_enabled')->default(false)->after('is_enabled')
                ->comment('Offer gateway payment to students; off falls back to manual receipts');
            $table->decimal('gateway_fee_percent', 5, 2)->default(0)->after('fee_amount');
            $table->decimal('gateway_fee_fixed', 10, 2)->default(0)->after('gateway_fee_percent');
        });

        // Changed in its own statement: dbal builds a column change by
        // comparing the whole table, and mixing one into the same closure as
        // the additions above has it compare against a table that does not
        // exist yet in that form.
        //
        // FRW is the Rwandan franc. The default was wrong for a Cameroonian
        // system from the start, and the testbed carried it through to the
        // student's screen while the amount was plainly meant as FCFA.
        Schema::table('platform_fee_settings', function (Blueprint $table) {
            $table->string('currency', 10)->default('XAF')->change();
        });

        // Only where it is still the old default — a school that deliberately
        // set something else keeps it.
        DB::table('platform_fee_settings')->where('currency', 'FRW')->update(['currency' => 'XAF']);
    }

    public function down(): void
    {
        Schema::table('platform_fee_settings', function (Blueprint $table) {
            $table->dropColumn(['online_payment_enabled', 'gateway_fee_percent', 'gateway_fee_fixed']);
        });

        Schema::table('platform_fee_settings', function (Blueprint $table) {
            $table->string('currency', 10)->default('FRW')->change();
        });
    }
};
