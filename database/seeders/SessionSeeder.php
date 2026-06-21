<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use App\Models\Session;

class SessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sessions')->delete();

        $sessions = Session::create([

            'title'=>'Spring-2022',
            'start_date'=>'2022-01-01',
            'end_date'=>'2022-04-30',
            'current'=>'1',
            'status'=>'1',

        ]);
    }
}
