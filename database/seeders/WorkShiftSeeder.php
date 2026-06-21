<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class WorkShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('work_shift_types')->delete();

        $work_shift_types = [
            ['title' => 'Morning', 'slug' => 'morning', 'status' => '1'],
            ['title' => 'Evening', 'slug' => 'evening', 'status' => '1'],
        ];

        DB::table('work_shift_types')->insert($work_shift_types);
    }
}
