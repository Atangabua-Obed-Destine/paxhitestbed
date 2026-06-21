<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Accounting Settings - System-wide accounting configuration
     */
    public function up(): void
    {
        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('string'); // string, integer, boolean, json
            $table->string('category')->default('general'); // general, closing, depreciation, etc.
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        $settings = [
            // General Settings
            ['setting_key' => 'default_currency', 'setting_value' => 'XAF', 'setting_type' => 'string', 'category' => 'general', 'description' => 'Default currency code'],
            ['setting_key' => 'currency_symbol', 'setting_value' => 'FCFA', 'setting_type' => 'string', 'category' => 'general', 'description' => 'Currency symbol for display'],
            ['setting_key' => 'decimal_places', 'setting_value' => '2', 'setting_type' => 'integer', 'category' => 'general', 'description' => 'Number of decimal places for amounts'],
            ['setting_key' => 'thousand_separator', 'setting_value' => ' ', 'setting_type' => 'string', 'category' => 'general', 'description' => 'Thousand separator character'],
            ['setting_key' => 'decimal_separator', 'setting_value' => ',', 'setting_type' => 'string', 'category' => 'general', 'description' => 'Decimal separator character'],
            
            // Year-End Closing Settings
            ['setting_key' => 'retained_earnings_account_id', 'setting_value' => null, 'setting_type' => 'integer', 'category' => 'closing', 'description' => 'Account ID for retained earnings'],
            ['setting_key' => 'income_summary_account_id', 'setting_value' => null, 'setting_type' => 'integer', 'category' => 'closing', 'description' => 'Temporary account for income summary during closing'],
            ['setting_key' => 'auto_create_opening_entries', 'setting_value' => 'true', 'setting_type' => 'boolean', 'category' => 'closing', 'description' => 'Automatically create opening entries for new year'],
            
            // Depreciation Settings
            ['setting_key' => 'depreciation_posting_day', 'setting_value' => '28', 'setting_type' => 'integer', 'category' => 'depreciation', 'description' => 'Day of month to post depreciation entries'],
            ['setting_key' => 'auto_post_depreciation', 'setting_value' => 'false', 'setting_type' => 'boolean', 'category' => 'depreciation', 'description' => 'Automatically post depreciation entries'],
            
            // Bank Reconciliation Settings
            ['setting_key' => 'reconciliation_tolerance', 'setting_value' => '1', 'setting_type' => 'decimal', 'category' => 'reconciliation', 'description' => 'Tolerance amount for reconciliation differences'],
            ['setting_key' => 'require_reconciliation_approval', 'setting_value' => 'true', 'setting_type' => 'boolean', 'category' => 'reconciliation', 'description' => 'Require approval for completed reconciliations'],
            
            // Aging Report Settings
            ['setting_key' => 'aging_bucket_1', 'setting_value' => '30', 'setting_type' => 'integer', 'category' => 'aging', 'description' => 'First aging bucket (days)'],
            ['setting_key' => 'aging_bucket_2', 'setting_value' => '60', 'setting_type' => 'integer', 'category' => 'aging', 'description' => 'Second aging bucket (days)'],
            ['setting_key' => 'aging_bucket_3', 'setting_value' => '90', 'setting_type' => 'integer', 'category' => 'aging', 'description' => 'Third aging bucket (days)'],
            ['setting_key' => 'aging_bucket_4', 'setting_value' => '120', 'setting_type' => 'integer', 'category' => 'aging', 'description' => 'Fourth aging bucket (days)'],
        ];

        foreach ($settings as $setting) {
            \DB::table('accounting_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
    }
};
