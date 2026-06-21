<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReligionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $religions = [
            ['title' => 'Catholic', 'slug' => 'catholic', 'is_catholic' => true, 'status' => true],
            ['title' => 'Protestant', 'slug' => 'protestant', 'is_catholic' => false, 'status' => true],
            ['title' => 'Orthodox', 'slug' => 'orthodox', 'is_catholic' => false, 'status' => true],
            ['title' => 'Muslim', 'slug' => 'muslim', 'is_catholic' => false, 'status' => true],
            ['title' => 'Jewish', 'slug' => 'jewish', 'is_catholic' => false, 'status' => true],
            ['title' => 'Hindu', 'slug' => 'hindu', 'is_catholic' => false, 'status' => true],
            ['title' => 'Buddhist', 'slug' => 'buddhist', 'is_catholic' => false, 'status' => true],
            ['title' => 'Other', 'slug' => 'other', 'is_catholic' => false, 'status' => true],
        ];

        DB::table('religions')->insert($religions);
    }
}
