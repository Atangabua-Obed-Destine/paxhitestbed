<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentAccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $types = [
            [
                'title' => 'Cash on Hand',
                'slug' => 'cash-on-hand',
                'description' => 'Physical cash kept in office or safe',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Bank Account',
                'slug' => 'bank-account',
                'description' => 'Money deposited in bank accounts',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Mobile Money',
                'slug' => 'mobile-money',
                'description' => 'Mobile money accounts (MTN, Airtel, etc.)',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Petty Cash',
                'slug' => 'petty-cash',
                'description' => 'Small cash fund for minor expenses',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('payment_account_types')->insert($types);
    }
}
