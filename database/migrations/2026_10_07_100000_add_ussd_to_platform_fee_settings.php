<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let the school give students a shortcode they can tap instead of type.
 *
 * Paying meant reading a merchant number off the screen, remembering it, and
 * typing a USSD string by hand — the commonest way a payment goes to the wrong
 * place or for the wrong amount. The school stores the pattern once; the portal
 * fills in the amount and turns it into a link the phone's dialler opens.
 *
 * The pattern is a template rather than a finished code, because the amount
 * differs and the merchant number does not: *126*4*123456*{amount}#
 *
 * merchant_name is shown beside it so the student can check, before entering
 * their PIN, that the name their phone shows back is the school's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_settings', function (Blueprint $table) {
            $table->string('ussd_template')->nullable()->after('payment_instructions');
            $table->string('merchant_name')->nullable()->after('ussd_template');
            $table->string('merchant_number')->nullable()->after('merchant_name');
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_settings', function (Blueprint $table) {
            $table->dropColumn(['ussd_template', 'merchant_name', 'merchant_number']);
        });
    }
};
