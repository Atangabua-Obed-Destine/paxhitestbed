<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PlatformFeeSetting;
use App\Models\Setting;

class PlatformFeeSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get system currency
        $systemSetting = Setting::where('status', '1')->first();
        $currency = $systemSetting->currency ?? 'FRW';

        PlatformFeeSetting::create([
            'title' => 'Platform Access Fee',
            'welcome_message' => 'Welcome to our academic platform! To access your student portal for this academic session, a one-time platform access fee is required.',
            'fee_amount' => 0.00,
            'is_enabled' => false,
            'payment_instructions' => 'Please make payment and upload your receipt for verification. Once verified by the administration, you will gain full access to your student portal.',
            'currency' => $currency,
            'status' => 1,
        ]);
    }
}
